<?php

declare(strict_types=1);

namespace App\Modules\Records\Runtime;

use App\Expressions\Calendars\Civil;
use App\Expressions\Evaluation\Context;
use App\Expressions\Evaluation\Evaluator;
use App\Expressions\Numbers\Decimal;
use App\Expressions\Text\SafeRegex;
use App\Expressions\Text\Unicode;
use App\Modules\Core\I18n\Translator;
use App\Modules\Records\Models\StoredFile;
use Illuminate\Support\Facades\DB;

/**
 * Server-side validation of a record (specification §4.6 Validation, §4.5
 * group validation, §4.7 condition effects): required, length, number,
 * pattern, format presets, dates, files, options, uniqueness, cross-field
 * comparisons, existence checks, custom rules, repeater rows and group rules.
 * Hidden fields (by access or by a condition) are not validated.
 *
 * Errors are keyed by field key, or `repeaterKey.index.fieldKey` for rows,
 * with messages already translated (custom per-rule messages win).
 */
final class RecordValidator
{
    private const FORMATS = [
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/u',
        'url' => '#^https?://[^\s/$.?\#].[^\s]*$#iu',
        'phone' => '/^\+?[0-9 ()-]{6,20}$/',
        'numeric' => '/^[0-9]+$/',
        'arabic' => '/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\s0-9.,،؛-]+$/u',
        'english' => '/^[A-Za-z\s0-9.,;:\'"!?()-]+$/',
        'alphanumeric' => '/^[\pL\pN]+$/u',
    ];

    /** @var array<string, list<string>> */
    private array $errors = [];

    public function __construct(private readonly References $refs, private readonly RecordLoader $loader) {}

    /**
     * @param  array<string, mixed>  $values  final values after rules
     * @param  array<string, string>  $access  field uuid => level
     * @return array<string, list<string>>
     */
    public function validate(FormRuntime $rt, array $values, RuleState $state, array $access, Context $ctx, ?int $recordId): array
    {
        $this->errors = [];
        $record = ValuesRecord::forRecord($rt, $values, $this->loader);
        $base = $ctx->with(['record' => $record]);
        foreach ($rt->mainFields() as $f) {
            if ($this->hidden($rt, $f, $state, $access, null)) {
                continue;
            }
            $this->field($rt, $f, $values[$f['key']] ?? null, $values, $state, $access, null, $f['key'], $base, $recordId);
        }
        foreach ($rt->repeaters as $repUuid => $rep) {
            $key = $rep['group']['key'];
            if ($this->groupHidden($rt, $repUuid, $state, null)) {
                continue;
            }
            $rows = is_array($values[$key] ?? null) ? array_values($values[$key]) : [];
            $cfg = $rep['group']['repeater'] ?? [];
            if (count($rows) < (int) ($cfg['minRows'] ?? 0)) {
                $this->add($key, $this->msg(null, 'min_rows', ['min' => (int) $cfg['minRows']]));
            }
            if (($cfg['maxRows'] ?? null) !== null && count($rows) > (int) $cfg['maxRows']) {
                $this->add($key, $this->msg(null, 'max_rows', ['max' => (int) $cfg['maxRows']]));
            }
            foreach ($rows as $i => $row) {
                $rowCtx = $base->withRow(new ValuesRecord($rt, is_array($row) ? $row : [], $rep['fields'], null, null, $this->loader, false));
                foreach ($rt->rowFields($repUuid) as $f) {
                    if ($this->hidden($rt, $f, $state, $access, [$key, $i])) {
                        continue;
                    }
                    $this->field($rt, $f, $row[$f['key']] ?? null, is_array($row) ? $row : [], $state, $access, [$key, $i], "{$key}.{$i}.{$f['key']}", $rowCtx, null);
                }
            }
        }
        foreach ($rt->groups as $g) {
            if ($this->groupHidden($rt, $g['uuid'], $state, null) || isset($rt->repeaters[$g['uuid']])) {
                continue;
            }
            $this->group($rt, $g, $values, $base);
        }
        foreach ($state->blocks as $b) {
            $this->add('_form', $this->i18n($b['message']) ?? __('records.rules.blocked'));
        }

        return $this->errors;
    }

