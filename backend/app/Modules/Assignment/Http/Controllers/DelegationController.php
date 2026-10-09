<?php

declare(strict_types=1);

namespace App\Modules\Assignment\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Access\EscalationGuard;
use App\Modules\Assignment\DelegationResolver;
use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Delegation and cover (specification §4.25): a user delegates their own
 * work for a period with a reason (Delegate Own Work); an administrator sets
 * delegations and out-of-office cover for others (Manage Delegation), and
 * only for users whose permissions they hold themselves. Delegations can be
 * limited to forms and are revoked, never deleted.
 */
final class DelegationController extends Controller
{
    public function __construct(private readonly AccessResolver $access, private readonly AuditWriter $audit, private readonly DelegationResolver $resolver) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['scope' => ['sometimes', Rule::in(['mine', 'all'])]]);
        $user = $this->user();
        $all = ($data['scope'] ?? 'mine') === 'all';
        abort_if($all && ! $this->access->allows($user, 'system.manage_delegation'), 403);
        $q = DB::table('delegations')->orderByDesc('starts_at')->limit(500);
        if (! $all) {
            $q->where(static fn ($w) => $w->where('delegator_user_id', $user->id)->orWhere('delegate_user_id', $user->id));
        }
        $rows = $q->get();
        $users = DB::table('users')->whereIn('id', [...$rows->pluck('delegator_user_id'), ...$rows->pluck('delegate_user_id')])->get(['id', 'uuid', 'name'])->keyBy('id');
        $forms = DB::table('forms')->get(['id', 'uuid', 'key'])->keyBy('id');

        return response()->json(['data' => $rows->map(fn ($d) => [
            'uuid' => strtolower((string) $d->uuid),
            'type' => $d->type,
            'delegator' => ['uuid' => strtolower((string) ($users[$d->delegator_user_id]->uuid ?? '')), 'name' => $users[$d->delegator_user_id]->name ?? null],
            'delegate' => ['uuid' => strtolower((string) ($users[$d->delegate_user_id]->uuid ?? '')), 'name' => $users[$d->delegate_user_id]->name ?? null],
            'starts_at' => Carbon::parse($d->starts_at, 'UTC')->toIso8601ZuluString(),
            'ends_at' => Carbon::parse($d->ends_at, 'UTC')->toIso8601ZuluString(),
            'reason' => $d->reason,
            'forms' => $d->form_ids === null ? null : array_values(array_filter(array_map(static fn ($id) => isset($forms[$id]) ? ['uuid' => strtolower((string) $forms[$id]->uuid), 'key' => $forms[$id]->key] : null, json_decode((string) $d->form_ids, true) ?: []))),
            'status' => self::status($d),
        ])->values()]);
    }

    public function store(Request $request, EscalationGuard $guard): JsonResponse
    {
        $data = $request->validate([
            'delegator' => ['sometimes', 'nullable', 'uuid'],
            'delegate' => ['required', 'uuid'],
            'type' => ['required', Rule::in(['delegation', 'out_of_office'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at', 'after:now'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'forms' => ['sometimes', 'nullable', 'array', 'max:200'],
            'forms.*' => ['uuid'],
        ]);
        $actor = $this->user();
        $delegator = isset($data['delegator']) ? User::query()->where('uuid', strtolower($data['delegator']))->firstOrFail() : $actor;
        $delegate = User::query()->where('uuid', strtolower($data['delegate']))->where('status', 'active')->firstOrFail();
        $forSelf = $delegator->id === $actor->id && $data['type'] === 'delegation';
        if ($forSelf) {
            abort_unless($this->access->allows($actor, 'system.delegate_own_work') || $this->access->allows($actor, 'system.manage_delegation'), 403, __('records.forbidden'));
        } else {
            abort_unless($this->access->allows($actor, 'system.manage_delegation'), 403, __('records.forbidden'));
            // An administrator never hands out more than they hold (specification §4.11 safeguards).
            if ($delegator->id !== $actor->id) {
                $guard->assertCanManage($actor, $delegator);
            }
        }
        abort_if($delegate->id === $delegator->id, 422, __('assignment.self_delegation'));
        $formIds = null;
        if (isset($data['forms'])) {
            $formIds = DB::table('forms')->whereIn('uuid', array_map('strtolower', $data['forms']))->pluck('id')->map(static fn ($v) => (int) $v)->all();
            abort_if(count($formIds) !== count(array_unique($data['forms'])), 422, __('validation.exists', ['attribute' => 'forms']));
        }
        $now = Carbon::now('UTC');
        $starts = Carbon::parse($data['starts_at'])->utc();
        $uuid = (string) Str::uuid7();
        DB::table('delegations')->insert([
            'uuid' => $uuid, 'organization_id' => $actor->organization_id, 'created_at' => $now->format('Y-m-d H:i:s.u'), 'updated_at' => $now->format('Y-m-d H:i:s.u'),
            'created_by' => $actor->id, 'updated_by' => $actor->id, 'delegator_user_id' => $delegator->id, 'delegate_user_id' => $delegate->id,
            'type' => $data['type'], 'starts_at' => $starts->format('Y-m-d H:i:s.u'), 'ends_at' => Carbon::parse($data['ends_at'])->utc()->format('Y-m-d H:i:s.u'),
            'reason' => $data['reason'], 'form_ids' => $formIds === null ? null : json_encode($formIds),
            'status' => $starts->isFuture() ? 'scheduled' : 'active', 'revoked_at' => null, 'revoked_by' => null,
        ]);
        $this->audit->record('delegation.created', 'access', null, 'user', $delegator->id, ['delegation' => $uuid, 'delegate' => $delegate->uuid, 'type' => $data['type'], 'forms' => $formIds], $actor->id, $delegator->id);
        $this->resolver->forget();

        return response()->json(['data' => ['uuid' => $uuid]], 201);
    }

    /** Revokes a delegation (the delegator, the delegate's administrator, or a holder of Manage Delegation). */
    public function destroy(string $delegation): JsonResponse
    {
        $row = DB::table('delegations')->where('uuid', strtolower($delegation))->first();
        abort_if($row === null, 404);
        $actor = $this->user();
        abort_unless((int) $row->delegator_user_id === $actor->id || $this->access->allows($actor, 'system.manage_delegation'), 403, __('records.forbidden'));
        if ($row->revoked_at === null) {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
            DB::table('delegations')->where('id', $row->id)->update(['revoked_at' => $now, 'revoked_by' => $actor->id, 'status' => 'revoked', 'updated_at' => $now, 'updated_by' => $actor->id]);
            $this->audit->record('delegation.revoked', 'access', null, 'user', (int) $row->delegator_user_id, ['delegation' => strtolower((string) $row->uuid)], $actor->id, (int) $row->delegator_user_id);
        }

        return response()->json(null, 204);
    }

    /** Refreshes the stored status of delegations from their dates (scheduler). */
    public static function refreshStatuses(): int
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $n = DB::table('delegations')->whereNull('revoked_at')->where('status', 'scheduled')->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->update(['status' => 'active', 'updated_at' => $now]);

        return $n + DB::table('delegations')->whereNull('revoked_at')->whereIn('status', ['scheduled', 'active'])->where('ends_at', '<=', $now)->update(['status' => 'expired', 'updated_at' => $now]);
    }

    private static function status(object $d): string
    {
        if ($d->revoked_at !== null) {
            return 'revoked';
        }
        $now = Carbon::now('UTC');

        return match (true) {
            Carbon::parse($d->ends_at, 'UTC')->lte($now) => 'expired',
            Carbon::parse($d->starts_at, 'UTC')->gt($now) => 'scheduled',
            default => 'active',
        };
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
