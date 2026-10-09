<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\AccessCache;
use App\Modules\Access\RecordScope;
use App\Modules\Access\ScopePredicate;
use App\Modules\Audit\AuditWriter;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Definition\DefinitionCompiler;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\StepUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Record-level rules of a form (specification §4.11 "Record level",
 * architecture §16.6): which records each subject reaches for viewing,
 * editing and deleting. Saved as one document with a concurrency hash; hard
 * denies need step-up confirmation; `explain` shows a user's tier walk.
 */
final class RecordAccessController extends Controller
{
    public function __construct(private readonly OwnedConditions $conditions, private readonly AccessCache $cache, private readonly AuditWriter $audit) {}

    public function show(Form $form): JsonResponse
    {
        Gate::authorize('system.manage_permissions');

        return response()->json(['data' => ['rules' => $this->load($form), 'hash' => DefinitionCompiler::hash($this->load($form))]]);
    }

    public function update(Request $request, Form $form, StepUp $stepUp, DraftRepository $drafts): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate([
            'base_hash' => ['required', 'string', 'size:64'],
            'rules' => ['present', 'array', 'max:500'],
            'rules.*.uuid' => ['required', 'uuid', 'distinct'],
            'rules.*.subject.type' => ['required', Rule::in(['everyone', 'role', 'department', 'user'])],
            'rules.*.subject.uuid' => ['required_unless:rules.*.subject.type,everyone', 'nullable', 'uuid'],
            'rules.*.operation' => ['required', Rule::in(['view', 'edit', 'delete', 'all'])],
            'rules.*.scope' => ['required', Rule::in(RecordScope::SCOPES)],
            'rules.*.condition' => ['nullable', 'array'],
            'rules.*.effect' => ['required', Rule::in(['allow', 'deny', 'hard_deny'])],
            'rules.*.priority' => ['sometimes', 'integer', 'between:0,100000'],
            'confirmation_code' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);
        if (! hash_equals(DefinitionCompiler::hash($this->load($form)), $data['base_hash'])) {
            return response()->json(['message' => __('access.record_rules_changed'), 'code' => 'rules_changed', 'data' => ['rules' => $this->load($form), 'hash' => DefinitionCompiler::hash($this->load($form))]], 409);
        }
        $rules = json_decode((string) json_encode($request->input('rules')), true);
        $existingHard = DB::table('record_access_rules')->where('form_id', $form->id)->where('effect', 'hard_deny')->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->all();
        foreach ($rules as $r) {
            if ($r['effect'] === 'hard_deny' && ! in_array(strtolower($r['uuid']), $existingHard, true)) {
                $stepUp->require($request, $this->user());
                break;
            }
        }
        $draft = $drafts->normalize($drafts->load($form));
        $fieldsByKey = array_column($draft['fields'], null, 'key');
        $errors = [];
        $subjects = [];
        foreach ($rules as $i => $r) {
            if ($r['scope'] === 'custom') {
                if (($r['condition'] ?? null) === null) {
                    $errors["rules.{$i}.condition"][] = __('validation.required', ['attribute' => 'condition']);
                } else {
                    try {
                        ScopePredicate::validate($r['condition'], $fieldsByKey);
                    } catch (InvalidArgumentException $e) {
                        $errors["rules.{$i}.condition"][] = $e->getMessage();
                    }
                }
            } elseif (($r['condition'] ?? null) !== null) {
                $errors["rules.{$i}.condition"][] = __('access.condition_only_custom');
            }
            $subjects[$i] = null;
            if ($r['subject']['type'] !== 'everyone') {
                $subjects[$i] = DB::table(match ($r['subject']['type']) {
                    'role' => 'roles', 'department' => 'departments', default => 'users',
                })->where('uuid', strtolower((string) $r['subject']['uuid']))->value('id');
                if ($subjects[$i] === null) {
                    $errors["rules.{$i}.subject"][] = __('validation.exists', ['attribute' => 'subject']);
                }
            }
            if (DB::table('record_access_rules')->where('uuid', strtolower($r['uuid']))->where('form_id', '!=', $form->id)->exists()) {
                $errors["rules.{$i}.uuid"][] = __('validation.unique', ['attribute' => 'uuid']);
            }
        }
        if ($errors !== []) {
            return response()->json(['message' => __('records.invalid'), 'errors' => $errors], 422);
        }
        $before = $this->load($form);
        DB::transaction(function () use ($form, $rules, $subjects): void {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            $kept = [];
            foreach ($rules as $i => $r) {
                $uuid = strtolower($r['uuid']);
                $existing = DB::table('record_access_rules')->where('form_id', $form->id)->where('uuid', $uuid)->first();
                $row = [
                    'updated_at' => $now, 'updated_by' => Auth::id(), 'subject_type' => $r['subject']['type'], 'subject_id' => $subjects[$i],
                    'operation' => $r['operation'], 'scope' => $r['scope'], 'effect' => $r['effect'], 'priority' => (int) ($r['priority'] ?? $i),
                ];
                if ($existing === null) {
                    $id = (int) DB::table('record_access_rules')->insertGetId($row + ['uuid' => $uuid, 'organization_id' => $form->organization_id, 'created_at' => $now, 'created_by' => Auth::id(), 'form_id' => $form->id, 'condition_id' => null]);
                    $conditionId = null;
                } else {
                    $id = (int) $existing->id;
                    DB::table('record_access_rules')->where('id', $id)->update($row);
                    $conditionId = $existing->condition_id === null ? null : (int) $existing->condition_id;
                }
                $new = $this->conditions->put($form->id, 'record_access_rule', $id, $r['scope'] === 'custom' ? $r['condition'] : null, $conditionId);
                if ($new !== $conditionId) {
                    DB::table('record_access_rules')->where('id', $id)->update(['condition_id' => $new]);
                }
                $kept[] = $id;
            }
            foreach (DB::table('record_access_rules')->where('form_id', $form->id)->whereNotIn('id', $kept ?: [0])->get(['id', 'condition_id']) as $gone) {
                DB::table('record_access_rules')->where('id', $gone->id)->delete();
                $this->conditions->forget($gone->condition_id === null ? null : (int) $gone->condition_id);
            }
        });
        $after = $this->load($form);
        $this->audit->record('access.record_rules_changed', 'access', [['field_key' => 'record_access_rules', 'old' => $before, 'new' => $after]], 'form', $form->id);
        $this->cache->bump();

        return $this->show($form);
    }

