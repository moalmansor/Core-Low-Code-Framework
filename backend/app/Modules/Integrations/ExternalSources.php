<?php

declare(strict_types=1);

namespace App\Modules\Integrations;

use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\EgressViolation;
use App\Modules\Audit\AuditWriter;
use App\Modules\Integrations\Jobs\RefreshExternalSource;
use App\Modules\Integrations\Models\ExternalDataSource;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * External data sources (specification §4.33, architecture §19.21,
 * ADR-0057): rows read from a REST endpoint (through the egress gateway) or
 * from a view of a database connection defined in the environment, mapped
 * to a value, a label, an optional parent (for cascading choices) and extra
 * columns, and cached per the source's policy. They feed choice fields
 * ("external" option source, checked again on save), read-only panels and
 * sync jobs, exactly like a collection.
 *
 * Nothing an admin types reaches SQL as text: a view and its columns are
 * checked against the connection's catalogue when saved, and queried with
 * the query builder; a REST response is parsed as JSON and read by paths.
 */
final class ExternalSources
{
    public const TYPES = ['rest', 'db_view'];

    public const AUTH = ['none', 'api_key', 'bearer', 'basic', 'oauth2_client_credentials'];

    public const CACHE = ['none', 'ttl', 'stale_while_revalidate'];

    /** Secrets per authentication type (write-only for admins). */
    public const SECRETS = ['api_key' => ['key'], 'bearer' => ['token'], 'basic' => ['password'], 'oauth2_client_credentials' => ['client_secret']];

    /** Settings per authentication type: name => required. */
    public const AUTH_FIELDS = [
        'none' => [],
        'api_key' => ['header' => true, 'key' => true],
        'bearer' => ['token' => true],
        'basic' => ['username' => true, 'password' => true],
        'oauth2_client_credentials' => ['token_url' => true, 'client_id' => true, 'client_secret' => true, 'scope' => false],
    ];

    /** Rows read from one source at most (a source is a list to choose from, not a data warehouse). */
    public const MAX_ROWS = 5000;

    private const MAX_BYTES = 5_242_880;

    public function __construct(private readonly EgressGateway $egress, private readonly AuditWriter $audit) {}

    /** Names of the environment-defined connections whose views may be registered. @return list<string> */
    public static function connections(): array
    {
        return array_values(array_map(static fn (string $k) => substr($k, 9), array_filter(array_keys((array) config('database.connections')), static fn ($k) => str_starts_with((string) $k, 'external_'))));
    }

