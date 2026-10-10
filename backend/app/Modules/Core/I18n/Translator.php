<?php

declare(strict_types=1);

namespace App\Modules\Core\I18n;

use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Translation;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Translation lookup with fallback (architecture §19.24): requested locale →
 * that locale's fallback → default locale. Missing values are never rendered
 * empty; callers get null only when no locale in the chain has a value.
 */
final class Translator
{
    public const UI_TYPE = 'ui';

    /** @var array<string, list<string>> */
    private array $chains = [];

    /** @return array<string, array{fallback: string|null, default: bool, enabled: bool, direction: string}> */
    public function localeTable(): array
    {
        return Cache::remember('i18n:locales', 3600, static fn (): array => Locale::query()->with('fallback')->orderBy('sort_order')->get()
            ->mapWithKeys(static fn (Locale $l): array => [$l->code => [
                'fallback' => $l->fallback?->code,
                'default' => (bool) $l->is_default,
                'enabled' => (bool) $l->is_enabled,
                'direction' => (string) $l->direction,
            ]])
            ->all());
    }

    /** @return list<string> enabled locale codes, in display order */
    public function enabledLocales(): array
    {
        return array_keys(array_filter($this->localeTable(), static fn (array $l): bool => $l['enabled']));
    }

    public function defaultLocale(): string
    {
        $table = $this->localeTable();
        $default = array_key_first(array_filter($table, static fn (array $l): bool => $l['default']));

        return (string) ($default ?? config('app.locale'));
    }

