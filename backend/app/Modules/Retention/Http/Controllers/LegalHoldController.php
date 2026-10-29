<?php

declare(strict_types=1);

namespace App\Modules\Retention\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\ScopePredicate;
use App\Modules\Core\I18n\Translator;
use App\Modules\Forms\Conditions\OwnedConditions;
use App\Modules\Forms\Draft\DraftRepository;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Retention\LegalHolds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Legal holds (specification §4.26): placed and lifted with a reason; everything needs "Apply legal hold". */
final class LegalHoldController extends Controller
{
    public function __construct(private readonly LegalHolds $holds, private readonly FormRuntimes $runtimes, private readonly OwnedConditions $conditions) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.apply_legal_hold');
        $data = $request->validate(['form' => ['sometimes', 'nullable', 'uuid'], 'active' => ['sometimes', 'boolean']]);
        $q = DB::table('legal_holds')->orderByDesc('id')->limit(500);
        if (($data['form'] ?? null) !== null) {
            $q->where('form_id', Form::query()->where('uuid', strtolower($data['form']))->value('id') ?? 0);
        }
        if (array_key_exists('active', $data)) {
            (bool) $data['active'] ? $q->whereNull('lifted_at') : $q->whereNotNull('lifted_at');
        }
        $rows = $q->get();
        $forms = Form::query()->whereIn('id', $rows->pluck('form_id')->all() ?: [0])->get()->keyBy('id');
        $users = DB::table('users')->whereIn('id', [...$rows->pluck('created_by')->filter(), ...$rows->pluck('lifted_by')->filter()] ?: [0])->pluck('name', 'id');
        $asts = $this->conditions->many($rows->pluck('condition_id')->all());

        return response()->json(['data' => $rows->map(function ($h) use ($forms, $users, $asts) {
            $form = $forms[$h->form_id] ?? null;
            $rt = $form === null ? null : $this->runtimes->forForm($form);
            $record = $h->record_id === null || $rt === null ? null : DB::table($rt->table)->where('id', $h->record_id)->first(['uuid', 'record_number']);

            return [
                'uuid' => strtolower((string) $h->uuid),
                'form' => $form === null ? null : ['uuid' => strtolower($form->uuid), 'label' => $form->translate('name') ?? Translator::humanize($form->key)],
                'record' => $record === null ? null : ['uuid' => strtolower((string) $record->uuid), 'number' => $record->record_number],
                'condition' => $h->condition_id === null ? null : ($asts[(int) $h->condition_id] ?? null),
                'reason' => $h->reason, 'placed_at' => self::iso($h->placed_at), 'placed_by' => $users[$h->created_by] ?? null,
                'lifted_at' => self::iso($h->lifted_at), 'lifted_by' => $h->lifted_by === null ? null : ($users[$h->lifted_by] ?? null), 'lift_reason' => $h->lift_reason,
                'held' => $rt === null || $h->lifted_at !== null ? 0 : ($h->record_id !== null ? 1 : null),
            ];
        })->values()->all()]);
    }

    public function store(Request $request, DraftRepository $drafts): JsonResponse
    {
        Gate::authorize('system.apply_legal_hold');
        $data = $request->validate([
            'form' => ['required', 'uuid'], 'record' => ['sometimes', 'nullable', 'uuid'], 'condition' => ['sometimes', 'nullable', 'array'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $form = Form::query()->where('uuid', strtolower($data['form']))->first();
        $rt = $form === null ? null : $this->runtimes->forForm($form);
        if ($rt === null) {
            throw ValidationException::withMessages(['form' => __('retention.bad_form')]);
        }
        $recordId = null;
        $condition = null;
        if (($data['record'] ?? null) !== null) {
            $recordId = DB::table($rt->table)->where('uuid', strtolower($data['record']))->value('id');
            if ($recordId === null) {
                throw ValidationException::withMessages(['record' => __('records.not_found')]);
            }
        } elseif (($data['condition'] ?? null) !== null) {
            $condition = json_decode((string) json_encode($data['condition']), true);
            $draft = $drafts->normalize($drafts->load($form));
            try {
                ScopePredicate::validate($condition, array_column($draft['fields'], null, 'key'));
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['condition' => $e->getMessage()]);
            }
        } else {
            throw ValidationException::withMessages(['record' => __('retention.hold_scope_required')]);
        }
        /** @var User $user */
        $user = Auth::user();
        $id = $this->holds->place($rt, $recordId === null ? null : (int) $recordId, $condition, trim($data['reason']), $user);

        return response()->json(['data' => ['uuid' => strtolower((string) DB::table('legal_holds')->where('id', $id)->value('uuid')), 'held' => DB::table($rt->table)->where('legal_hold', true)->count()]], 201);
    }

    public function lift(Request $request, string $hold): JsonResponse
    {
        Gate::authorize('system.apply_legal_hold');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $h = Str::isUuid($hold) ? DB::table('legal_holds')->where('uuid', strtolower($hold))->first() : null;
        abort_if($h === null, 404);
        if ($h->lifted_at !== null) {
            throw ValidationException::withMessages(['hold' => __('retention.hold_already_lifted')]);
        }
        /** @var User $user */
        $user = Auth::user();
        $this->holds->lift($h, trim($data['reason']), $user);

        return response()->json(['data' => ['lifted' => true]]);
    }

    private static function iso(mixed $v): ?string
    {
        return $v === null ? null : Carbon::parse((string) $v, 'UTC')->toIso8601ZuluString();
    }
}
