<?php

declare(strict_types=1);

namespace App\Modules\Platform\Packages;

use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\NotificationRules;
use App\Modules\Platform\Packages\Parts\ApplicationPart;
use App\Modules\Platform\Packages\Parts\DashboardPart;
use App\Modules\Platform\Packages\Parts\DocumentTemplatePart;
use App\Modules\Platform\Packages\Parts\DownloadProfilePart;
use App\Modules\Platform\Packages\Parts\EmailTemplatePart;
use App\Modules\Platform\Packages\Parts\FormPart;
use App\Modules\Platform\Packages\Parts\ReportPart;
use App\Modules\Schema\Execution\SchemaReconciler;
use App\Support\Json\Canonical;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Configuration packages and the environment drift report (specification
 * §4.10, §4.22, architecture §13.6, ADR-0052).
 *
 * - A package is a signed-by-checksum JSON file of objects (applications,
 *   forms with their workflow, notification rules and access, email and
 *   document templates, shared download profiles), each with its uuid, key,
 *   content and the uuids it depends on. Roles, departments and people the
 *   content names travel as keys (role key, department code, e-mail) and are
 *   matched by key on import.
 * - Comparing a package with this environment gives a drift report: per
 *   object new / same / changed / changed in both; dependencies that exist
 *   nowhere; and, per form, physical tables that differ from this
 *   environment's own metadata.
 * - An import is refused while the result would be ambiguous: an object
 *   changed both here and in the package since it was last imported, a form
 *   here with unpublished changes, or tables here that drift from their
 *   metadata. Other conflicts are resolved per object: take the package's,
 *   keep this one, or import under another key.
 * - Applying runs object by object in dependency order; forms are published
 *   through the normal migration plan. Each object's hash is recorded so the
 *   next import can tell which side changed.
 */
final class PackageService
{
    public const FORMAT = 'lcf-package/1';

    public const MAX_BYTES = 20 * 1024 * 1024;

    /** Apply order: what others depend on first. Forms' notification rules are saved after the templates. */
    private const ORDER = ['application', 'form', 'email_template', 'document_template', 'download_profile', 'report', 'dashboard'];

    /** @var array<string, PackagePart> */
    private array $parts;

    public function __construct(
        ApplicationPart $applications,
        FormPart $forms,
        EmailTemplatePart $emails,
        DocumentTemplatePart $documents,
        DownloadProfilePart $downloads,
        ReportPart $reports,
        DashboardPart $dashboards,
        private readonly SchemaReconciler $reconciler,
    ) {
        foreach ([$applications, $forms, $emails, $documents, $downloads, $reports, $dashboards] as $part) {
            $this->parts[$part->type()] = $part;
        }
    }

    /** @return array<string, list<array<string, mixed>>> what can be exported, by kind */
    public function catalog(): array
    {
        $out = [];
        foreach (self::ORDER as $type) {
            $out[$type] = $this->parts[$type]->catalog();
        }

        return $out;
    }

    // ── Export ───────────────────────────────────────────────────────────

    /**
     * A package of the chosen objects and everything they depend on.
     *
     * @param  list<array{type: string, uuid: string}>  $chosen
     * @return array<string, mixed>
     */
    public function export(string $name, array $chosen, User $user): array
    {
        $objects = [];
        $queue = array_map(static fn ($c) => [strtolower($c['uuid']), $c['type']], $chosen);
        while ($queue !== []) {
            [$uuid, $type] = array_shift($queue);
            if (isset($objects[$uuid])) {
                continue;
            }
            $type ??= $this->typeOf($uuid);
            $part = $type === null ? null : ($this->parts[$type] ?? null);
            $object = $part?->export($uuid);
            if ($object === null) {
                throw ValidationException::withMessages(['objects' => __('packages.unknown_object', ['uuid' => $uuid])]);
            }
            $objects[$uuid] = ['type' => $type] + $object;
            foreach ($object['dependencies'] as $dep) {
                $queue[] = [$dep, null];
            }
        }
        // As the file will carry it: empty maps and lists read back alike, so the hashes match on import.
        $objects = self::plain(array_values($objects));
        usort($objects, static fn ($a, $b) => array_search($a['type'], self::ORDER, true) <=> array_search($b['type'], self::ORDER, true) ?: strcmp($a['key'], $b['key']));
        foreach ($objects as &$o) {
            $o['hash'] = self::hash($o['content']);
        }
        unset($o);
        $package = [
            'format' => self::FORMAT,
            'name' => mb_substr($name, 0, 255),
            'source' => ['environment' => (string) config('app.name'), 'url' => (string) config('app.url'), 'exportedAt' => Carbon::now('UTC')->toIso8601ZuluString(), 'exportedBy' => (string) $user->name],
            'subjects' => $this->subjects($objects),
            'objects' => $objects,
        ];
        $package['checksum'] = self::checksum($package);

        return $package;
    }