    /** A user's record scope for each operation, with the tier walk. */
    public function explain(Request $request, Form $form, RecordScope $scope): JsonResponse
    {
        Gate::authorize('system.manage_permissions');
        $data = $request->validate(['user' => ['required', 'uuid', Rule::exists('users', 'uuid')]]);
        $user = User::query()->where('uuid', strtolower($data['user']))->firstOrFail();
        $out = [];
        foreach (RecordScope::OPERATIONS as $op) {
            $r = $scope->resolve($form->id, $user, $op);
            $out[$op] = ['scopes' => array_keys($r['scopes']), 'custom' => count($r['custom']), 'exclusions' => count($r['exclude']), 'tiers' => $r['trace']];
        }

        return response()->json(['data' => $out]);
    }

    /** @return list<array<string, mixed>> */
    private function load(Form $form): array
    {
        $rows = DB::table('record_access_rules')->where('form_id', $form->id)->orderBy('priority')->orderBy('id')->get();
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());
        $names = [
            'role' => DB::table('roles')->whereIn('id', $rows->where('subject_type', 'role')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
            'department' => DB::table('departments')->whereIn('id', $rows->where('subject_type', 'department')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
            'user' => DB::table('users')->whereIn('id', $rows->where('subject_type', 'user')->pluck('subject_id'))->pluck('uuid', 'id')->all(),
        ];

        return $rows->map(static fn ($r) => [
            'uuid' => strtolower((string) $r->uuid),
            'subject' => ['type' => $r->subject_type, 'uuid' => $r->subject_type === 'everyone' ? null : strtolower((string) ($names[$r->subject_type][$r->subject_id] ?? ''))],
            'operation' => $r->operation, 'scope' => $r->scope, 'effect' => $r->effect, 'priority' => (int) $r->priority,
            'condition' => $r->condition_id === null ? null : ($asts[(int) $r->condition_id] ?? null),
        ])->values()->all();
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