    private function field(FormRuntime $rt, array $f, mixed $v, array $scope, RuleState $state, array $access, ?array $row, string $path, Context $ctx, ?int $recordId): void
    {
        $type = $rt->type($f);
        if ($type === null || ! $type->isStored() || $type->calculated || $type->storage === 'auto_number') {
            return;
        }
        $rules = $f['validation'] ?? [];
        $required = ($rules['required'] ?? false) || ($access[$f['uuid']] ?? null) === 'required' || $state->flag($f['uuid'], 'required', $row);
        if ($this->empty($v)) {
            if ($required) {
                $this->add($path, $this->msg($f, 'required'));
            }

            return;
        }
        $storage = $type->storage;
        $text = is_string($v) ? $v : null;

        // Length and pattern (text-like values).
        if ($text !== null && in_array($storage, ['string', 'text', 'longtext', 'choice'], true)) {
            $len = Unicode::length($text);
            if (($min = $rules['length']['min'] ?? null) !== null && $len < $min) {
                $this->add($path, $this->msg($f, 'length', ['min' => $min, 'max' => $rules['length']['max'] ?? '']));
            }
            if (($max = $rules['length']['max'] ?? null) !== null && $len > $max) {
                $this->add($path, $this->msg($f, 'length', ['min' => $rules['length']['min'] ?? 0, 'max' => $max]));
            }
            $limit = $rt->column($f['uuid'])['length'] ?? null;
            if ($limit !== null && $len > (int) $limit) {
                $this->add($path, $this->msg(null, 'too_long', ['max' => $limit]));
            }
            if (! empty($rules['pattern']) && SafeRegex::matches($text, $rules['pattern']) !== true) {
                $this->add($path, $this->msg($f, 'pattern'));
            }
            if (! empty($rules['format']) && ! $this->format($rules['format'], $text)) {
                $this->add($path, $this->msg($f, 'format', ['format' => $rules['format']]));
            }
        }
        if (is_array($v) && $storage === 'phone' && ! empty($rules['format']) && $rules['format'] !== 'phone' && ! $this->format($rules['format'], (string) ($v['number'] ?? ''))) {
            $this->add($path, $this->msg($f, 'format', ['format' => $rules['format']]));
        }

        // Numbers.
        $number = match ($storage) {
            'decimal', 'number', 'int', 'duration' => is_string($v) || is_int($v) ? Decimal::parse((string) $v) : null,
            'currency' => Decimal::parse((string) (is_array($v) ? ($v['amount'] ?? '') : $v)),
            default => null,
        };
        if ($number !== null) {
            $col = $rt->column($f['uuid']);
            if (($col['type'] ?? null) === 'decimal') {
                $intDigits = strlen(ltrim(explode('.', ltrim($number->abs()->toString(), '-'))[0], '0'));
                $scale = strlen(explode('.', $number->toString())[1] ?? '');
                if ($intDigits > (int) $col['precision'] - (int) $col['scale'] || $scale > (int) $col['scale']) {
                    $this->add($path, $this->msg(null, 'precision', ['precision' => $col['precision'], 'scale' => $col['scale']]));
                }
            }
            if (($min = $rules['number']['min'] ?? null) !== null && $number->compare(Decimal::of($min)) < 0) {
                $this->add($path, $this->msg($f, 'number', ['min' => $min, 'max' => $rules['number']['max'] ?? '']));
            }
            if (($max = $rules['number']['max'] ?? null) !== null && $number->compare(Decimal::of($max)) > 0) {
                $this->add($path, $this->msg($f, 'number', ['min' => $rules['number']['min'] ?? '', 'max' => $max]));
            }
            if (($step = $rules['number']['step'] ?? null) !== null && Decimal::of($step)->compare(Decimal::of('0')) > 0) {
                $origin = ($rules['number']['min'] ?? null) !== null ? Decimal::of($rules['number']['min']) : Decimal::of('0');
                $rem = $number->sub($origin);
                $rem = $rem instanceof Decimal ? $rem->mod(Decimal::of($step)) : null;
                if ($rem instanceof Decimal && ! $rem->isZero()) {
                    $this->add($path, $this->msg($f, 'step', ['step' => $step]));
                }
            }
        }

        // Dates.
        if (in_array($storage, ['date', 'datetime', 'range_date', 'range_datetime'], true)) {
            $dates = is_array($v) ? array_filter([$v['from'] ?? null, $v['to'] ?? null]) : [$v];
            foreach ($dates as $d) {
                $this->date($f, (string) $d, $rules['date'] ?? [], $path, $ctx);
            }
        }

        // Options.
        if ($type->options && ($f['options']['source'] ?? null) === 'static' && ! ($f['options']['allowCustom'] ?? false)) {
            $allowed = array_column(array_filter($f['options']['static'] ?? [], static fn ($o) => $o['active'] ?? true), 'value');
            foreach ((array) $v as $item) {
                if (! in_array($item, $allowed, true)) {
                    $this->add($path, $this->msg(null, 'option'));
                    break;
                }
            }
        }
        if (is_array($v) && array_is_list($v) && $type->multiple) {
            if (($min = $f['options']['min'] ?? null) !== null && count($v) < $min) {
                $this->add($path, $this->msg($f, 'min_selections', ['min' => $min]));
            }
            if (($max = $f['options']['max'] ?? null) !== null && count($v) > $max) {
                $this->add($path, $this->msg($f, 'max_selections', ['max' => $max]));
            }
        }

        // References must exist (and not be deleted).
        $table = $rt->targetTable($f);
        if ($table !== null) {
            $uuids = array_values(array_filter((array) $v, 'is_string'));
            $found = $this->refs->ids($table, $uuids);
            if (count($found) !== count($uuids)) {
                $this->add($path, $this->msg(null, 'reference'));
            }
        }

        // Files.
        if (in_array($storage, ['file', 'files'], true)) {
            $this->files($f, (array) $v, $rules['file'] ?? [], $path);
        }

        // Uniqueness (application level; scope and soft-deleted handling).
        if (($rules['unique'] ?? null) !== null && $row === null) {
            $this->unique($rt, $f, $v, $scope, $rules['unique'], $path, $recordId);
        }

        // Cross-field comparisons.
        foreach ($rules['compare'] ?? [] as $cmp) {
            $other = $rt->fields[$cmp['field']] ?? null;
            if ($other === null) {
                continue;
            }
            $ov = $scope[$other['key']] ?? null;
            if ($this->empty($ov)) {
                continue;
            }
            $a = is_array($v) ? ($v['amount'] ?? null) : $v;
            $b = is_array($ov) ? ($ov['amount'] ?? null) : $ov;
            $c = (Decimal::parse((string) $a) !== null && Decimal::parse((string) $b) !== null) ? Decimal::parse((string) $a)->compare(Decimal::parse((string) $b)) : strcmp((string) $a, (string) $b) <=> 0;
            $ok = match ($cmp['op']) {
                'gt', 'after' => $c > 0,
                'gte' => $c >= 0,
                'lt', 'before' => $c < 0,
                'lte' => $c <= 0,
                'eq' => $c === 0,
                default => $c !== 0,
            };
            if (! $ok) {
                $this->add($path, $this->msg($f, 'compare', ['op' => $cmp['op'], 'other' => $this->label($other)]));
            }
        }

        // Existence in a collection.
        if (($async = $rules['async'] ?? null) !== null && is_string($v)) {
            $exists = $this->existsIn($async['collection'], $async['path'], $v);
            if ($exists !== null && $exists !== ($async['type'] === 'exists_in')) {
                $this->add($path, $this->msg($f, 'async'));
            }
        }

        // Custom rules: the rule fails when its expression is true.
        foreach ($rules['custom'] ?? [] as $custom) {
            if (Evaluator::evaluate($custom['when'], $ctx)->value->data === true) {
                $this->add($path, $this->msg($f, $custom['messageKey']));
            }
        }
    }

