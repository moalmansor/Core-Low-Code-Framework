<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Experience\Announcements;
use App\Modules\Experience\Models\Announcement;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Announcements and banners (specification §4.29, ADR-0054); publishing them needs "Publish announcements". */
final class AnnouncementController extends Controller
{
    public function __construct(private readonly Announcements $announcements) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.publish_announcements');

        return response()->json(['data' => Announcement::query()->orderByDesc('starts_at')->orderByDesc('id')->get()
            ->map(fn (Announcement $a) => $this->announcements->present($a))->values()->all()]);
    }

    public function show(string $announcement): JsonResponse
    {
        Gate::authorize('system.publish_announcements');

        return response()->json(['data' => $this->announcements->present($this->find($announcement))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.publish_announcements');
        $a = $this->announcements->save(null, $this->announcements->check((array) $request->input('announcement', [])), $this->user());

        return response()->json(['data' => $this->announcements->present($a)], 201);
    }

    public function update(Request $request, string $announcement): JsonResponse
    {
        Gate::authorize('system.publish_announcements');
        $a = $this->find($announcement);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->announcements->present($a)['hash'], (string) $request->input('base_hash')), 409, __('experience.changed_meanwhile'));
        $a = $this->announcements->save($a, $this->announcements->check((array) $request->input('announcement', [])), $this->user());

        return response()->json(['data' => $this->announcements->present($a)]);
    }

    public function destroy(string $announcement): JsonResponse
    {
        Gate::authorize('system.publish_announcements');
        $this->announcements->delete($this->find($announcement), $this->user());

        return response()->json(null, 204);
    }

    /** What the signed-in person sees now, optionally for one placement. */
    public function active(Request $request): JsonResponse
    {
        $data = $request->validate(['placement' => ['sometimes', 'nullable', Rule::in(Announcements::PLACEMENTS)]]);

        return response()->json(['data' => $this->announcements->active($this->user(), $data['placement'] ?? null)]);
    }

    public function dismiss(string $announcement): JsonResponse
    {
        $a = $this->find($announcement);
        // Only what the person can see now can be dismissed.
        abort_unless(in_array(strtolower($a->uuid), array_column($this->announcements->active($this->user(), null), 'uuid'), true), 404);
        $this->announcements->dismiss($a, $this->user());

        return response()->json(null, 204);
    }

    private function find(string $uuid): Announcement
    {
        $a = Str::isUuid($uuid) ? Announcement::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($a === null, 404);

        return $a;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