    /**
     * Checks an admin's document and returns the row to save. Secrets left
     * empty keep the saved ones.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalize(array $d, ?ExternalDataSource $existing): array
    {
        $errors = [];
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
            $errors['key'][] = __('integrations.bad_key');
        } elseif (ExternalDataSource::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('integrations.key_taken');
        }
        $type = (string) ($d['type'] ?? '');
        if (! in_array($type, self::TYPES, true)) {
            $errors['type'][] = __('integrations.bad_type');
        }
        $row = ['key' => $key, 'type' => $type, 'rest_config' => null, 'db_connection' => null, 'db_view' => null, 'auth_type' => 'none', 'credentials' => null, 'headers' => null];
        $columns = null;
        if ($type === 'rest') {
            $c = (array) ($d['rest_config'] ?? []);
            $url = trim((string) ($c['url'] ?? ''));
            if (! $this->allowedUrl($url)) {
                $errors['rest_config.url'][] = __('integrations.bad_url');
            }
            $method = strtoupper((string) ($c['method'] ?? 'GET'));
            if (! in_array($method, ['GET', 'POST'], true)) {
                $errors['rest_config.method'][] = __('integrations.bad_method');
            }
            $query = [];
            foreach (array_slice(array_values((array) ($c['query'] ?? [])), 0, 20) as $i => $p) {
                $name = trim((string) ($p['name'] ?? ''));
                if ($name === '' || preg_match('/^[A-Za-z0-9_.\-\[\]]{1,64}$/', $name) !== 1) {
                    $errors["rest_config.query.{$i}"][] = __('integrations.bad_param');

                    continue;
                }
                $query[] = ['name' => $name, 'value' => mb_substr((string) ($p['value'] ?? ''), 0, 500)];
            }
            $body = trim((string) ($c['body'] ?? ''));
            if ($method === 'POST' && $body !== '' && json_decode(self::fillJson($body, ['q' => 'x', 'depends' => 'x'])) === null) {
                $errors['rest_config.body'][] = __('integrations.bad_body');
            }
            $recordsPath = trim((string) ($c['records'] ?? ''));
            if ($recordsPath !== '' && preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/', $recordsPath) !== 1) {
                $errors['rest_config.records'][] = __('integrations.bad_path');
            }
            $row['rest_config'] = ['url' => $url, 'method' => $method, 'query' => $query, 'body' => $method === 'POST' && $body !== '' ? $body : null, 'records' => $recordsPath];
            $auth = (string) ($d['auth_type'] ?? 'none');
            if (! in_array($auth, self::AUTH, true)) {
                $errors['auth_type'][] = __('integrations.bad_auth');
                $auth = 'none';
            }
            $row['auth_type'] = $auth;
            $saved = $existing !== null && $existing->auth_type === $auth ? (array) $existing->credentials : [];
            $creds = [];
            foreach (self::AUTH_FIELDS[$auth] as $name => $required) {
                $value = trim((string) ($d['credentials'][$name] ?? ''));
                if ($value === '' && in_array($name, self::SECRETS[$auth] ?? [], true) && isset($saved[$name])) {
                    $value = (string) $saved[$name];
                }
                if ($value === '') {
                    if ($required) {
                        $errors["credentials.{$name}"][] = __('integrations.required');
                    }

                    continue;
                }
                $creds[$name] = mb_substr($value, 0, 4000);
            }
            if (isset($creds['header']) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $creds['header']) !== 1) {
                $errors['credentials.header'][] = __('integrations.bad_header');
            }
            if (isset($creds['token_url']) && ! $this->allowedUrl($creds['token_url'])) {
                $errors['credentials.token_url'][] = __('integrations.bad_url');
            }
            $row['credentials'] = $creds === [] ? null : $creds;
            $savedHeaders = [];
            foreach ((array) ($existing->headers ?? []) as $h) {
                $savedHeaders[strtolower((string) $h['name'])] = (string) $h['value'];
            }
            $headers = [];
            foreach (array_slice(array_values((array) ($d['headers'] ?? [])), 0, 10) as $i => $h) {
                $name = trim((string) ($h['name'] ?? ''));
                $value = (string) ($h['value'] ?? '');
                if ($value === '' && isset($savedHeaders[strtolower($name)])) {
                    $value = $savedHeaders[strtolower($name)];
                }
                if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $name) !== 1 || in_array(strtolower($name), ['host', 'content-length', 'authorization'], true) || $value === '') {
                    $errors["headers.{$i}"][] = __('integrations.bad_header');

                    continue;
                }
                $headers[] = ['name' => $name, 'value' => mb_substr($value, 0, 2000)];
            }
            $row['headers'] = $headers === [] ? null : $headers;
        } elseif ($type === 'db_view') {
            $connection = (string) ($d['db_connection'] ?? '');
            $view = (string) ($d['db_view'] ?? '');
            if (! in_array($connection, self::connections(), true)) {
                $errors['db_connection'][] = __('integrations.bad_connection');
            } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}(\.[A-Za-z_][A-Za-z0-9_]{0,63})?$/', $view) !== 1 || ($columns = $this->viewColumns($connection, $view)) === null) {
                $errors['db_view'][] = __('integrations.bad_view');
            }
            $row['db_connection'] = $connection;
            $row['db_view'] = $view;
        }
        $m = (array) ($d['response_mapping'] ?? []);
        $mapping = ['value' => trim((string) ($m['value'] ?? '')), 'label' => trim((string) ($m['label'] ?? '')), 'parent' => trim((string) ($m['parent'] ?? '')) ?: null, 'columns' => []];
        $pathOk = static fn (string $p): bool => $type === 'db_view'
            ? $columns === null || in_array(strtolower($p), array_map('strtolower', $columns), true)
            : preg_match('/^[A-Za-z0-9_\-]+(\.[A-Za-z0-9_\-]+)*$/', $p) === 1;
        foreach (['value', 'label'] as $k) {
            if ($mapping[$k] === '' || ! $pathOk($mapping[$k])) {
                $errors["response_mapping.{$k}"][] = __('integrations.bad_mapping');
            }
        }
        if ($mapping['parent'] !== null && ! $pathOk($mapping['parent'])) {
            $errors['response_mapping.parent'][] = __('integrations.bad_mapping');
        }
        $keys = [];
        foreach (array_slice(array_values((array) ($m['columns'] ?? [])), 0, 20) as $i => $col) {
            $ckey = (string) ($col['key'] ?? '');
            $path = trim((string) ($col['path'] ?? ''));
            $label = [];
            foreach ((array) ($col['label'] ?? []) as $loc => $text) {
                if (is_string($loc) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $loc) === 1 && is_string($text) && trim($text) !== '') {
                    $label[$loc] = mb_substr(trim($text), 0, 120);
                }
            }
            if (preg_match('/^[a-z][a-z0-9_]{0,47}$/', $ckey) !== 1 || isset($keys[$ckey]) || in_array($ckey, ['value', 'label', 'parent'], true) || ! $pathOk($path) || $label === []) {
                $errors["response_mapping.columns.{$i}"][] = __('integrations.bad_column');

                continue;
            }
            $keys[$ckey] = true;
            $mapping['columns'][] = ['key' => $ckey, 'path' => $path, 'label' => $label];
        }
        $policy = (string) ($d['cache_policy'] ?? 'ttl');
        if (! in_array($policy, self::CACHE, true)) {
            $errors['cache_policy'][] = __('integrations.bad_cache');
        }
        $ttl = $d['cache_ttl_seconds'] ?? null;
        if ($policy !== 'none' && (! is_int($ttl) || $ttl < 30 || $ttl > 604800)) {
            $errors['cache_ttl_seconds'][] = __('integrations.bad_ttl');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $row + ['response_mapping' => $mapping, 'cache_policy' => $policy, 'cache_ttl_seconds' => $policy === 'none' ? null : $ttl, 'is_active' => (bool) ($d['active'] ?? true)];
    }

    /**
     * Options for a choice field: searched, filtered by the parent value, paged.
     *
     * @return array{items: list<array{value: string, label: string, parent: string|null}>, total: int}
     */
    public function options(ExternalDataSource $s, string $term, ?string $depends, int $page, int $pageSize): array
    {
        $rows = $this->rows($s, ['q' => $term, 'depends' => $depends]);
        $items = array_map(static fn (array $r) => ['value' => $r['value'], 'label' => $r['label'], 'parent' => $r['parent']], $rows);

        return ['items' => array_slice($items, ($page - 1) * $pageSize, $pageSize), 'total' => count($items)];
    }