    private function date(array $f, string $value, array $rules, string $path, Context $ctx): void
    {
        $day = Civil::parseDate(substr($value, 0, 10));
        if ($day === null) {
            return;
        }
        if (($rules['noPast'] ?? false) && $day < $ctx->today) {
            $this->add($path, $this->msg($f, 'no_past'));
        }
        if (($rules['noFuture'] ?? false) && $day > $ctx->today) {
            $this->add($path, $this->msg($f, 'no_future'));
        }
        if (in_array(Civil::weekday($day), $rules['disabledWeekdays'] ?? [], true)) {
            $this->add($path, $this->msg($f, 'disabled_weekday'));
        }
        if (in_array(substr($value, 0, 10), $rules['disabledDates'] ?? [], true)) {
            $this->add($path, $this->msg($f, 'disabled_date'));
        }
        foreach (['min' => 1, 'max' => -1] as $bound => $sign) {
            if (($rules[$bound] ?? null) === null) {
                continue;
            }
            $limit = Evaluator::evaluate($rules[$bound], $ctx)->value;
            $limitDay = match ($limit->type) {
                'date' => $limit->data,
                'datetime' => intdiv($limit->data, 86400),
                default => null,
            };
            if ($limitDay !== null && ($day - $limitDay) * $sign < 0) {
                $this->add($path, $this->msg($f, 'date_'.$bound, ['date' => Civil::formatDate($limitDay)]));
            }
        }
    }

