<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\External\Models\ExternalForm;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Submission limits of external forms (architecture §19.12): counts per
 * visitor address or verified address per hour in `submission_throttles`,
 * keyed by a hash so no address is stored. Old windows are removed by the
 * daily clean-up.
 */
final class Throttles
{
    /** Counts one submission; false when the limit for the hour is already reached. */
    public function take(ExternalForm $x, string $type, string $key, int $limit): bool
    {
        $window = Carbon::now('UTC')->startOfHour()->format('Y-m-d H:i:s.u');
        $hash = hash('sha256', $type.'|'.$key);
        $where = ['external_form_id' => $x->id, 'key_type' => $type, 'key_hash' => $hash, 'window_start' => $window];
        $taken = DB::table('submission_throttles')->where($where)->where('count', '<', $limit)->update(['count' => DB::raw('count + 1')]);
        if ($taken > 0) {
            return true;
        }
        if (DB::table('submission_throttles')->where($where)->exists()) {
            return false;
        }
        try {
            DB::table('submission_throttles')->insert($where + ['count' => 1]);

            return true;
        } catch (QueryException) {
            // Another submission created the window at the same moment.
            return DB::table('submission_throttles')->where($where)->where('count', '<', $limit)->update(['count' => DB::raw('count + 1')]) > 0;
        }
    }

    /** Removes windows older than a day. */
    public function prune(): int
    {
        return DB::table('submission_throttles')->where('window_start', '<', Carbon::now('UTC')->subDay()->format('Y-m-d H:i:s.u'))->delete();
    }
}
