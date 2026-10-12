<?php

declare(strict_types=1);

namespace App\Modules\Downloads\Http\Controllers;

use App\Modules\Access\AccessResolver;
use App\Modules\Downloads\DownloadProfiles;
use App\Modules\Downloads\DownloadSchedules;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Scheduled downloads of a profile: managed by whoever may edit the
 * profile (a personal profile's schedule only ever reaches its owner).
 */
final class DownloadScheduleController extends Controller
{
    public function __construct(private readonly DownloadSchedules $schedules, private readonly DownloadProfiles $profiles, private readonly AccessResolver $access) {}

    public function index(string $profile): JsonResponse
    {
        return response()->json(['data' => $this->schedules->list($this->profile($profile))]);
    }

    public function store(Request $request, string $profile): JsonResponse
    {
        $p = $this->profile($profile);
        $s = $this->schedules->save($p, null, (array) $request->validate(['schedule' => ['required', 'array']])['schedule'], $this->user());

        return response()->json(['data' => $this->schedules->present($s)], 201);
    }

    public function update(Request $request, string $schedule): JsonResponse
    {
        [$s, $p] = $this->schedule($schedule);
        $saved = $this->schedules->save($p, $s, (array) $request->validate(['schedule' => ['required', 'array']])['schedule'], $this->user());

        return response()->json(['data' => $this->schedules->present($saved)]);
    }

    public function destroy(string $schedule): JsonResponse
    {
        [$s] = $this->schedule($schedule);
        $this->schedules->delete($s, $this->user());

        return response()->json(null, 204);
    }

    /** @return array{0: object, 1: object} */
    private function schedule(string $uuid): array
    {
        $s = DB::table('download_schedules')->where('uuid', strtolower($uuid))->first();
        abort_if($s === null, 404);
        $p = DB::table('download_profiles')->where('id', $s->download_profile_id)->first();
        abort_if($p === null, 404);

        return [$s, $this->profile((string) $p->uuid)];
    }

    private function profile(string $uuid): object
    {
        $p = $this->profiles->row($uuid);
        abort_if($p === null, 404);
        $u = $this->user();
        $ok = $p->is_personal
            ? (int) $p->owner_user_id === $u->id && $this->access->allows($u, 'system.create_personal_download_profiles')
            : $this->access->allows($u, 'system.manage_download_profiles');
        abort_unless($ok, $p->is_personal ? 404 : 403);

        return $p;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