    /** @param  list<mixed>  $uuids */
    private function files(array $f, array $uuids, array $rules, string $path): void
    {
        $uuids = array_values(array_filter($uuids, 'is_string'));
        if (($max = $rules['maxCount'] ?? null) !== null && count($uuids) > $max) {
            $this->add($path, $this->msg($f, 'file_count', ['max' => $max]));
        }
        $files = StoredFile::query()->whereIn('uuid', $uuids)->whereNull('deleted_at')->get();
        if ($files->count() !== count($uuids)) {
            $this->add($path, $this->msg(null, 'file_missing'));

            return;
        }
        foreach ($files as $file) {
            if (! empty($rules['types']) && ! in_array($file->extension, $rules['types'], true)) {
                $this->add($path, $this->msg($f, 'file_type', ['types' => implode(', ', $rules['types'])]));
            }
            if (! empty($rules['mimes']) && ! $this->mimeAllowed($file->mime_type, $rules['mimes'])) {
                $this->add($path, $this->msg($f, 'file_type', ['types' => implode(', ', $rules['mimes'])]));
            }
            if (($kb = $rules['maxSizeKb'] ?? null) !== null && $file->size_bytes > $kb * 1024) {
                $this->add($path, $this->msg($f, 'file_size', ['kb' => $kb]));
            }
            $img = $rules['image'] ?? [];
            if ($img !== [] && $file->width !== null) {
                if ((($img['minWidth'] ?? null) !== null && $file->width < $img['minWidth']) || (($img['maxWidth'] ?? null) !== null && $file->width > $img['maxWidth'])
                    || (($img['minHeight'] ?? null) !== null && $file->height < $img['minHeight']) || (($img['maxHeight'] ?? null) !== null && $file->height > $img['maxHeight'])) {
                    $this->add($path, $this->msg($f, 'image_dimensions'));
                }
            }
            if ($file->scan_status === 'infected') {
                $this->add($path, $this->msg(null, 'file_missing'));
            }
        }
    }

