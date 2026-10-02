<?php

declare(strict_types=1);

namespace App\Modules\Identity\Sessions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/** Ends database sessions (active session management, specification §5). */
final class SessionRevoker
{
    public function revoke(string $sessionId, int $userId): bool
    {
        return DB::table('sessions')->where('id', $sessionId)->where('user_id', $userId)->delete() > 0;
    }

    public function revokeAllFor(int $userId, bool $exceptCurrent = false): int
    {
        $query = DB::table('sessions')->where('user_id', $userId);
        if ($exceptCurrent && Session::isStarted()) {
            $query->where('id', '!=', Session::getId());
        }

        return $query->delete();
    }
}
