<?php

declare(strict_types=1);

namespace App\Modules\Core\I18n;

/**
 * Translatable attributes live in `translations`, never in columns (ADR-0007).
 * A model lists its translatable attributes in `$translatable` and names its
 * morph type in `translationType()`.
 */
trait HasTranslations
{
    abstract public function translationType(): string;

    /** @return list<string> */
    public function translatableAttributes(): array
    {
        return $this->translatable ?? [];
    }

    /** Value in the requested (or current) locale, following the fallback chain. */
    public function translate(string $field, ?string $locale = null): ?string
    {
        return app(Translator::class)->get($this->translationType(), (int) $this->getKey(), $field, $locale);
    }

    /** @return array<string, string> locale => value */
    public function translationsFor(string $field): array
    {
        return app(Translator::class)->all($this->translationType(), (int) $this->getKey(), $field);
    }

    /**
     * Replace the given locales' values; null or empty strings delete the locale row.
     *
     * @param  array<string, string|null>  $values  locale => value
     */
    public function setTranslations(string $field, array $values): void
    {
        app(Translator::class)->putMany($this->translationType(), (int) $this->getKey(), $field, $values);
    }

    public static function bootHasTranslations(): void
    {
        static::deleted(static function (self $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return; // soft-deleted objects keep their labels for history
            }
            app(Translator::class)->forget($model->translationType(), (int) $model->getKey());
        });
    }
}