    // ── Import ───────────────────────────────────────────────────────────

    /**
     * Reads an uploaded package and checks its format and checksum.
     *
     * @return array<string, mixed>
     */
    public function read(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => __('packages.too_large')]);
        }
        $package = json_decode($json, true);
        if (! is_array($package) || ($package['format'] ?? null) !== self::FORMAT || ! is_array($package['objects'] ?? null)) {
            throw ValidationException::withMessages(['file' => __('packages.not_a_package')]);
        }
        if (! is_string($package['checksum'] ?? null) || ! hash_equals(self::checksum($package), $package['checksum'])) {
            throw ValidationException::withMessages(['file' => __('packages.checksum_mismatch')]);
        }
        foreach ($package['objects'] as $o) {
            if (! is_array($o) || ! isset($this->parts[$o['type'] ?? '']) || ! is_string($o['uuid'] ?? null) || ! Str::isUuid($o['uuid'])
                || ! is_string($o['key'] ?? null) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $o['key']) !== 1 || ! is_array($o['content'] ?? null)) {
                throw ValidationException::withMessages(['file' => __('packages.bad_object')]);
            }
        }

        return $package;
    }

    /**
     * Compares a package with this environment (the drift report).
     *
     * @param  array<string, mixed>  $package
     * @return array{objects: list<array<string, mixed>>, schema: list<array<string, mixed>>, ambiguous: bool, conflicts: list<string>, missingSubjects: list<array<string, string>>}
     */
    public function analyze(array $package): array
    {
        [$package, $missingSubjects] = $this->localize($package);
        $inPackage = array_flip(array_column($package['objects'], 'uuid'));
        $rows = [];
        $schema = [];
        $ambiguous = false;
        $conflicts = [];
        foreach ($package['objects'] as $o) {
            $part = $this->parts[$o['type']];
            $row = ['type' => $o['type'], 'uuid' => $o['uuid'], 'key' => $o['key'], 'label' => (string) ($o['label'] ?? Translator::humanize((string) $o['key'])), 'state' => 'new', 'differences' => [], 'missing' => []];
            foreach ($o['dependencies'] ?? [] as $dep) {
                if (! isset($inPackage[$dep]) && $this->typeOf($dep) === null) {
                    $row['missing'][] = $dep;
                }
            }
            if (! $part->exists($o['uuid'])) {
                $row['state'] = $part->keyTaken($o['key'], $o['uuid']) ? 'key_taken' : 'new';
            } elseif ($o['type'] === 'form' && Form::query()->where('uuid', $o['uuid'])->whereNull('current_version_id')->exists()) {
                // Created by an earlier import of this package that did not get as far as publishing it.
                $row['state'] = 'new';
            } else {
                try {
                    $here = $part->export($o['uuid']);
                } catch (ValidationException $e) {
                    $here = null;
                    $row['state'] = 'unpublished_here';
                    $row['differences'][] = (string) collect($e->errors())->flatten()->first();
                }
                if ($here !== null) {
                    $here['content'] = self::plain($here['content']);
                    $hereHash = self::hash($here['content']);
                    $pkgHash = self::hash($o['content']);
                    [$base, $importedAs] = $this->lastImported($o['uuid']);
                    // An object here keeps its own key; one imported under another key is remembered as such.
                    $row['localKey'] = $here['key'];
                    $sameKey = $here['key'] === $o['key'] || $here['key'] === $importedAs;
                    $row['state'] = match (true) {
                        $hereHash === $pkgHash && $sameKey => 'same',
                        $base !== null && $hereHash !== $base && $pkgHash !== $base => 'changed_in_both',
                        $base !== null && $hereHash !== $base && $pkgHash === $base => 'changed_here',
                        default => 'changed',
                    };
                    $row['differences'] = self::differences($here['content'], $o['content']);
                    if (! $sameKey) {
                        $row['differences'][] = 'key';
                    }
                }
                if ($o['type'] === 'form' && ($form = Form::query()->where('uuid', $o['uuid'])->first()) !== null) {
                    $diffs = $this->reconciler->differences($form);
                    if ($diffs !== []) {
                        $schema[] = ['form' => $o['uuid'], 'key' => $o['key'], 'table' => $form->table_name, 'differences' => $diffs];
                        $row['state'] = 'schema_drift';
                    }
                }
            }
            if (in_array($row['state'], ['changed_in_both', 'unpublished_here', 'schema_drift'], true)) {
                $ambiguous = true;
                $conflicts[] = $o['uuid'];
            }
            $rows[] = $row;
        }

        return ['objects' => $rows, 'schema' => $schema, 'ambiguous' => $ambiguous, 'conflicts' => $conflicts, 'missingSubjects' => $missingSubjects];
    }

    /**
     * Stores the drift report of a package compared with this environment.
     *
     * @param  array<string, mixed>  $package
     * @param  array<string, mixed>  $report
     */
    public function storeReport(array $package, array $report, User $user): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');

        return (int) DB::table('environment_drift_reports')->insertGetId([
            'uuid' => (string) Str::uuid7(), 'organization_id' => $user->organization_id, 'created_at' => $now, 'updated_at' => $now, 'created_by' => $user->id, 'updated_by' => $user->id,
            'source_label' => mb_substr((string) ($package['source']['environment'] ?? '?').' · '.($package['name'] ?? ''), 0, 128),
            'target_label' => mb_substr((string) config('app.name'), 0, 128),
            'source_manifest_hash' => (string) $package['checksum'],
            'metadata_differences' => json_encode($report['objects'], JSON_UNESCAPED_UNICODE),
            'schema_differences' => json_encode($report['schema'], JSON_UNESCAPED_UNICODE),
            'is_ambiguous' => $report['ambiguous'],
            'conflicting_objects' => $report['conflicts'] === [] ? null : json_encode($report['conflicts']),
        ]);
    }

    /**
     * The choices needed before applying: each conflict resolved, nothing ambiguous.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, array{action?: string, key?: string|null}>  $choices  by object uuid
     * @return array<string, array{action: string, key: string}>
     */
    public function resolve(array $report, array $choices): array
    {
        if ($report['ambiguous']) {
            throw ValidationException::withMessages(['package' => __('packages.ambiguous')]);
        }
        $out = [];
        $errors = [];
        foreach ($report['objects'] as $row) {
            $choice = $choices[$row['uuid']] ?? null;
            $action = $choice['action'] ?? match ($row['state']) {
                'same', 'changed_here' => 'keep',
                'new' => 'take',
                default => null,
            };
            if ($row['missing'] !== [] && $action !== 'keep') {
                $errors["choices.{$row['uuid']}"][] = __('packages.missing_dependency', ['name' => $row['label']]);

                continue;
            }
            if (! in_array($action, ['take', 'keep', 'rename'], true)) {
                $errors["choices.{$row['uuid']}"][] = __('packages.choose', ['name' => $row['label']]);

                continue;
            }
            $key = $row['localKey'] ?? $row['key'];
            if ($action === 'rename') {
                $key = (string) ($choice['key'] ?? '');
                if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1 || $this->parts[$row['type']]->keyTaken($key, $row['uuid'])) {
                    $errors["choices.{$row['uuid']}"][] = __('packages.bad_key', ['name' => $row['label']]);

                    continue;
                }
                if ($this->parts[$row['type']]->exists($row['uuid'])) {
                    // A renamed object keeps its identity: only something not here yet can take another key.
                    $errors["choices.{$row['uuid']}"][] = __('packages.rename_new_only', ['name' => $row['label']]);

                    continue;
                }
            } elseif ($action === 'take' && $row['state'] === 'key_taken') {
                $errors["choices.{$row['uuid']}"][] = __('packages.key_taken', ['name' => $row['label']]);

                continue;
            }
            $out[$row['uuid']] = ['action' => $action, 'key' => $key];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * Applies a resolved package, object by object in dependency order.
     *
     * @param  array<string, mixed>  $package
     * @param  array<string, array{action: string, key: string}>  $resolution
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, results: list<array<string, mixed>>, applied: array<string, string>, keys: array<string, string>}
     */
    public function apply(array $package, array $resolution, array $options, User $user): array
    {
        [$package] = $this->localize($package);
        $objects = $this->ordered($package['objects']);
        $results = [];
        $applied = [];
        $keys = [];
        $ok = true;
        $deferred = [];
        foreach ($objects as $o) {
            $choice = $resolution[$o['uuid']] ?? ['action' => 'keep', 'key' => $o['key']];
            if ($choice['action'] === 'keep') {
                $results[] = ['uuid' => $o['uuid'], 'type' => $o['type'], 'key' => $o['key'], 'outcome' => 'kept', 'notes' => []];

                continue;
            }
            $object = ['key' => $choice['key']] + $o;
            if (! $ok) {
                $results[] = ['uuid' => $o['uuid'], 'type' => $o['type'], 'key' => $object['key'], 'outcome' => 'not_run', 'notes' => []];

                continue;
            }
            try {
                if ($o['type'] === 'form') {
                    // Notification rules name templates the package may bring later: they are saved after them.
                    $deferred[] = $object;
                    $object['content']['notifications'] = [];
                }
                $notes = $this->parts[$o['type']]->apply($object, $user, $options);
                $applied[$o['uuid']] = self::hash($o['content']);
                $keys[$o['uuid']] = $object['key'];
                $results[] = ['uuid' => $o['uuid'], 'type' => $o['type'], 'key' => $object['key'], 'outcome' => 'applied', 'notes' => $notes];
            } catch (Throwable $e) {
                $ok = false;
                $results[] = ['uuid' => $o['uuid'], 'type' => $o['type'], 'key' => $object['key'], 'outcome' => 'failed', 'notes' => [self::message($e)]];
            }
        }
        if ($ok) {
            foreach ($deferred as $object) {
                try {
                    $form = Form::query()->where('uuid', $object['uuid'])->firstOrFail();
                    app(NotificationRules::class)->save($form, array_values((array) $object['content']['notifications']), $user->id);
                } catch (Throwable $e) {
                    $ok = false;
                    foreach ($results as &$r) {
                        if ($r['uuid'] === $object['uuid']) {
                            $r['outcome'] = 'failed';
                            $r['notes'][] = self::message($e);
                        }
                    }
                    unset($r);
                }
            }
        }

        return ['ok' => $ok, 'results' => $results, 'applied' => $applied, 'keys' => $keys];
    }

    public static function hash(array $content): string
    {
        return hash('sha256', (string) json_encode(Canonical::of(self::plain($content)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Content as a package file carries it (empty maps and lists read back alike).
     *
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    private static function plain(array $content): array
    {
        return (array) json_decode((string) json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
    }

    /** @param  array<string, mixed>  $package */
    public static function checksum(array $package): string
    {
        return self::hash(array_intersect_key($package, array_flip(['format', 'name', 'source', 'subjects', 'objects'])));
    }

    /** Which kind of object a uuid names here, if any. */
    private function typeOf(string $uuid): ?string
    {
        foreach (self::ORDER as $type) {
            if ($this->parts[$type]->exists($uuid)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The hash an object had when it was last imported here or exported from here, and the key it carried then.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function lastImported(string $uuid): array
    {
        // The last time this environment and a package agreed on the object: an applied import, or an export from here.
        $rows = DB::table('config_packages')
            ->where(static fn ($q) => $q->where(static fn ($w) => $w->where('direction', 'import')->where('status', 'applied'))
                ->orWhere(static fn ($w) => $w->where('direction', 'export')->whereNotNull('resolution')))
            ->orderByDesc('applied_at')->orderByDesc('id')->limit(200)->pluck('resolution');
        foreach ($rows as $json) {
            $resolution = json_decode((string) $json, true) ?: [];
            if (isset($resolution['applied'][$uuid])) {
                return [(string) $resolution['applied'][$uuid], isset($resolution['keys'][$uuid]) ? (string) $resolution['keys'][$uuid] : null];
            }
        }

        return [null, null];
    }

    /**
     * Roles, departments and people the objects name, by key.
     *
     * @param  list<array<string, mixed>>  $objects
     * @return array<string, array{type: string, key: string}>
     */
    private function subjects(array $objects): array
    {
        $uuids = [];
        array_walk_recursive($objects, static function ($v) use (&$uuids): void {
            if (is_string($v) && Str::isUuid($v)) {
                $uuids[strtolower($v)] = true;
            }
        });
        $uuids = array_keys($uuids);
        $out = [];
        foreach ([['role', 'roles', 'key'], ['department', 'departments', 'code'], ['user', 'users', 'email']] as [$type, $table, $column]) {
            foreach (array_chunk($uuids, 500) as $chunk) {
                foreach (DB::table($table)->whereIn('uuid', $chunk)->get(['uuid', $column]) as $r) {
                    $out[strtolower((string) $r->uuid)] = ['type' => $type, 'key' => strtolower((string) $r->{$column})];
                }
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * The package with the roles, departments and people it names replaced by
     * the ones with the same key here; the ones missing here are listed.
     *
     * @param  array<string, mixed>  $package
     * @return array{0: array<string, mixed>, 1: list<array<string, string>>}
     */
    private function localize(array $package): array
    {
        $map = [];
        $missing = [];
        foreach ((array) ($package['subjects'] ?? []) as $uuid => $s) {
            [$table, $column] = ['role' => ['roles', 'key'], 'department' => ['departments', 'code'], 'user' => ['users', 'email']][$s['type'] ?? ''] ?? [null, null];
            $here = $table === null ? null : DB::table($table)->whereRaw('LOWER('.DB::getQueryGrammar()->wrap($column).') = ?', [strtolower((string) $s['key'])])->value('uuid');
            if ($here === null) {
                $missing[] = ['type' => (string) ($s['type'] ?? ''), 'key' => (string) ($s['key'] ?? '')];
            } elseif (strtolower((string) $here) !== strtolower((string) $uuid)) {
                $map[strtolower((string) $uuid)] = strtolower((string) $here);
            }
        }
        if ($map !== []) {
            $walk = static function (mixed $v) use (&$walk, $map): mixed {
                if (is_string($v)) {
                    return $map[strtolower($v)] ?? $v;
                }
                if (is_array($v)) {
                    return array_map($walk, $v);
                }

                return $v;
            };
            $package['objects'] = array_map(static fn (array $o) => ['content' => $walk($o['content'])] + $o, $package['objects']);
        }

        return [$package, $missing];
    }

    /**
     * Objects in apply order: by kind, and forms after the forms their relations point to.
     *
     * @param  list<array<string, mixed>>  $objects
     * @return list<array<string, mixed>>
     */
    private function ordered(array $objects): array
    {
        $forms = array_values(array_filter($objects, static fn ($o) => $o['type'] === 'form'));
        $sortedForms = [];
        $visiting = [];
        $byUuid = array_column($forms, null, 'uuid');
        $visit = function (array $f) use (&$visit, &$sortedForms, &$visiting, $byUuid): void {
            if (isset($sortedForms[$f['uuid']]) || isset($visiting[$f['uuid']])) {
                return;
            }
            $visiting[$f['uuid']] = true;
            foreach ($f['dependencies'] ?? [] as $dep) {
                if (isset($byUuid[$dep])) {
                    $visit($byUuid[$dep]);
                }
            }
            $sortedForms[$f['uuid']] = $f;
        };
        foreach ($forms as $f) {
            $visit($f);
        }
        $out = [];
        foreach (self::ORDER as $type) {
            $list = $type === 'form' ? array_values($sortedForms) : array_values(array_filter($objects, static fn ($o) => $o['type'] === $type));
            array_push($out, ...$list);
        }

        return $out;
    }

    /**
     * Top-level parts of the content that differ (for forms, the elements by key).
     *
     * @param  array<string, mixed>  $here
     * @param  array<string, mixed>  $package
     * @return list<string>
     */
    private static function differences(array $here, array $package): array
    {
        $out = [];
        foreach (array_unique([...array_keys($here), ...array_keys($package)]) as $k) {
            if (Canonical::same($here[$k] ?? null, $package[$k] ?? null)) {
                continue;
            }
            if ($k === 'document' && is_array($here[$k] ?? null) && is_array($package[$k] ?? null)) {
                foreach (['form', 'groups', 'fields', 'relations', 'conditions'] as $part) {
                    if ($part === 'form') {
                        if (! Canonical::same($here[$k]['form'] ?? null, $package[$k]['form'] ?? null)) {
                            $out[] = 'form';
                        }

                        continue;
                    }
                    $a = array_column((array) ($here[$k][$part] ?? []), null, 'uuid');
                    $b = array_column((array) ($package[$k][$part] ?? []), null, 'uuid');
                    foreach (array_diff_key($b, $a) as $x) {
                        $out[] = "{$part}+".($x['key'] ?? $x['uuid']);
                    }
                    foreach (array_diff_key($a, $b) as $x) {
                        $out[] = "{$part}-".($x['key'] ?? $x['uuid']);
                    }
                    foreach (array_intersect_key($a, $b) as $uuid => $x) {
                        if (! Canonical::same($x, $b[$uuid])) {
                            $out[] = "{$part}~".($x['key'] ?? $uuid);
                        }
                    }
                }

                continue;
            }
            $out[] = (string) $k;
        }

        return $out;
    }

    private static function message(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return implode(' ', array_map('strval', collect($e->errors())->flatten()->all()));
        }

        return mb_substr($e->getMessage(), 0, 1000);
    }
}