    /** @param  list<string>  $allowed */
    private function mimeAllowed(string $mime, array $allowed): bool
    {
        foreach ($allowed as $a) {
            if ($a === $mime || (str_ends_with($a, '/*') && str_starts_with($mime, substr($a, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    private function unique(FormRuntime $rt, array $f, mixed $v, array $values, array $rule, string $path, ?int $recordId): void
    {
        $col = $rt->column($f['uuid']);
        if ($col === null || is_array($v) || ($col['encrypted'] ?? false)) {
            if (($col['encrypted'] ?? false) && ($bidx = $rt->column($f['uuid'], 'bidx')) !== null) {
                $q = DB::table($rt->table)->where($bidx['name'], ValueCodec::blindIndex($v));
            } else {
                return;
            }
        } else {
            $dbValue = $col['type'] === 'bigint' && is_string($v) ? ($this->refs->ids((string) $rt->targetTable($f), [$v])[$v] ?? null) : $v;
            $q = DB::table($rt->table)->where($col['name'], $dbValue);
        }
        if (! ($rule['includeDeleted'] ?? false)) {
            $q->whereNull('deleted_at');
        }
        if ($recordId !== null) {
            $q->where('id', '!=', $recordId);
        }
        foreach ($rule['scope'] ?? [] as $scopeUuid) {
            $sf = $rt->fields[$scopeUuid] ?? null;
            $sc = $sf === null ? null : $rt->column($scopeUuid);
            if ($sc === null) {
                continue;
            }
            $sv = $values[$sf['key']] ?? null;
            $sv = $sc['type'] === 'bigint' && is_string($sv) ? ($this->refs->ids((string) $rt->targetTable($sf), [$sv])[$sv] ?? null) : $sv;
            $sv === null ? $q->whereNull($sc['name']) : $q->where($sc['name'], $sv);
        }
        if ($q->exists()) {
            $this->add($path, $this->msg($f, 'unique'));
        }
    }

    /** Whether a value exists in a collection column (null when the collection cannot be checked). */
    public function existsIn(string $collectionUuid, array $path, string $value): ?bool
    {
        $target = app(FormRuntimes::class)->forUuid($collectionUuid);
        if ($target === null) {
            return null;
        }
        $fieldUuid = $target->keys[$path[0]] ?? null;
        $col = $fieldUuid === null ? null : $target->column($fieldUuid);
        if ($col === null) {
            return null;
        }

        return DB::table($target->table)->whereNull('deleted_at')->where($col['name'], $value)->exists();
    }

    private function group(FormRuntime $rt, array $g, array $values, Context $ctx): void
    {
        $v = $g['validation'] ?? null;
        if (! is_array($v)) {
            return;
        }
        if (($min = $v['minFilled'] ?? null) !== null && $min > 0) {
            $filled = 0;
            foreach ($rt->mainFields() as $f) {
                if ($this->inGroup($rt, $f['group'], $g['uuid']) && ! $this->empty($values[$f['key']] ?? null)) {
                    $filled++;
                }
            }
            if ($filled < $min) {
                $this->add('_group.'.$g['key'], $this->i18n($v['minFilledMessage'] ?? null) ?? __('records.rules.min_filled', ['min' => $min]));
            }
        }
        foreach ($v['rules'] ?? [] as $rule) {
            if (Evaluator::evaluate($rule['when'], $ctx)->value->data === true) {
                $this->add('_group.'.$g['key'], $this->i18n($rule['message'] ?? null) ?? __('records.rules.custom'));
            }
        }
    }

    private function inGroup(FormRuntime $rt, ?string $groupUuid, string $ancestor): bool
    {
        $seen = [];
        while ($groupUuid !== null && ! isset($seen[$groupUuid])) {
            if ($groupUuid === $ancestor) {
                return true;
            }
            $seen[$groupUuid] = true;
            $groupUuid = $rt->groups[$groupUuid]['parent'] ?? null;
        }

        return false;
    }

    /** @param  array<string, string>  $access */
    public function hidden(FormRuntime $rt, array $f, RuleState $state, array $access, ?array $row): bool
    {
        if (($access[$f['uuid']] ?? 'editable') === 'hidden' || $state->flag($f['uuid'], 'hidden', $row)) {
            return true;
        }

        return $this->groupHidden($rt, $f['group'], $state, $row);
    }

    private function groupHidden(FormRuntime $rt, ?string $groupUuid, RuleState $state, ?array $row): bool
    {
        $seen = [];
        while ($groupUuid !== null && ! isset($seen[$groupUuid])) {
            $seen[$groupUuid] = true;
            if ($state->flag($groupUuid, 'hidden', $row) || $state->flag($groupUuid, 'hidden')) {
                return true;
            }
            $groupUuid = $rt->groups[$groupUuid]['parent'] ?? null;
        }

        return false;
    }

    private function format(string $format, string $value): bool
    {
        return match ($format) {
            'iban' => self::iban($value),
            'national_id' => preg_match('/^[0-9]{8,15}$/', Unicode::asciiDigits($value)) === 1,
            default => preg_match(self::FORMATS[$format] ?? '/.*/', $value) === 1,
        };
    }

    public static function iban(string $value): bool
    {
        $s = strtoupper(str_replace(' ', '', $value));
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $s) !== 1) {
            return false;
        }
        $moved = substr($s, 4).substr($s, 0, 4);
        $digits = '';
        foreach (str_split($moved) as $ch) {
            $digits .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rem = 0;
        foreach (str_split($digits) as $d) {
            $rem = ($rem * 10 + (int) $d) % 97;
        }

        return $rem === 1;
    }

    private function empty(mixed $v): bool
    {
        if ($v === null || $v === '' || $v === []) {
            return true;
        }
        if (is_array($v) && ! array_is_list($v)) {
            return array_filter($v, static fn ($x) => $x !== null && $x !== '') === [];
        }

        return $v === false;
    }

    private function label(array $f): string
    {
        return app(Translator::class)->pick($f['i18n']['label'] ?? null) ?? $f['key'];
    }

    /** Translated message: the field's custom message for the rule, else the default. */
    private function msg(?array $f, string $rule, array $params = []): string
    {
        $custom = $f === null ? null : $this->i18n($f['i18n']['messages'][$rule] ?? null);
        if ($custom !== null) {
            return $custom;
        }
        $params += ['attribute' => $f === null ? '' : $this->label($f)];
        $key = 'records.rules.'.$rule;
        $text = __($key, $params);

        return $text === $key ? __('records.rules.custom', $params) : $text;
    }

    /** @param  array<string, string>|null  $map */
    private function i18n(?array $map): ?string
    {
        if ($map === null || $map === []) {
            return null;
        }
        $locale = app()->getLocale();

        return $map[$locale] ?? (reset($map) ?: null);
    }

    private function add(string $path, string $message): void
    {
        if (! in_array($message, $this->errors[$path] ?? [], true)) {
            $this->errors[$path][] = $message;
        }
    }
}
