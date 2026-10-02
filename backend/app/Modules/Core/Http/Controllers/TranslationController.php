<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\TranslatableRegistry;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Translation manager (specification §3): browse and edit every translatable
 * text — interface strings and object labels — per locale, and list what is
 * still untranslated.
 */
final class TranslationController extends Controller
{
    public function __construct(
        private readonly Translator $translator,
        private readonly TranslatableRegistry $registry,
    ) {}

    public function types(): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $types = [['type' => Translator::UI_TYPE, 'label' => 'translations.type.ui', 'fields' => ['text']]];
        foreach ($this->registry->all() as $type => $info) {
            $types[] = ['type' => $type, 'label' => $info['label'], 'fields' => $info['fields']];
        }

        return response()->json(['data' => $types]);
    }

    /**
     * Rows for one object type: source text in the default locale next to the
     * target locale's value; `untranslated=1` keeps only rows without a value.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'locale' => ['required', 'string', Rule::exists(Locale::class, 'code')],
            'untranslated' => ['sometimes', 'boolean'],
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,200'],
        ]);
        $source = (string) (Locale::query()->where('is_default', true)->value('code') ?? 'en');
        $rows = $data['type'] === Translator::UI_TYPE ? $this->uiRows($source, $data['locale']) : $this->objectRows($data['type'], $source, $data['locale']);
        if ($data['untranslated'] ?? false) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => $r['value'] === null || $r['value'] === ''));
        }
        if (! empty($data['search'])) {
            $needle = mb_strtolower($data['search']);
            $rows = array_values(array_filter($rows, static fn (array $r): bool => str_contains(mb_strtolower($r['key'].' '.$r['source'].' '.$r['value']), $needle)));
        }
        $perPage = (int) ($data['per_page'] ?? 50);
        $page = (int) ($data['page'] ?? 1);

        return response()->json([
            'data' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total' => count($rows),
            'page' => $page,
            'per_page' => $perPage,
            'source_locale' => $source,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::exists(Locale::class, 'code')],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.type' => ['required', 'string', 'max:64'],
            'items.*.object' => ['nullable', 'uuid'],
            'items.*.key' => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'items.*.field' => ['required', 'string', 'max:64'],
            'items.*.value' => ['present', 'nullable', 'string', 'max:10000'],
        ]);
        $known = array_keys($this->translator->bundledUi($this->translator->defaultLocale()));
        DB::transaction(function () use ($data, $known): void {
            $changes = [];
            foreach ($data['items'] as $item) {
                if ($item['type'] === Translator::UI_TYPE) {
                    abort_if(empty($item['key']) || ! in_array($item['key'], $known, true), 422, __('validation.in', ['attribute' => 'key']));
                    $old = $this->translator->uiOverrides($data['locale'])[$item['key']] ?? null;
                    $this->translator->putMany(Translator::UI_TYPE, 0, $item['key'], [$data['locale'] => $item['value']]);
                    $changes[] = ['field_key' => "ui:{$item['key']}:{$data['locale']}", 'old' => $old, 'new' => $item['value']];

                    continue;
                }
                $info = $this->registry->get($item['type']);
                abort_if($info === null || ! in_array($item['field'], $info['fields'], true), 422, __('validation.in', ['attribute' => 'field']));
                // Objects are addressed by UUID, or by key where the table has none (permissions).
                $model = ! empty($item['object'])
                    ? $info['model']::query()->where('uuid', $item['object'])->firstOrFail()
                    : $info['model']::query()->where('key', (string) $item['key'])->firstOrFail();
                $old = $this->translator->all($item['type'], (int) $model->getKey(), $item['field'])[$data['locale']] ?? null;
                $this->translator->putMany($item['type'], (int) $model->getKey(), $item['field'], [$data['locale'] => $item['value']]);
                $changes[] = ['field_key' => "{$item['type']}:".($item['object'] ?? $item['key']).":{$item['field']}:{$data['locale']}", 'old' => $old, 'new' => $item['value']];
            }
            app(AuditWriter::class)->record('config.translations_changed', 'config', changes: $changes, meta: ['locale' => $data['locale'], 'items' => count($data['items'])]);
        });

        return response()->json(['data' => ['saved' => count($data['items'])]]);
    }

    /** Export one locale's interface strings and labels as JSON. */
    public function export(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $locale = $request->validate(['locale' => ['required', 'string', Rule::exists(Locale::class, 'code')]])['locale'];

        return response()->json(['data' => [
            'format' => 'lcf.translations/v1',
            'locale' => $locale,
            'ui' => $this->translator->uiOverrides($locale) + $this->translator->bundledUi($locale),
        ]]);
    }

    /** Import interface strings for a locale (labels of objects are edited in place). */
    public function import(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_translations');
        $data = $request->validate([
            'format' => ['required', Rule::in(['lcf.translations/v1'])],
            'locale' => ['required', 'string', Rule::exists(Locale::class, 'code')],
            'ui' => ['required', 'array', 'max:5000'],
            'ui.*' => ['nullable', 'string', 'max:10000'],
        ]);
        $known = array_keys($this->translator->bundledUi((string) (Locale::query()->where('is_default', true)->value('code') ?? 'en')));
        $count = 0;
        DB::transaction(function () use ($data, $known, &$count): void {
            foreach ($data['ui'] as $key => $value) {
                if (in_array($key, $known, true)) {
                    $this->translator->putMany(Translator::UI_TYPE, 0, (string) $key, [$data['locale'] => $value]);
                    $count++;
                }
            }
            app(AuditWriter::class)->record('config.translations_imported', 'config', meta: ['locale' => $data['locale'], 'items' => $count]);
        });

        return response()->json(['data' => ['imported' => $count]]);
    }

    /** @return list<array{type: string, object: null, key: string, field: string, source: string|null, value: string|null}> */
    private function uiRows(string $source, string $locale): array
    {
        $sourceStrings = $this->translator->bundledUi($source);
        $values = $this->translator->bundledUi($locale);
        $overrides = $this->translator->uiOverrides($locale);
        $rows = [];
        foreach ($sourceStrings as $key => $text) {
            $rows[] = ['type' => Translator::UI_TYPE, 'object' => null, 'key' => $key, 'field' => 'text', 'source' => $text, 'value' => $overrides[$key] ?? $values[$key] ?? null];
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function objectRows(string $type, string $source, string $locale): array
    {
        $info = $this->registry->get($type);
        abort_if($info === null, 404);
        $models = $info['model']::query()->get();
        $ids = $models->map(static fn ($m): int => (int) $m->getKey())->all();
        $translations = Translation::query()->where('object_type', $type)->whereIn('object_id', $ids)->whereIn('locale', [$source, $locale])->get();
        $index = [];
        foreach ($translations as $t) {
            $index[$t->object_id][$t->field][$t->locale] = $t->value;
        }
        $rows = [];
        foreach ($models as $model) {
            foreach ($info['fields'] as $field) {
                $sourceText = $index[$model->getKey()][$field][$source] ?? null;
                if ($sourceText === null && $field !== $info['fields'][0]) {
                    continue; // optional fields (e.g. descriptions) only when they have source text
                }
                $rows[] = [
                    'type' => $type,
                    'object' => $model->getAttribute('uuid'),
                    'key' => (string) ($model->getAttribute('key') ?? $model->getAttribute('code') ?? $model->getKey()),
                    'field' => $field,
                    'source' => $sourceText,
                    'value' => $index[$model->getKey()][$field][$locale] ?? null,
                ];
            }
        }

        return $rows;
    }
}
