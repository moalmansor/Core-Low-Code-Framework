<?php

declare(strict_types=1);

namespace App\Modules\Records\Templating;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Exchange\SheetCodec;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordStore;
use App\Modules\Records\Runtime\References;
use App\Modules\Records\Runtime\RuleRuntime;
use App\Modules\Views\RelationPaths;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The values of a record as a template shows them (architecture §19.14),
 * shared by email templates and document templates. Placeholders are
 * references of the expression language: `{{ field }}`, `{{ customer.name }}`
 * (a relation path of up to four hops), or a system value such as
 * `{{ @record_number }}`. A value is shown only when every viewer of the
 * rendering may see it: a field hidden from any of them reads as empty,
 * linked records only within each viewer's record scope, and a conditional
 * block only when its condition holds for each of them. Everything returned
 * is plain text; escaping belongs to the writer of each output format.
 */
final class TemplateValues
{
    /** The product's default primary colour (frontend/src/theme/tokens.css), for an unbranded installation. */
    public const DEFAULT_ACCENT = '#1a6fd4';

    public const SYSTEM = ['@record_number', '@status', '@created_at', '@updated_at', '@created_by', '@updated_by', '@assignee', '@user', '@link', '@form', '@recipient', '@today', '@now', '@app'];

    /** `{{ path }}` or `{{ path | format | format }}` (ValueFormat). */
    public const TOKEN = '/\{\{\s*([@a-z0-9_.]+)\s*((?:\|\s*[a-z]+\s*(?::\s*[A-Za-z0-9_]+\s*)?)*)\}\}/i';

    private const PATH = '/^(@[a-z_]+|[a-z][a-z0-9_]{0,47}(\.[a-z][a-z0-9_]{0,47}){0,3}(\.@[a-z_]+)?)$/';

    /** Rows of a repeater table shown at most. */
    public const MAX_ROWS = 200;

    public function __construct(
        private readonly Translator $translator,
        private readonly RelationPaths $paths,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly RecordStore $store,
        private readonly RuleRuntime $rules,
        private readonly SheetCodec $codec,
        private readonly References $refs,
        private readonly SettingsService $settings,
        private readonly ValueFormat $format,
        private readonly AccessResolver $access,
        private readonly RecordScope $scope,
        private readonly FormRuntimes $runtimes,
    ) {}

    /** @return list<string> the placeholders of a text, as written */
    public static function tokens(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /** @return list<string> the format specs a text's placeholders carry (empty ones left out) */
    public static function specs(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $m);

        return array_values(array_unique(array_filter(array_map(static fn (string $s) => trim($s), $m[2]), static fn (string $s) => $s !== '')));
    }

    /**
     * A field list entry or placeholder split into its path and format spec:
     * `amount|currency:SAR` gives `['amount', 'currency:SAR']`.
     *
     * @return array{0: string, 1: string}
     */
    public static function split(string $entry): array
    {
        $at = strpos($entry, '|');

        return $at === false ? [strtolower(trim($entry)), ''] : [strtolower(trim(substr($entry, 0, $at))), trim(substr($entry, $at + 1))];
    }

    /** Whether a placeholder is well formed (its target is checked against the form separately). */
    public static function wellFormed(string $token): bool
    {
        return preg_match(self::PATH, $token) === 1 && (! str_starts_with($token, '@') || in_array($token, self::SYSTEM, true));
    }

    /** Whether a placeholder names a system value or, for a form's template, a field or relation path of that form. */
    public function pathOk(string $token, ?FormRuntime $rt): bool
    {
        if (! self::wellFormed($token)) {
            return false;
        }
        if (str_starts_with($token, '@')) {
            return true;
        }

        return $rt !== null && $this->paths->resolve($rt, explode('.', $token)) !== null;
    }

    /** The repeater group a key names, or null. */
    public function repeater(?FormRuntime $rt, string $group): ?string
    {
        foreach ($rt === null ? [] : $rt->repeaters as $uuid => $rep) {
            if ($rep['group']['key'] === $group) {
                return $uuid;
            }
        }

        return null;
    }