    /** Labels of stored values, for showing a record. @param  list<string>  $values  @return array<string, string> */
    public function labels(ExternalDataSource $s, array $values): array
    {
        if ($values === []) {
            return [];
        }
        try {
            $out = [];
            foreach ($this->rows($s, ['values' => $values]) as $r) {
                $out[$r['value']] = $r['label'];
            }

            return $out;
        } catch (SourceFailure) {
            // Showing a record never fails because a source is down: the stored values show as they are.
            return [];
        }
    }

    /** Whether a value is one of the source's (a save is checked against it). */
    public function exists(ExternalDataSource $s, string $value): bool
    {
        foreach ($this->rows($s, ['values' => [$value]]) as $r) {
            if ($r['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rows of the source, mapped and filtered.
     *
     * @param  array{q?: string|null, depends?: string|null, values?: list<string>, match?: array<string, string>}  $filters
     * @return list<array{value: string, label: string, parent: string|null, cells: array<string, mixed>}>
     *
     * @throws SourceFailure
     */
    public function rows(ExternalDataSource $s, array $filters = [], int $limit = self::MAX_ROWS): array
    {
        if (! $s->is_active) {
            throw new SourceFailure(__('integrations.inactive'));
        }
        $fetched = $s->type === 'db_view' ? $this->cached($s, $filters, fn () => $this->queryView($s, $filters, $limit)) : $this->cached($s, $this->restParams($s, $filters), fn () => $this->fetchRest($s, $filters));
        $term = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $depends = $filters['depends'] ?? null;
        $values = isset($filters['values']) ? array_flip(array_map('strval', $filters['values'])) : null;
        $out = [];
        foreach ($fetched as $r) {
            if ($term !== '' && ! str_contains(mb_strtolower($r['label']), $term) && ! str_contains(mb_strtolower($r['value']), $term)) {
                continue;
            }
            if ($depends !== null && $s->response_mapping['parent'] !== null && $r['parent'] !== $depends) {
                continue;
            }
            if ($values !== null && ! isset($values[$r['value']])) {
                continue;
            }
            foreach ($filters['match'] ?? [] as $col => $want) {
                if ((string) ($r['cells'][$col] ?? ($col === 'value' ? $r['value'] : '')) !== (string) $want) {
                    continue 2;
                }
            }
            $out[] = $r;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Calls the source now (no cache) and reports what came back: the first
     * rows as mapped, and the first record as received, to help with the mapping.
     *
     * @return array{ok: bool, error: string|null, count: int, rows: list<array<string, mixed>>, sample: mixed}
     */
    public function test(ExternalDataSource $s): array
    {
        $sample = null;
        try {
            if ($s->type === 'db_view') {
                $rows = $this->queryView($s, [], 50);
                $sample = $this->connection($s)->table((string) $s->db_view)->first();
            } else {
                $raw = $this->restRecords($s, []);
                $sample = $raw[0] ?? null;
                $rows = array_map(fn ($r) => $this->map($s, $r), array_slice($raw, 0, 50));
            }
            $status = 'ok';
            $error = null;
        } catch (SourceFailure $e) {
            $rows = [];
            $status = 'failed';
            $error = $e->getMessage();
        }
        DB::table('external_data_sources')->where('id', $s->id)->update(['last_tested_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'last_test_status' => $status]);

        return ['ok' => $status === 'ok', 'error' => $error, 'count' => count($rows), 'rows' => array_slice($rows, 0, 10), 'sample' => self::maskSample($sample)];
    }

    /** Fetches again and stores the rows for a stale-while-revalidate source (queued). */
    public function refresh(int $sourceId, string $cacheKey, array $filters): void
    {
        /** @var ExternalDataSource|null $s */
        $s = ExternalDataSource::query()->withoutGlobalScope('organization')->find($sourceId);
        if ($s === null || ! $s->is_active) {
            return;
        }
        try {
            $rows = $s->type === 'db_view' ? $this->queryView($s, $filters, self::MAX_ROWS) : $this->fetchRest($s, $filters);
            Cache::put($cacheKey, ['at' => time(), 'rows' => $rows], now()->addDays(7));
        } catch (SourceFailure) {
            // Recorded by the fetch; the stale rows stay in use.
        } finally {
            Cache::forget($cacheKey.':refreshing');
        }
    }

    public function forget(ExternalDataSource $s): void
    {
        Cache::forget('xds-token:'.$s->id);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  callable(): list<array{value: string, label: string, parent: string|null, cells: array<string, mixed>}>  $fetch
     * @return list<array{value: string, label: string, parent: string|null, cells: array<string, mixed>}>
     */
    private function cached(ExternalDataSource $s, array $params, callable $fetch): array
    {
        $key = 'xds:'.$s->id.':'.hash('sha256', $s->updated_at.'|'.json_encode($params));
        $ttl = (int) ($s->cache_ttl_seconds ?? 0);
        if ($s->cache_policy === 'none') {
            return $fetch();
        }
        if ($s->cache_policy === 'ttl') {
            /** @var list<array{value: string, label: string, parent: string|null, cells: array<string, mixed>}> */
            return Cache::remember($key, $ttl, $fetch);
        }
        $hit = Cache::get($key);
        if (is_array($hit) && isset($hit['at'], $hit['rows'])) {
            if (time() - (int) $hit['at'] > $ttl && Cache::add($key.':refreshing', true, 120)) {
                RefreshExternalSource::dispatch($s->id, $key, $params)->afterResponse();
            }

            return $hit['rows'];
        }
        $rows = $fetch();
        Cache::put($key, ['at' => time(), 'rows' => $rows], now()->addDays(7));

        return $rows;
    }

    /** What changes a REST call (the placeholders it fills): the cache key. @return array<string, mixed> */
    private function restParams(ExternalDataSource $s, array $filters): array
    {
        $template = json_encode([$s->rest_config['query'] ?? [], $s->rest_config['body'] ?? '']).(string) ($s->rest_config['url'] ?? '');

        return ['q' => str_contains($template, '{q}') || str_contains($template, '{{q}}') ? (string) ($filters['q'] ?? '') : null,
            'depends' => str_contains($template, '{depends}') || str_contains($template, '{{depends}}') ? ($filters['depends'] ?? null) : null];
    }

    /** @return list<array{value: string, label: string, parent: string|null, cells: array<string, mixed>}> */
    private function fetchRest(ExternalDataSource $s, array $filters): array
    {
        return array_values(array_map(fn ($r) => $this->map($s, $r), array_slice($this->restRecords($s, $filters), 0, self::MAX_ROWS)));
    }

    /** @return list<mixed> */
    private function restRecords(ExternalDataSource $s, array $filters): array
    {
        $c = (array) $s->rest_config;
        $params = ['q' => (string) ($filters['q'] ?? ''), 'depends' => (string) ($filters['depends'] ?? '')];
        $headers = ['Accept' => 'application/json', 'User-Agent' => 'LowCodeFramework-DataSource/1'];
        foreach ((array) $s->headers as $h) {
            $headers[$h['name']] = $h['value'];
        }
        $creds = (array) $s->credentials;
        $options = [];
        switch ($s->auth_type) {
            case 'api_key':
                $headers[$creds['header']] = $creds['key'];
                break;
            case 'bearer':
                $headers['Authorization'] = 'Bearer '.$creds['token'];
                break;
            case 'basic':
                $options['auth'] = [$creds['username'], $creds['password']];
                break;
            case 'oauth2_client_credentials':
                $headers['Authorization'] = 'Bearer '.$this->oauthToken($s);
                break;
        }
        $query = [];
        foreach ((array) ($c['query'] ?? []) as $p) {
            $query[$p['name']] = strtr((string) $p['value'], ['{q}' => $params['q'], '{depends}' => $params['depends']]);
        }
        $url = strtr((string) $c['url'], ['{q}' => rawurlencode($params['q']), '{depends}' => rawurlencode($params['depends'])]);
        $options['headers'] = $headers;
        if ($query !== []) {
            $options['query'] = $query;
        }
        if (($c['method'] ?? 'GET') === 'POST' && ($c['body'] ?? null) !== null) {
            $options['body'] = self::fillJson((string) $c['body'], $params);
            $options['headers']['Content-Type'] = 'application/json';
        }
        $json = $this->call($s, (string) ($c['method'] ?? 'GET'), $url, $options);
        $records = ($c['records'] ?? '') === '' ? $json : data_get($json, (string) $c['records']);
        if (! is_array($records) || ! array_is_list($records)) {
            throw $this->failed($s, __('integrations.not_a_list'));
        }

        return $records;
    }

    private function oauthToken(ExternalDataSource $s): string
    {
        $cacheKey = 'xds-token:'.$s->id.':'.hash('sha256', (string) $s->updated_at);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $creds = (array) $s->credentials;
        $json = $this->call($s, 'POST', (string) $creds['token_url'], ['form_params' => array_filter(['grant_type' => 'client_credentials', 'client_id' => $creds['client_id'],
            'client_secret' => $creds['client_secret'], 'scope' => $creds['scope'] ?? null])]);
        $token = is_array($json) ? (string) ($json['access_token'] ?? '') : '';
        if ($token === '') {
            throw $this->failed($s, __('integrations.no_token'));
        }
        Cache::put($cacheKey, $token, max(30, (int) ($json['expires_in'] ?? 300) - 30));

        return $token;
    }

    /** @param  array<string, mixed>  $options */
    private function call(ExternalDataSource $s, string $method, string $url, array $options): mixed
    {
        try {
            $response = $this->egress->request($method, $url, $options, 20, self::MAX_BYTES);
        } catch (EgressViolation $e) {
            throw $this->failed($s, $e->getMessage());
        } catch (Throwable $e) {
            throw $this->failed($s, __('integrations.unreachable', ['error' => mb_substr($e->getMessage(), 0, 300)]));
        }
        if ($response->getStatusCode() >= 300) {
            throw $this->failed($s, __('integrations.refused', ['status' => $response->getStatusCode()]));
        }
        $json = json_decode((string) $response->getBody(), true);
        if ($json === null) {
            throw $this->failed($s, __('integrations.not_json'));
        }

        return $json;
    }

    /** @return list<array{value: string, label: string, parent: string|null, cells: array<string, mixed>}> */
    private function queryView(ExternalDataSource $s, array $filters, int $limit): array
    {
        $m = $s->response_mapping;
        $columns = array_values(array_unique(array_filter([$m['value'], $m['label'], $m['parent'] ?? null, ...array_column($m['columns'] ?? [], 'path')])));
        try {
            $q = $this->connection($s)->table((string) $s->db_view)->select($columns);
            $term = trim((string) ($filters['q'] ?? ''));
            if ($term !== '') {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $q->where(static fn ($w) => $w->where($m['label'], 'like', $like)->orWhere($m['value'], 'like', $like));
            }
            if (($filters['depends'] ?? null) !== null && ($m['parent'] ?? null) !== null) {
                $q->where($m['parent'], $filters['depends']);
            }
            if (isset($filters['values'])) {
                $q->whereIn($m['value'], array_slice($filters['values'], 0, 1000));
            }
            foreach ($filters['match'] ?? [] as $col => $want) {
                $path = $col === 'value' ? $m['value'] : (collect($m['columns'] ?? [])->firstWhere('key', $col)['path'] ?? null);
                if ($path !== null) {
                    $q->where($path, $want);
                }
            }
            $rows = $q->orderBy($m['label'])->limit(min($limit, self::MAX_ROWS))->get();
        } catch (Throwable $e) {
            throw $this->failed($s, __('integrations.query_failed', ['error' => mb_substr($e->getMessage(), 0, 300)]));
        }

        return array_values(array_map(fn ($r) => $this->map($s, (array) $r), $rows->all()));
    }

    private function connection(ExternalDataSource $s): Connection
    {
        if (! in_array((string) $s->db_connection, self::connections(), true)) {
            throw $this->failed($s, __('integrations.bad_connection'));
        }

        return DB::connection('external_'.$s->db_connection);
    }

    /** The view's columns, or null when the connection has no such view. @return list<string>|null */
    private function viewColumns(string $connection, string $view): ?array
    {
        try {
            $schema = Schema::connection('external_'.$connection);
            $views = array_map(static fn (array $v) => strtolower((string) (($v['schema'] ?? null) ? $v['schema'].'.'.$v['name'] : $v['name'])), $schema->getViews());
            $names = array_map(static fn (array $v) => strtolower((string) $v['name']), $schema->getViews());
            if (! in_array(strtolower($view), $views, true) && ! in_array(strtolower($view), $names, true)) {
                return null;
            }
            $columns = $schema->getColumnListing($view);

            return $columns === [] ? null : array_values($columns);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{value: string, label: string, parent: string|null, cells: array<string, mixed>} */
    private function map(ExternalDataSource $s, mixed $record): array
    {
        $m = $s->response_mapping;
        $get = static fn (string $path) => is_array($record) ? (array_key_exists($path, $record) ? $record[$path] : data_get($record, $path)) : null;
        $scalar = static fn (mixed $v): ?string => is_scalar($v) ? (string) $v : null;
        $cells = [];
        foreach ($m['columns'] ?? [] as $c) {
            $v = $get($c['path']);
            $cells[$c['key']] = is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        $value = $scalar($get($m['value'])) ?? '';

        return ['value' => mb_substr($value, 0, 255), 'label' => mb_substr($scalar($get($m['label'])) ?? $value, 0, 500),
            'parent' => ($m['parent'] ?? null) === null ? null : $scalar($get($m['parent'])), 'cells' => $cells];
    }

    /**
     * A failure is recorded for the Operations Center (at most once every five
     * minutes per source, so a search typed against a source that is down does
     * not flood the log) and returned for the caller to throw.
     */
    private function failed(ExternalDataSource $s, string $message): SourceFailure
    {
        if (Cache::add('xds-failed:'.$s->id, true, 300)) {
            $this->audit->record('integration.source_failed', 'operations', null, 'external_data_source', $s->id, ['key' => $s->key, 'error' => mb_substr($message, 0, 500)]);
        }

        return new SourceFailure($message);
    }

    private function allowedUrl(string $url): bool
    {
        if (! str_starts_with($url, 'https://')) {
            return false;
        }
        try {
            $this->egress->permits(new Uri(strtr($url, ['{q}' => 'x', '{depends}' => 'x'])));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Fills a JSON body template: {{q}} and {{depends}} become JSON strings. @param  array<string, string>  $values */
    public static function fillJson(string $template, array $values): string
    {
        return (string) preg_replace_callback('/"?\{\{\s*(q|depends)\s*\}\}"?/', static fn (array $m) => (string) json_encode($values[$m[1]] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $template);
    }

    /** The first record as received, with long values cut, to help an admin map it. */
    private static function maskSample(mixed $sample): mixed
    {
        if (is_object($sample)) {
            $sample = (array) $sample;
        }
        if (! is_array($sample)) {
            return is_scalar($sample) ? mb_substr((string) $sample, 0, 200) : null;
        }
        $out = [];
        foreach (array_slice($sample, 0, 40, true) as $k => $v) {
            $out[$k] = is_array($v) || is_object($v) ? self::maskSample($v) : (is_string($v) ? mb_substr($v, 0, 200) : $v);
        }

        return $out;
    }
}
