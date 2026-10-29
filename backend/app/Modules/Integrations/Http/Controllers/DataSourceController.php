<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Integrations\ExternalSources;
use App\Modules\Integrations\Models\ExternalDataSource;
use App\Support\Json\Canonical;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * External data sources (specification §4.33, ADR-0057). Managing them
 * needs "Manage integrations"; form builders may list them to use as an
 * option source, without seeing their settings.
 */
final class DataSourceController extends Controller
{
    public function __construct(private readonly ExternalSources $sources, private readonly Translator $translator, private readonly AuditWriter $audit) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json([
            'data' => ExternalDataSource::query()->orderBy('key')->get()->map(fn (ExternalDataSource $s) => $this->summary($s))->values()->all(),
            'meta' => ['connections' => ExternalSources::connections(), 'auth_fields' => ExternalSources::AUTH_FIELDS, 'secrets' => ExternalSources::SECRETS],
        ]);
    }

    /** Sources a form builder can choose, with their columns (no settings). */
    public function options(): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_forms', 'system.manage_integrations']), 403);

        return response()->json(['data' => ExternalDataSource::query()->where('is_active', true)->orderBy('key')->get()->map(fn (ExternalDataSource $s) => [
            'uuid' => strtolower($s->uuid), 'key' => $s->key, 'label' => $this->label($s), 'cascades' => ($s->response_mapping['parent'] ?? null) !== null,
            'columns' => array_map(fn (array $c) => ['key' => $c['key'], 'label' => $this->translator->pick($c['label'])], $s->response_mapping['columns'] ?? []),
        ])->values()->all()]);
    }

    public function show(string $source): JsonResponse
    {
        Gate::authorize('system.manage_integrations');

        return response()->json(['data' => $this->present($this->find($source))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $d = (array) $request->input('source', []);
        $row = $this->sources->normalize($d, null);
        $name = $this->name($d);
        $s = new ExternalDataSource;
        DB::transaction(function () use ($s, $row, $name): void {
            $s->forceFill($row)->save();
            $s->setTranslations('name', $name);
        });
        $this->audit->record('integration.source_created', 'config', null, 'external_data_source', $s->id, ['key' => $s->key, 'type' => $s->type]);

        return response()->json(['data' => $this->present($s->refresh())], 201);
    }

    public function update(Request $request, string $source): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $s = $this->find($source);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->present($s)['hash'], (string) $request->input('base_hash')), 409, __('integrations.changed_meanwhile'));
        $d = (array) $request->input('source', []);
        $row = $this->sources->normalize($d, $s);
        if ($row['key'] !== $s->key && $this->usedBy($s) !== []) {
            throw ValidationException::withMessages(['key' => __('integrations.key_in_use')]);
        }
        $name = $this->name($d);
        DB::transaction(function () use ($s, $row, $name): void {
            $s->forceFill($row)->save();
            $s->setTranslations('name', $name);
        });
        $this->sources->forget($s);
        $this->audit->record('integration.source_updated', 'config', null, 'external_data_source', $s->id, ['key' => $s->key, 'active' => $s->is_active]);

        return response()->json(['data' => $this->present($s->refresh())]);
    }

    public function destroy(string $source): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $s = $this->find($source);
        $used = $this->usedBy($s);
        if ($used !== []) {
            throw ValidationException::withMessages(['source' => __('integrations.in_use', ['what' => implode(', ', array_slice($used, 0, 5))])]);
        }
        $this->audit->record('integration.source_deleted', 'config', null, 'external_data_source', $s->id, ['key' => $s->key]);
        $s->delete();

        return response()->json(null, 204);
    }

    /** Calls the source now and shows what came back. */
    public function test(string $source): JsonResponse
    {
        Gate::authorize('system.manage_integrations');
        $s = $this->find($source);
        $out = $this->sources->test($s);
        $this->audit->record('integration.source_tested', 'config', null, 'external_data_source', $s->id, ['key' => $s->key, 'ok' => $out['ok']]);

        return response()->json(['data' => $out]);
    }

    /** @param  array<string, mixed>  $d  @return array<string, string> */
    private function name(array $d): array
    {
        $name = [];
        foreach ((array) ($d['name'] ?? []) as $loc => $v) {
            if (is_string($loc) && preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $loc) === 1 && is_string($v) && trim($v) !== '') {
                $name[$loc] = mb_substr(trim($v), 0, 255);
            }
        }
        if ($name === []) {
            throw ValidationException::withMessages(['name' => __('integrations.name_required')]);
        }

        return $name;
    }

    /** Where the source is used: forms whose published or draft fields choose from it, and sync jobs. @return list<string> */
    private function usedBy(ExternalDataSource $s): array
    {
        $uuid = strtolower($s->uuid);
        $forms = DB::table('fields')->join('forms', 'forms.id', '=', 'fields.form_id')->whereNull('fields.archived_at')
            ->where('fields.options_source', 'like', '%'.$uuid.'%')->distinct()->pluck('forms.key')->map(static fn ($k) => (string) $k)->all();
        $panels = DB::table('view_panels')->join('forms', 'forms.id', '=', 'view_panels.form_id')->where('view_panels.config', 'like', '%'.$uuid.'%')->distinct()->pluck('forms.key')->map(static fn ($k) => (string) $k)->all();
        $jobs = DB::table('sync_jobs')->where('external_data_source_id', $s->id)->count();

        return array_values(array_unique([...$forms, ...$panels, ...($jobs > 0 ? [__('integrations.sync_jobs_n', ['n' => $jobs])] : [])]));
    }

    /** @return array<string, mixed> */
    private function present(ExternalDataSource $s): array
    {
        $creds = [];
        foreach (ExternalSources::AUTH_FIELDS[$s->auth_type] ?? [] as $name => $required) {
            $secret = in_array($name, ExternalSources::SECRETS[$s->auth_type] ?? [], true);
            $creds[$name] = $secret ? null : (((array) $s->credentials)[$name] ?? null);
            if ($secret) {
                $creds[$name.'_set'] = isset(((array) $s->credentials)[$name]);
            }
        }
        $doc = [
            'uuid' => strtolower($s->uuid), 'key' => $s->key, 'type' => $s->type, 'name' => (object) $s->translationsFor('name'),
            'rest_config' => $s->rest_config, 'db_connection' => $s->db_connection, 'db_view' => $s->db_view,
            'auth_type' => $s->auth_type, 'credentials' => (object) $creds,
            'headers' => array_map(static fn (array $h) => ['name' => $h['name'], 'value' => null, 'set' => true], (array) $s->headers),
            'response_mapping' => $s->response_mapping, 'cache_policy' => $s->cache_policy, 'cache_ttl_seconds' => $s->cache_ttl_seconds, 'active' => $s->is_active,
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc + ['secrets' => hash('sha256', (string) json_encode([$s->credentials, $s->headers]))]), JSON_UNESCAPED_UNICODE));

        return $doc + $this->summary($s) + ['used_by' => $this->usedBy($s)];
    }

    /** @return array<string, mixed> */
    private function summary(ExternalDataSource $s): array
    {
        return ['uuid' => strtolower($s->uuid), 'key' => $s->key, 'type' => $s->type, 'label' => $this->label($s), 'active' => $s->is_active,
            'last_tested_at' => $s->last_tested_at, 'last_test_status' => $s->last_test_status,
            'target' => $s->type === 'rest' ? parse_url((string) ($s->rest_config['url'] ?? ''), PHP_URL_HOST) : $s->db_connection.' · '.$s->db_view];
    }

    private function label(ExternalDataSource $s): string
    {
        return $this->translator->labelOf($s->translationsFor('name'), $s->key);
    }

    private function find(string $uuid): ExternalDataSource
    {
        $s = Str::isUuid($uuid) ? ExternalDataSource::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($s === null, 404);

        return $s;
    }
}
