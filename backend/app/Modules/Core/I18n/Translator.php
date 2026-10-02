<?php

declare(strict_types=1);

namespace App\Modules\Core\I18n;

use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Translation;
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
        $chain = $this->fallbackChain($locale);
        $rows = $ids === [] ? collect() : Translation::query()
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
        $path = resource_path("ui-strings/{$locale}.json");
        if (! is_file($path)) {
            return [];
        }
        /** @var array<string, string> $data */
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $data;
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
