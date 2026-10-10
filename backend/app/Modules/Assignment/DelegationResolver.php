<?php

declare(strict_types=1);

namespace App\Modules\Assignment;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Delegation and cover (specification §4.25, architecture §19.4): while a
 * delegation is active, the delegate may act for the delegator on the
 * delegated forms (all forms when none are listed). Access is the union of
 * both; what the delegate does only through the delegator is recorded as
 * on behalf of the delegator.
 */
final class DelegationResolver
{
    /** @var array<string, list<int>> */
    private array $memo = [];

    /**
     * Ids of the users who delegate to this user for the form right now.
     *
     * @return list<int>
     */
    public function delegatorsOf(User $user, ?int $formId): array
    {
        $key = $user->id.':'.($formId ?? '*');
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.u');
        $rows = DB::table('delegations')->where('delegate_user_id', $user->id)->whereNull('revoked_at')
            ->where('starts_at', '<=', $now)->where('ends_at', '>', $now)
            ->get(['delegator_user_id', 'form_ids']);
        $out = [];
        foreach ($rows as $r) {
            $forms = $r->form_ids === null ? null : (json_decode((string) $r->form_ids, true) ?: []);
            if ($formId === null || $forms === null || in_array($formId, array_map('intval', $forms), true)) {
                $out[] = (int) $r->delegator_user_id;
            }
        }
        $out = array_values(array_unique($out));
        $users = $out === [] ? [] : DB::table('users')->whereIn('id', $out)->where('status', 'active')->whereNull('deleted_at')->pluck('id')->map(static fn ($v) => (int) $v)->all();

        return $this->memo[$key] = $users;
    }

    /**
     * The user and the active delegators, as models.
     *
     * @return list<User>
     */
    public function principalsFor(User $user, ?int $formId): array
    {
        $ids = $this->delegatorsOf($user, $formId);

        return [$user, ...($ids === [] ? [] : User::query()->whereIn('id', $ids)->orderBy('id')->get()->all())];
    }

    public function forget(): void
    {
        $this->memo = [];
    }
}
