<?php

declare(strict_types=1);

namespace App\Modules\Justification\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Justification\JustificationPresenter;
use App\Modules\Justification\JustificationRules;
use App\Modules\Justification\Models\ReasonCode;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\FormRuntimes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Justification administration and display (specification §4.24, architecture
 * §21 Justification): a form's rules (Manage Justification Rules), the reason
 * code lists, and a record's justifications (View Justifications, within the
 * record's scope). There is no endpoint that edits or deletes a saved
 * justification.
 */
final class JustificationController extends Controller
{
    public function __construct(private readonly JustificationRules $rules, private readonly Translator $translator) {}

    public function rules(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_justification_rules');

        return response()->json(['data' => ['rules' => $this->rules->load($form), 'hash' => $this->rules->hash($form)]]);
    }

    public function saveRules(Request $request, Form $form): JsonResponse
    {
        Gate::authorize('system.manage_justification_rules');
        $data = $request->validate(['rules' => ['present', 'array'], 'base_hash' => ['required', 'string', 'size:64']]);
        if (! hash_equals($this->rules->hash($form), $data['base_hash'])) {
            return response()->json(['message' => __('justification.changed_elsewhere'), 'code' => 'rules_changed', 'data' => ['rules' => $this->rules->load($form), 'hash' => $this->rules->hash($form)]], 409);
        }
        $rules = json_decode((string) json_encode($request->input('rules')), true);
        foreach ($rules as &$r) {
            if (is_array($r)) {
                foreach (['uuid', 'target'] as $k) {
                    $r[$k] = is_string($r[$k] ?? null) ? strtolower($r[$k]) : ($r[$k] ?? null);
                }
            }
        }
        unset($r);
        $this->rules->save($form, array_values($rules), (int) Auth::id());

        return $this->rules($form);
    }

    public function codes(Request $request): JsonResponse
    {
        $data = $request->validate(['set' => ['sometimes', 'nullable', 'string', 'max:48']]);
        abort_unless($this->user()->can('system.manage_justification_rules') || isset($data['set']), 403);
        $q = ReasonCode::query()->orderBy('set_key')->orderBy('sort_order')->orderBy('id');
        if (isset($data['set'])) {
            $q->where('set_key', $data['set']);
        }

        return response()->json(['data' => $q->get()->map(fn (ReasonCode $c) => $this->code($c))->values()]);
    }

    public function storeCode(Request $request, AuditWriter $audit): JsonResponse
    {
        Gate::authorize('system.manage_justification_rules');
        $data = $request->validate($this->codeRules() + [
            'set_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/'],
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_\-]{1,48}$/', Rule::unique('justification_reason_codes', 'code')->where('set_key', $request->input('set_key'))],
        ]);
        $code = DB::transaction(function () use ($data, $audit): ReasonCode {
            $code = ReasonCode::query()->create([
                'set_key' => $data['set_key'], 'code' => $data['code'], 'requires_note' => $data['requires_note'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $data['is_active'] ?? true,
            ]);
            $code->setTranslations('label', $data['label']);
            $audit->record('justification.reason_code_created', 'config', null, 'justification_reason_code', $code->id, ['set' => $code->set_key, 'code' => $code->code]);

            return $code;
        });

        return response()->json(['data' => $this->code($code)], 201);
    }

    public function updateCode(Request $request, string $code, AuditWriter $audit): JsonResponse
    {
        Gate::authorize('system.manage_justification_rules');
        $row = ReasonCode::query()->where('uuid', strtolower($code))->firstOrFail();
        $data = $request->validate(array_map(static fn (array $r) => ['sometimes', ...array_diff($r, ['required'])], $this->codeRules()) + ['base_updated_at' => ['required', 'string', 'max:40']]);
        if ($this->code($row)['updated_at'] !== $data['base_updated_at']) {
            return response()->json(['message' => __('justification.code_changed'), 'code' => 'reason_code_changed', 'data' => $this->code($row)], 409);
        }
        DB::transaction(function () use ($row, $data, $audit): void {
            $row->fill(array_intersect_key($data, array_flip(['requires_note', 'sort_order', 'is_active'])))->save();
            if (isset($data['label'])) {
                $row->setTranslations('label', $data['label']);
            }
            $audit->record('justification.reason_code_updated', 'config', null, 'justification_reason_code', $row->id, ['set' => $row->set_key, 'code' => $row->code]);
        });

        return response()->json(['data' => $this->code($row->refresh())]);
    }

    /** A record's justifications, newest first. */
    public function forRecord(Form $form, string $record, FormRuntimes $runtimes, RecordScope $scope, JustificationPresenter $presenter, AccessResolver $access): JsonResponse
    {
        Gate::authorize('system.view_justifications');
        $rt = $runtimes->forForm($form);
        abort_if($rt === null || ! $access->allows($this->user(), "form.{$form->uuid}.view"), 404);
        $id = DB::table($rt->table)->where('uuid', strtolower($record))->value('id');
        abort_if($id === null || ! $scope->allows($rt, $this->user(), 'view', (int) $id), 404);
        $rows = DB::table('justifications')->where('form_id', $form->id)->where('record_id', $id)->orderByDesc('id')->limit(200)->get()->all();
        $hidden = $this->hiddenKeys($rt, $form);

        return response()->json(['data' => array_values(array_map(static function (array $j) use ($hidden): array {
            $j['changed_fields'] = array_values(array_filter($j['changed_fields'], static fn ($c) => ! isset($hidden[$c['field'] ?? ''])));

            return $j;
        }, $presenter->present($rows)))]);
    }

    /** @return array<string, true> */
    private function hiddenKeys(FormRuntime $rt, Form $form): array
    {
        $levels = app(FieldAccessResolver::class)->resolve($this->user(), $form->id, $form->uuid, $rt->definition, 'view');
        $out = [];
        foreach ($levels['fields'] as $uuid => $l) {
            if ($l === 'hidden' && isset($rt->fields[$uuid])) {
                $out[$rt->fields[$uuid]['key']] = true;
            }
        }

        return $out;
    }

    /** @return array<string, list<mixed>> */
    private function codeRules(): array
    {
        $default = $this->translator->defaultLocale();

        return [
            'label' => ['required', 'array'],
            'label.'.$default => ['required', 'string', 'max:255'],
            'label.*' => ['nullable', 'string', 'max:255'],
            'requires_note' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function code(ReasonCode $c): array
    {
        return [
            'uuid' => strtolower($c->uuid), 'set_key' => $c->set_key, 'code' => $c->code, 'label' => $c->translate('label') ?? Translator::humanize((string) $c->code),
            'labels' => $c->translationsFor('label'), 'requires_note' => $c->requires_note, 'sort_order' => $c->sort_order, 'is_active' => $c->is_active,
            'updated_at' => $c->updated_at === null ? null : Carbon::parse($c->updated_at)->format('Y-m-d\\TH:i:s.u\\Z'),
        ];
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