    /**
     * The text of a per-locale map (an object's `i18n` entry) for the
     * request's locale: that locale, its fallback chain, then any filled
     * locale. Blank entries count as missing, so a label filled only in the
     * default language is still shown (design system §5.6).
     *
     * @param  array<string, string|null>|object|null  $values
     */
    public function pick(array|object|null $values, ?string $locale = null): ?string
    {
        $values = (array) ($values ?? []);
        foreach ([$locale ?? App::getLocale(), ...$this->fallbackChain($locale), ...array_keys($values)] as $code) {
            $v = $values[$code] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return $v;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function fallbackChain(?string $locale = null): array
    {
        $locale ??= App::getLocale();
        if (isset($this->chains[$locale])) {
            return $this->chains[$locale];
        }
        $locales = $this->localeTable();
        $chain = [];
        $current = $locale;
        while ($current !== null && isset($locales[$current]) && ! in_array($current, $chain, true)) {
            $chain[] = $current;
            $current = $locales[$current]['fallback'];
        }
        $default = array_key_first(array_filter($locales, static fn (array $l): bool => $l['default']));
        if ($default !== null && ! in_array($default, $chain, true)) {
            $chain[] = (string) $default;
        }

        return $this->chains[$locale] = $chain;
    }

    public function get(string $type, int $id, string $field, ?string $locale = null): ?string
    {
        $values = $this->all($type, $id, $field);
        foreach ($this->fallbackChain($locale) as $code) {
            if (isset($values[$code]) && $values[$code] !== '') {
                return $values[$code];
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public function all(string $type, int $id, string $field): array
    {
        return Translation::query()
            ->where(['object_type' => $type, 'object_id' => $id, 'field' => $field])
            ->pluck('value', 'locale')->all();
    }

    /**
     * Bulk lookup for lists: [id => [field => value]] resolved for the locale.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $fields
     * @return array<int, array<string, string|null>>
     */
    public function many(string $type, array $ids, array $fields, ?string $locale = null): array
    {
        if ($ids === []) {
            return [];
        }
        $chain = $this->fallbackChain($locale);
        $rows = Translation::query()
            ->where('object_type', $type)->whereIn('object_id', $ids)->whereIn('field', $fields)->whereIn('locale', $chain)
            ->get(['object_id', 'field', 'locale', 'value']);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->object_id][$row->field][$row->locale] = $row->value;
        }
        $result = [];
        foreach ($ids as $id) {
            foreach ($fields as $field) {
                $value = null;
                foreach ($chain as $code) {
                    $candidate = $indexed[$id][$field][$code] ?? null;
                    if ($candidate !== null && $candidate !== '') {
                        $value = $candidate;
                        break;
                    }
                }
                $result[$id][$field] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function putMany(string $type, int $id, string $field, array $values): void
    {
        $known = Locale::query()->pluck('code')->all();
        foreach ($values as $locale => $value) {
            if (! in_array($locale, $known, true)) {
                throw ValidationException::withMessages(["{$field}.{$locale}" => __('validation.in', ['attribute' => 'locale'])]);
            }
            if ($value === null || trim($value) === '') {
                Translation::query()->where(['object_type' => $type, 'object_id' => $id, 'field' => $field, 'locale' => $locale])->delete();

                continue;
            }
            Translation::query()->updateOrCreate(
                ['object_type' => $type, 'object_id' => $id, 'field' => $field, 'locale' => $locale],
                ['value' => $value, 'updated_by' => Auth::id()],
            );
        }
    }

    /**
     * Every stored locale value of the given objects: [id => [field => [locale => value]]].
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, array<string, string>>>
     */
    public function allMany(string $type, array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = Translation::query()->where('object_type', $type)->whereIn('object_id', $chunk)->get(['object_id', 'field', 'locale', 'value']);
            foreach ($rows as $row) {
                $out[(int) $row->object_id][$row->field][$row->locale] = $row->value;
            }
        }

        return $out;
    }

    /**
     * Makes the stored translations of many objects equal to the given values in
     * one pass: missing (object, field, locale) rows are inserted, changed ones
     * updated, and rows of the listed objects that are no longer given deleted.
     * Used by bulk metadata saves (form drafts) instead of per-value upserts.
     *
     * @param  array<int, array<string, array<string, string|null>>>  $values  id => field => locale => value
     */
    public function syncObjects(string $type, array $values): void
    {
        if ($values === []) {
            return;
        }
        $known = array_flip(Locale::query()->pluck('code')->all());
        $existing = $this->allMany($type, array_keys($values));
        $userId = Auth::id();
        $now = now('UTC')->format('Y-m-d H:i:s.u');
        $organizationId = app(TenantContext::class)->organizationId();
        $inserts = [];
        $deletes = [];
        foreach ($values as $id => $fields) {
            $current = $existing[$id] ?? [];
            foreach ($fields as $field => $locales) {
                foreach ($locales as $locale => $value) {
                    if (! isset($known[$locale])) {
                        throw ValidationException::withMessages(["{$field}.{$locale}" => __('validation.in', ['attribute' => 'locale'])]);
                    }
                    $value = ($value === null || trim($value) === '') ? '' : $value;
                    $old = $current[$field][$locale] ?? null;
                    if ($value === '') {
                        continue;
                    }
                    if ($old === null) {
                        $inserts[] = ['organization_id' => $organizationId, 'object_type' => $type, 'object_id' => $id, 'field' => $field, 'locale' => $locale, 'value' => $value, 'updated_by' => $userId, 'updated_at' => $now];
                    } elseif ($old !== $value) {
                        Translation::query()->where(['object_type' => $type, 'object_id' => $id, 'field' => $field, 'locale' => $locale])
                            ->update(['value' => $value, 'updated_by' => $userId, 'updated_at' => $now]);
                    }
                }
            }
            foreach ($current as $field => $locales) {
                foreach ($locales as $locale => $old) {
                    $new = $fields[$field][$locale] ?? null;
                    if ($new === null || trim($new) === '') {
                        $deletes[$id][] = [$field, $locale];
                    }
                }
            }
        }
        foreach (array_chunk($inserts, 200) as $chunk) {
            Translation::query()->insert($chunk);
        }
        foreach ($deletes as $id => $pairs) {
            Translation::query()->where('object_type', $type)->where('object_id', $id)
                ->where(function ($q) use ($pairs): void {
                    foreach ($pairs as [$field, $locale]) {
                        $q->orWhere(fn ($w) => $w->where('field', $field)->where('locale', $locale));
                    }
                })->delete();
        }
    }

    public function forget(string $type, int $id): void
    {
        Translation::query()->where(['object_type' => $type, 'object_id' => $id])->delete();
    }

    /**
     * UI strings for a locale: bundled defaults overlaid with admin edits, each
     * missing key filled from the fallback chain.
     *
     * @return array<string, string>
     */
    public function uiCatalog(string $locale): array
    {
        $merged = [];
        foreach (array_reverse($this->fallbackChain($locale)) as $code) {
            $merged = array_replace($merged, $this->bundledUi($code), $this->uiOverrides($code));
        }

        return $merged;
    }

    /** @return array<string, string> */
    public function bundledUi(string $locale): array
    {
        // `ui-strings/{locale}.json` plus one file per area in `ui-strings/{locale}/`.
        $paths = [resource_path("ui-strings/{$locale}.json"), ...(glob(resource_path("ui-strings/{$locale}/*.json")) ?: [])];
        $merged = [];
        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }
            /** @var array<string, string> $data */
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $merged = array_replace($merged, $data);
        }

        return $merged;
    }

    /** @return array<string, string> */
    public function uiOverrides(string $locale): array
    {
        return Translation::query()
            ->where(['object_type' => self::UI_TYPE, 'object_id' => 0, 'locale' => $locale])
            ->pluck('value', 'field')->all();
    }

    public function flush(): void
    {
        Cache::forget('i18n:locales');
        $this->chains = [];
    }
}
