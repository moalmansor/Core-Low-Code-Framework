<?php

declare(strict_types=1);

namespace App\Modules\Assignment\Http\Controllers;

use App\Infrastructure\Database\Contracts\DatabaseDriver;
use App\Modules\Access\AccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pickers for users, roles and departments on the Phase 3 configuration
 * screens (approvers, assignees, rule subjects, shares, delegates): names and
 * uuids only, for whoever configures or assigns work. Account administration
 * stays behind Manage Users.
 */
final class SubjectOptionsController extends Controller
{
    private const ALLOWED = [
        'system.manage_forms', 'system.manage_permissions', 'system.manage_justification_rules', 'system.manage_delegation',
        'system.delegate_own_work', 'system.assign_records', 'system.reassign_records',
    ];

    public function __invoke(Request $request, AccessResolver $access, Translator $translator): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        // Sharing a saved view needs only a signed-in user, so roles, departments and users are listed for everyone signed in;
        // the listing carries names only.
        $privileged = array_filter(self::ALLOWED, static fn ($p) => $access->allows($user, $p)) !== [];
        $data = $request->validate([
            'type' => ['required', Rule::in(['user', 'role', 'department'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'uuids' => ['sometimes', 'array', 'max:100'],
            'uuids.*' => ['uuid'],
        ]);
        $term = trim((string) ($data['search'] ?? ''));
        if ($data['type'] === 'user') {
            // Without a configuration permission the directory is searched, never listed: at least 3 characters, 10 names.
            if (! $privileged && mb_strlen($term) < 3 && ! isset($data['uuids'])) {
                return response()->json(['data' => []]);
            }
            $q = DB::table('users')->whereNull('deleted_at')->where('status', 'active');
            if ($term !== '') {
                $driver = app(DatabaseDriver::class);
                $q->where(static fn ($w) => $w->where(static fn ($x) => $driver->caseInsensitiveLike($x, 'name', $term))->orWhere(static fn ($x) => $driver->caseInsensitiveLike($x, 'email', $term)));
            }
            if (isset($data['uuids'])) {
                $q->whereIn('uuid', array_map('strtolower', $data['uuids']));
            }
            $rows = $q->orderBy('name')->limit($privileged ? 25 : 10)->get(['uuid', 'name', 'email']);

            return response()->json(['data' => $rows->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'name' => $r->name, 'detail' => $privileged ? $r->email : null])->values()]);
        }
        $table = $data['type'] === 'role' ? 'roles' : 'departments';
        $q = DB::table($table);
        if ($table === 'departments') {
            $q->whereNull('deleted_at');
        }
        if (isset($data['uuids'])) {
            $q->whereIn('uuid', array_map('strtolower', $data['uuids']));
        }
        $rows = $q->get(['id', 'uuid', $table === 'roles' ? 'key' : 'code']);
        $names = $translator->many($data['type'], $rows->pluck('id')->map(static fn ($v) => (int) $v)->all(), ['name']);
        $out = $rows->map(static fn ($r) => ['uuid' => strtolower((string) $r->uuid), 'name' => $names[(int) $r->id]['name'] ?? Translator::humanize((string) ($r->key ?? $r->code)), 'detail' => $r->key ?? $r->code])
            ->filter(static fn ($o) => $term === '' || str_contains(mb_strtolower($o['name'].' '.$o['detail']), mb_strtolower($term)))
            ->sortBy('name')->take(50)->values();

        return response()->json(['data' => $out]);
    }
}
