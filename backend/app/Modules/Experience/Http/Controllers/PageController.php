<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Maintenance;
use App\Modules\Experience\Models\Page;
use App\Modules\Experience\Pages;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Custom pages (specification §4.29, ADR-0054). Building them needs "Manage
 * pages and menus"; opening one needs `page.{uuid}.view` and the permission
 * of what the page shows.
 */
final class PageController extends Controller
{
    public function __construct(private readonly Pages $pages, private readonly Translator $translator) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $data = $request->validate(['application' => ['sometimes', 'nullable', 'uuid']]);
        $apps = Application::query()->pluck('uuid', 'id');
        $q = Pages::live()->orderBy('key');
        if (isset($data['application'])) {
            $q->where('application_id', (int) Application::query()->where('uuid', strtolower($data['application']))->value('id'));
        }

        return response()->json(['data' => $q->get()->map(fn (Page $p) => [
            'uuid' => strtolower($p->uuid), 'key' => $p->key, 'type' => $p->type, 'published' => $p->is_published,
            'label' => $this->translator->labelOf($p->translationsFor('name'), $p->key),
            'application' => strtolower((string) ($apps[$p->application_id] ?? '')),
            'updated_at' => Carbon::parse($p->getRawOriginal('updated_at'), 'UTC')->toIso8601ZuluString(),
        ])->values()->all()]);
    }

    public function show(string $page): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');

        return response()->json(['data' => $this->pages->present($this->find($page))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $p = $this->pages->save(null, $this->pages->check((array) $request->input('page', []), null), $this->user());

        return response()->json(['data' => $this->pages->present($p)], 201);
    }

    public function update(Request $request, string $page): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $p = $this->find($page);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->pages->present($p)['hash'], (string) $request->input('base_hash')), 409, __('experience.changed_meanwhile'));
        $p = $this->pages->save($p, $this->pages->check((array) $request->input('page', []), $p), $this->user());

        return response()->json(['data' => $this->pages->present($p)]);
    }

    public function destroy(string $page): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $this->pages->delete($this->find($page), $this->user());

        return response()->json(null, 204);
    }

    /** Tells the application's people about a changelog page (architecture §19.23). */
    public function announce(string $page): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        Gate::authorize('system.publish_announcements');
        $a = $this->pages->announce($this->find($page), $this->user());

        return response()->json(['data' => ['uuid' => strtolower($a->uuid)]], 201);
    }

    /** The page for the person opening it. */
    public function view(string $page, Maintenance $maintenance): JsonResponse
    {
        $p = $this->find($page);
        $user = $this->user();
        abort_unless($this->pages->viewable($p, $user), 404);
        $app = Application::query()->findOrFail($p->application_id);
        abort_if($maintenance->blocks($app, $user), 423, $this->translator->pick($app->translationsFor('maintenance_message')) ?? __('experience.maintenance'));

        return response()->json(['data' => $this->pages->view($p)]);
    }

    private function find(string $uuid): Page
    {
        $p = Str::isUuid($uuid) ? Pages::live()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($p === null, 404);

        return $p;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
