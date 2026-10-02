<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Locales (specification §3): each declares direction, calendar preference,
 * number formatting, and fallback. Adding a language needs no schema change.
 */
final class LocaleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_translations');

        return response()->json(['data' => Locale::query()->with('fallback:id,code')->orderBy('sort_order')->get()->map(fn (Locale $l): array => $this->present($l))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $data = $this->validated($request, null);
        $locale = DB::transaction(function () use ($data): Locale {
            $locale = Locale::query()->create($this->attributes($data) + ['is_default' => false]);
            app(AuditWriter::class)->record('config.locale_added', 'config', objectType: 'locale', objectId: $locale->id, meta: ['code' => $locale->code]);

            return $locale;
        });
        app(Translator::class)->flush();

        return response()->json(['data' => $this->present($locale->load('fallback'))], 201);
    }

    public function update(Request $request, Locale $locale): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $data = $this->validated($request, $locale);
        DB::transaction(function () use ($locale, $data): void {
            if (($data['is_default'] ?? false) === true) {
                abort_if(($data['is_enabled'] ?? $locale->is_enabled) === false, 422, __('ui.locales.default_must_be_enabled'));
                Locale::query()->where('id', '!=', $locale->id)->update(['is_default' => false]);
                $locale->is_default = true;
            }
            if (($data['is_enabled'] ?? true) === false) {
                abort_if($locale->is_default, 422, __('ui.locales.cannot_disable_default'));
            }
            $locale->fill($this->attributes($data, $locale))->save();
            app(AuditWriter::class)->record('config.locale_changed', 'config', objectType: 'locale', objectId: $locale->id, meta: ['code' => $locale->code]);
        });
        app(Translator::class)->flush();

        return response()->json(['data' => $this->present($locale->fresh('fallback'))]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?Locale $locale = null): array
    {
        $out = collect($data)->except(['fallback', 'is_default', 'code'])->all();
        if (isset($out['number_format'])) {
            $out['number_format']['group'] ??= ''; // "no grouping" arrives as null
        }
        if ($locale === null) {
            $out['code'] = $data['code'];
        }
        if (array_key_exists('fallback', $data)) {
            $fallbackId = $data['fallback'] === null ? null : (int) Locale::query()->where('code', $data['fallback'])->value('id');
            abort_if($locale !== null && $fallbackId === $locale->id, 422, __('ui.locales.self_fallback'));
            $out['fallback_locale_id'] = $fallbackId;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Locale $locale): array
    {
        $r = $locale === null ? 'required' : 'sometimes';

        return $request->validate([
            'code' => [$locale === null ? 'required' : 'prohibited', 'string', 'max:10', 'regex:/^[a-z]{2,3}(-[A-Z][a-z]{3})?(-[A-Z]{2})?$/', Rule::unique(Locale::class, 'code')],
            'native_name' => [$r, 'string', 'max:64'],
            'direction' => [$r, Rule::in(['ltr', 'rtl'])],
            'calendar' => [$r, Rule::in(['gregorian', 'hijri', 'both'])],
            'digits' => [$r, Rule::in(['western', 'arabic_indic'])],
            'date_format' => [$r, 'string', 'max:32', 'regex:/^[yMdHhmsaEG\/\-\.\s,]+$/'],
            'time_format' => [$r, Rule::in(['12h', '24h'])],
            'number_format' => [$r, 'array'],
            'number_format.decimal' => [$r, Rule::in(['.', ',', '٫'])],
            'number_format.group' => [$locale === null ? 'present' : 'sometimes', 'nullable', Rule::in([',', '.', ' ', '٬', "'", ''])],
            'first_day_of_week' => [$r, 'integer', 'between:0,6'],
            'fallback' => ['sometimes', 'nullable', 'string', Rule::exists(Locale::class, 'code')],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,1000'],
        ]);
    }

    /** @return array<string, mixed> */
    public static function present(Locale $l): array
    {
        return [
            'code' => $l->code,
            'native_name' => $l->native_name,
            'direction' => $l->direction,
            'calendar' => $l->calendar,
            'digits' => $l->digits,
            'date_format' => $l->date_format,
            'time_format' => $l->time_format,
            'number_format' => $l->number_format,
            'first_day_of_week' => $l->first_day_of_week,
            'fallback' => $l->fallback?->code,
            'is_enabled' => $l->is_enabled,
            'is_default' => $l->is_default,
            'sort_order' => $l->sort_order,
        ];
    }
}