    /** @param  list<User>  $viewers */
    public function context(?FormRuntime $rt, ?int $recordId, array $viewers, string $locale, ?string $recipientName = null, ?string $actorName = null): RenderContext
    {
        return new RenderContext($rt, $recordId, $viewers, $locale, $recipientName, $actorName);
    }

    /** Whether a block's condition holds for every viewer (a block without one always shows). @param  array<string, mixed>  $block */
    public function shown(array $block, RenderContext $ctx): bool
    {
        $ast = $block['condition'] ?? null;
        if (! is_array($ast)) {
            return true;
        }
        if ($ctx->rt === null || $ctx->recordId === null || $ctx->viewers === []) {
            return false;
        }
        foreach ($ctx->viewers as $i => $viewer) {
            if (! $this->rules->holds($ctx->rt, $ast, $this->visibleValues($ctx, $i), $viewer, 'view')) {
                return false;
            }
        }

        return true;
    }

    /**
     * A template text with its placeholders filled, as runs of plain text.
     * `**bold**` in the template is the only markup: it is read from the
     * template, never from filled values, and a bold run that fills to
     * nothing is dropped with its markers.
     *
     * @return list<array{text: string, bold: bool}>
     */
    public function runs(string $source, RenderContext $ctx): array
    {
        $out = [];
        foreach (preg_split('/(\*\*.+?\*\*)/su', $source, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $bold = strlen($part) > 4 && str_starts_with($part, '**') && str_ends_with($part, '**');
            $text = $this->fill($bold ? substr($part, 2, -2) : $part, $ctx);
            if ($text !== '') {
                $out[] = ['text' => $text, 'bold' => $bold && trim($text) !== ''];
            }
        }

        return $out;
    }

    /** A template text with its placeholders filled and its bold markers removed. */
    public function plain(string $source, RenderContext $ctx): string
    {
        return implode('', array_map(static fn (array $r) => $r['text'], $this->runs($source, $ctx)));
    }

    /** A text with its placeholders filled, markup left as written. */
    public function fill(string $source, RenderContext $ctx): string
    {
        return (string) preg_replace_callback(self::TOKEN, fn (array $m) => $this->formatted(strtolower($m[1]), trim($m[2]), $ctx), $source);
    }

    /** A placeholder's text with its formats applied (ValueFormat), '' whenever the plain text would be. */
    public function formatted(string $token, string $spec, RenderContext $ctx): string
    {
        $display = $this->value($token, $ctx);
        $formats = $spec === '' ? [] : ValueFormat::parse(ltrim($spec, '| '));
        if ($display === '' || $formats === null || $formats === []) {
            return $display;
        }

        return $this->format->apply($this->raw($token, $ctx), $display, $formats, $ctx->locale);
    }

    /** The stored value behind a placeholder, for formatting what is already known to be visible. */
    private function raw(string $token, RenderContext $ctx): mixed
    {
        $rt = $ctx->rt;
        if ($token === '@today') {
            return Carbon::now((string) $this->settings->get('formats', 'timezone'))->format('Y-m-d');
        }
        if ($token === '@now') {
            return Carbon::now('UTC')->format('Y-m-d H:i:s');
        }
        if ($rt === null || $ctx->recordId === null) {
            return null;
        }
        if (in_array($token, ['@created_at', '@updated_at'], true)) {
            return DB::table($rt->table)->where('id', $ctx->recordId)->value(substr($token, 1));
        }
        if (str_starts_with($token, '@') || str_contains($token, '@')) {
            return null;
        }

        return $this->paths->rawValues($rt, explode('.', $token), [$ctx->recordId])[$ctx->recordId] ?? null;
    }

    /** A URL with its placeholders filled and encoded. */
    public function url(string $source, RenderContext $ctx): string
    {
        return (string) preg_replace_callback(self::TOKEN, fn (array $m) => rawurlencode($this->value(strtolower($m[1]), $ctx)), $source);
    }

    /**
     * Label and value of each path every viewer may see; paths hidden from any of them are left out.
     *
     * @param  list<string>  $paths
     * @return list<array{label: string, value: string}>
     */
    public function fieldRows(array $paths, RenderContext $ctx): array
    {
        if ($ctx->rt === null || $ctx->recordId === null || $ctx->viewers === []) {
            return [];
        }
        $rows = [];
        foreach ($paths as $entry) {
            [$path, $spec] = self::split($entry);
            $segments = explode('.', $path);
            $r = null;
            foreach ($ctx->viewers as $viewer) {
                $r = $this->paths->resolve($ctx->rt, $segments, $viewer);
                if ($r === null) {
                    break;
                }
            }
            if ($r === null) {
                continue;
            }
            $label = $r['field'] === null
                ? __('views.system_'.$r['system'])
                : $this->translator->labelOf($r['field']['i18n']['label'] ?? null, (string) $r['field']['key']);
            $rows[] = ['label' => $label, 'value' => $this->formatted($path, $spec, $ctx)];
        }

        return $rows;
    }

    /**
     * A repeater's rows with the columns every viewer may see, or null when
     * the repeater is hidden from any of them or has no visible column.
     *
     * @param  list<string>  $columns  column keys, in order
     * @return array{head: list<string>, keys: list<string>, rows: list<list<string>>}|null
     */
    public function table(string $group, array $columns, RenderContext $ctx, array $formats = []): ?array
    {
        $rt = $ctx->rt;
        if ($rt === null || $ctx->recordId === null || $ctx->viewers === []) {
            return null;
        }
        $repUuid = $this->repeater($rt, $group);
        $levels = $this->levels($ctx);
        if ($repUuid === null || ($levels[$repUuid] ?? 'editable') === 'hidden') {
            return null;
        }
        $byKey = [];
        foreach ($rt->rowFields($repUuid) as $f) {
            $byKey[$f['key']] = $f;
        }
        $fields = array_values(array_filter(array_map(static fn ($k) => $byKey[$k] ?? null, $columns), static fn ($f) => $f !== null && ($levels[$f['uuid']] ?? 'editable') !== 'hidden'));
        if ($fields === []) {
            return null;
        }
        $record = $this->store->findById($rt, $ctx->recordId);
        $rows = array_slice(array_values((array) ($record['values'][$rt->repeaters[$repUuid]['group']['key']] ?? [])), 0, self::MAX_ROWS);
        $titles = [];
        foreach ($fields as $f) {
            if ($rt->targetTable($f) !== null) {
                $uuids = [];
                foreach ($rows as $row) {
                    $uuids = [...$uuids, ...array_filter((array) ($row[$f['key']] ?? []), 'is_string')];
                }
                $titles[$f['uuid']] = $this->refs->titles($rt, $f, array_values(array_unique($uuids)));
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($fields as $f) {
                $raw = ((array) $row)[$f['key']] ?? null;
                $cell = $this->codec->toCell($rt, $f, $raw, $titles[$f['uuid']] ?? [], []);
                $text = is_bool($cell) ? ($cell ? '✓' : '') : (string) ($cell ?? '');
                $parsed = ValueFormat::parse((string) ($formats[$f['key']] ?? ''));
                $cells[] = $parsed === null || $parsed === [] ? $text : $this->format->apply($raw, $text, $parsed, $ctx->locale);
            }
            $out[] = $cells;
        }

        return [
            'head' => array_map(fn (array $f) => $this->translator->labelOf($f['i18n']['label'] ?? null, (string) $f['key']), $fields),
            'keys' => array_map(static fn (array $f) => (string) $f['key'], $fields),
            'rows' => $out,
        ];
    }

    /**
     * Records of another form that link to this one (child records: the
     * lines of an order, the visits of a case), with chosen columns. Only
     * records every viewer may see are listed, and only columns every viewer
     * may see; a column may carry formats (`amount|currency:SAR`).
     *
     * @param  list<string>  $columns  paths in the source form, each optionally with formats
     * @return array{head: list<string>, rows: list<list<string>>}|null
     */
    public function related(string $sourceUuid, string $via, array $columns, int $limit, RenderContext $ctx): ?array
    {
        $rt = $ctx->rt;
        $source = $sourceUuid === '' ? null : $this->runtimes->forUuid($sourceUuid);
        if ($rt === null || $source === null || $ctx->recordId === null || $ctx->viewers === []) {
            return null;
        }
        $viaField = $source->fields[$source->keys[$via] ?? ''] ?? null;
        $col = $viaField === null ? null : $source->column($viaField['uuid']);
        if ($col === null || $source->targetFormUuid($viaField) !== $rt->form->uuid) {
            return null;
        }
        $q = DB::table($source->table)->whereNull('deleted_at')->where($col['name'], $ctx->recordId);
        foreach ($ctx->viewers as $viewer) {
            if (! $this->access->allows($viewer, "form.{$source->form->uuid}.view") && $source->form->kind !== 'collection') {
                return null;
            }
            $this->scope->apply($q, $source, $viewer, 'view');
        }
        $ids = $q->orderBy('id')->limit(max(1, min(self::MAX_ROWS, $limit)))->pluck('id')->map(static fn ($v) => (int) $v)->all();
        $head = [];
        $cells = [];
        foreach ($columns as $entry) {
            [$path, $spec] = self::split($entry);
            $segments = explode('.', $path);
            $r = null;
            foreach ($ctx->viewers as $viewer) {
                $r = $this->paths->resolve($source, $segments, $viewer);
                if ($r === null) {
                    break;
                }
            }
            if ($r === null) {
                continue;
            }
            $head[] = $r['field'] === null ? __('views.system_'.$r['system']) : $this->translator->labelOf($r['field']['i18n']['label'] ?? null, (string) $r['field']['key']);
            // What every viewer sees alike; a value any of them would see differently is left empty.
            $shown = null;
            foreach ($ctx->viewers as $viewer) {
                $mine = $this->paths->values($source, $segments, $ids, $viewer);
                foreach ($ids as $id) {
                    $v = self::flatten($mine[$id] ?? null);
                    $shown[$id] = $shown === null || ! array_key_exists($id, $shown) || $shown[$id] === $v ? $v : '';
                }
            }
            $parsed = ValueFormat::parse($spec) ?? [];
            $raw = $parsed === [] || $r['field'] === null ? [] : $this->paths->rawValues($source, $segments, $ids);
            $values = [];
            foreach ($ids as $id) {
                $text = (string) ($shown[$id] ?? '');
                $values[] = $parsed === [] ? $text : $this->format->apply($raw[$id] ?? null, $text, $parsed, $ctx->locale);
            }
            $cells[] = $values;
        }
        if ($head === []) {
            return null;
        }
        $rows = [];
        foreach (array_keys($ids) as $i) {
            $rows[] = array_map(static fn (array $c) => $c[$i] ?? '', $cells);
        }

        return ['head' => $head, 'rows' => $rows];
    }

    /** The display text of a placeholder for this rendering ('' when unknown, hidden or empty). */
    public function value(string $token, RenderContext $ctx): string
    {
        if (array_key_exists($token, $ctx->cache)) {
            return $ctx->cache[$token];
        }
        $rt = $ctx->rt;
        $value = match (true) {
            $token === '@link' => $rt !== null && $ctx->recordId !== null
                ? rtrim((string) config('app.url'), '/').'/app/'.$rt->form->uuid.'/'.strtolower((string) DB::table($rt->table)->where('id', $ctx->recordId)->value('uuid'))
                : rtrim((string) config('app.url'), '/'),
            $token === '@form' => $rt === null ? '' : $this->translator->labelOf($rt->form->translationsFor('name'), (string) $rt->form->key),
            $token === '@recipient' => (string) ($ctx->recipientName ?? ''),
            $token === '@today' => Carbon::now((string) $this->settings->get('formats', 'timezone'))->format('Y-m-d'),
            $token === '@now' => Carbon::now((string) $this->settings->get('formats', 'timezone'))->format('Y-m-d H:i'),
            $token === '@user' => (string) ($ctx->actorName ?? ''),
            $token === '@app' => $this->appName(),
            $rt === null || $ctx->recordId === null || $ctx->viewers === [] => '',
            $token === '@created_by', $token === '@updated_by' => (string) (DB::table('users')->where('id', DB::table($rt->table)->where('id', $ctx->recordId)->value(substr($token, 1)))->value('name') ?? ''),
            $token === '@assignee' => $this->assigneeNames($rt, $ctx),
            default => $this->shared($rt, $token, $ctx),
        };

        return $ctx->cache[$token] = $value;
    }

    /** A design's accent colour: its own, else the brand's primary colour, else the product default. @param  array<string, mixed>  $design */
    public function accent(array $design): string
    {
        $c = $design['style']['accent'] ?? null;
        if (is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) === 1) {
            return $c;
        }
        $brand = $this->settings->get('branding', 'primary_color');

        return is_string($brand) && preg_match('/^#[0-9a-f]{6}$/i', $brand) === 1 ? $brand : self::DEFAULT_ACCENT;
    }

    /** A path's text as every viewer sees it, or '' when they would not all see the same. */
    private function shared(FormRuntime $rt, string $token, RenderContext $ctx): string
    {
        $seen = null;
        foreach ($ctx->viewers as $viewer) {
            $v = self::flatten($this->paths->values($rt, explode('.', $token), [$ctx->recordId], $viewer)[$ctx->recordId] ?? null);
            if ($seen !== null && $v !== $seen) {
                return '';
            }
            $seen = $v;
        }

        return (string) $seen;
    }

    /** @return array<string, mixed> the record's values with the fields one viewer cannot see emptied */
    private function visibleValues(RenderContext $ctx, int $viewer): array
    {
        $record = $this->store->findById($ctx->rt, (int) $ctx->recordId);
        $values = $record['values'] ?? [];
        foreach ($this->levelsOf($ctx, $viewer) as $uuid => $level) {
            $key = $ctx->rt->fields[$uuid]['key'] ?? null;
            if ($level === 'hidden' && $key !== null) {
                $values[$key] = null;
            }
        }

        return $values;
    }

    /** @return array<string, string> field and group access of one viewer */
    private function levelsOf(RenderContext $ctx, int $viewer): array
    {
        if (! isset($ctx->levels[$viewer])) {
            $status = DB::table($ctx->rt->table)->where('id', $ctx->recordId)->value('status_id');
            $ctx->levels[$viewer] = $this->fieldAccess->resolve($ctx->viewers[$viewer], $ctx->rt->form->id, $ctx->rt->form->uuid, $ctx->rt->definition, 'view', $status === null ? null : (int) $status)['fields'];
        }

        return $ctx->levels[$viewer];
    }

    /** @return array<string, string> the most restrictive access across the viewers: hidden for one is hidden */
    private function levels(RenderContext $ctx): array
    {
        $out = [];
        foreach (array_keys($ctx->viewers) as $i) {
            foreach ($this->levelsOf($ctx, $i) as $uuid => $level) {
                if ($level === 'hidden' || ! isset($out[$uuid])) {
                    $out[$uuid] = $level;
                }
            }
        }

        return $out;
    }

    /** Who the record is assigned to now: people by name, roles and departments by their name in this language. */
    private function assigneeNames(FormRuntime $rt, RenderContext $ctx): string
    {
        $names = [];
        foreach (DB::table('assignments')->where('form_id', $rt->form->id)->where('record_id', $ctx->recordId)->where('status', 'active')->orderBy('id')->get(['assignee_type', 'assignee_id']) as $a) {
            $names[] = match ($a->assignee_type) {
                'user' => (string) (DB::table('users')->where('id', $a->assignee_id)->value('name') ?? ''),
                'role' => (string) ($this->translator->get('role', (int) $a->assignee_id, 'name', $ctx->locale) ?? Translator::humanize((string) DB::table('roles')->where('id', $a->assignee_id)->value('key'))),
                default => (string) ($this->translator->get('department', (int) $a->assignee_id, 'name', $ctx->locale) ?? ''),
            };
        }

        return implode(', ', array_values(array_unique(array_filter($names))));
    }

    public function appName(): string
    {
        return $this->translator->get('organization', app(TenantContext::class)->organizationId(), 'name') ?? (string) config('app.name');
    }

    private static function flatten(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '✓' : '',
            is_array($value) => implode(', ', array_map(static fn ($v) => self::flatten($v), $value)),
            default => (string) $value,
        };
    }
}
