<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\AccessResolver;
use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\HomeScreens;
use App\Modules\Experience\Maintenance;
use App\Modules\Experience\Models\HomeScreen;
use App\Modules\Forms\Models\Application;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntimes;
use App\Modules\Records\Runtime\RecordPresenter;
use App\Modules\Records\Runtime\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Home screens (specification §4.29, ADR-0054). Building them needs "Manage
 * pages and menus"; everyone gets the screen meant for them, with only the
 * widgets they may see.
 */
final class HomeScreenController extends Controller
{
    public function __construct(private readonly HomeScreens $screens, private readonly Translator $translator) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $apps = Application::query()->get()->keyBy('id');

        return response()->json(['data' => HomeScreen::query()->orderBy('application_id')->orderBy('target_type')->orderByDesc('priority')->get()->map(function (HomeScreen $h) use ($apps): array {
            $doc = $this->screens->present($h);
            $app = $h->application_id === null ? null : $apps[$h->application_id] ?? null;

            return [
                'uuid' => $doc['uuid'], 'label' => $doc['label'], 'target_type' => $h->target_type, 'target_name' => $this->targetName($h),
                'application' => $doc['application'], 'application_name' => $app === null ? null : $this->translator->labelOf($app->translationsFor('name'), (string) $app->key),
                'priority' => $h->priority, 'active' => $h->is_active, 'widgets' => count($doc['widgets']),
            ];
        })->values()->all()]);
    }

    public function show(string $screen): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');

        return response()->json(['data' => $this->screens->present($this->find($screen))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $h = $this->screens->save(null, $this->screens->check((array) $request->input('screen', []), null), $this->user());

        return response()->json(['data' => $this->screens->present($h)], 201);
    }

    public function update(Request $request, string $screen): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $h = $this->find($screen);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->screens->present($h)['hash'], (string) $request->input('base_hash')), 409, __('experience.changed_meanwhile'));
        $h = $this->screens->save($h, $this->screens->check((array) $request->input('screen', []), $h), $this->user());

        return response()->json(['data' => $this->screens->present($h)]);
    }

    public function destroy(string $screen): JsonResponse
    {
        Gate::authorize('system.manage_pages_menus');
        $this->screens->delete($this->find($screen), $this->user());

        return response()->json(null, 204);
    }

    /** The signed-in person's home screen, the main one or an application's; null when none applies. */
    public function home(Request $request, AccessResolver $access, Maintenance $maintenance): JsonResponse
    {
        $data = $request->validate(['application' => ['sometimes', 'nullable', 'uuid']]);
        $user = $this->user();
        $app = null;
        if (isset($data['application'])) {
            $app = Application::query()->where('uuid', strtolower($data['application']))->where('status', 'active')->first();
            abort_if($app === null || ! $access->allows($user, "app.{$app->uuid}.access"), 404);
            abort_if($maintenance->blocks($app, $user), 423, $this->translator->pick($app->translationsFor('maintenance_message')) ?? __('experience.maintenance'));
        }
        $h = $this->screens->resolve($user, $app);

        return response()->json(['data' => $h === null ? null : $this->screens->view($h, $user)]);
    }

    /** The person's own latest changes to records they can still see. */
    public function activity(Request $request, FormRuntimes $runtimes, RecordScope $scope, RecordStore $store, RecordPresenter $presenter, AccessResolver $access, FieldAccessResolver $fieldAccess): JsonResponse
    {
        $data = $request->validate(['limit' => ['sometimes', 'integer', 'between:1,50']]);
        $user = $this->user();
        $entries = DB::table('submission_journal')->where('user_id', $user->id)->where('status', 'processed')->whereNotNull('record_id')
            ->whereIn('operation', ['create', 'update', 'transition', 'action', 'restore'])->orderByDesc('id')->limit(200)->get(['form_id', 'record_id', 'operation', 'created_at']);
        $out = [];
        $seen = [];
        foreach ($entries->groupBy('form_id') as $formId => $rows) {
            $form = Form::query()->find($formId);
            $rt = $form === null || $form->state !== 'published' || ! $access->allows($user, "form.{$form->uuid}.view") ? null : $runtimes->forForm($form);
            if ($rt === null) {
                continue;
            }
            $ids = $rows->pluck('record_id')->map(static fn ($v) => (int) $v)->unique()->values()->all();
            $raw = $scope->apply(DB::table($rt->table)->whereIn('id', $ids)->whereNull('deleted_at'), $rt, $user, 'view')->get()->map(static fn ($r) => (array) $r)->all();
            $uuids = array_column($raw, 'uuid', 'id');
            $titles = [];
            // Titles are built only from fields the person may see.
            $levels = $fieldAccess->resolve($user, $form->id, $form->uuid, $rt->definition, 'view');
            foreach ($presenter->many($rt, $store->hydrate($rt, $raw, false), $levels['fields'], true) as $p) {
                $titles[strtolower((string) $p['uuid'])] = $p;
            }
            foreach ($rows as $e) {
                $rec = $titles[strtolower((string) ($uuids[(int) $e->record_id] ?? ''))] ?? null;
                if ($rec === null || isset($seen[$formId.':'.$e->record_id])) {
                    continue;
                }
                $seen[$formId.':'.$e->record_id] = true;
                $out[] = [
                    'form' => ['uuid' => strtolower((string) $form->uuid), 'name' => $this->translator->labelOf($form->translationsFor('name'), (string) $form->key)],
                    'record' => ['uuid' => $rec['uuid'], 'title' => $rec['title'] ?? null], 'operation' => $e->operation,
                    'at' => Carbon::parse($e->created_at, 'UTC')->toIso8601ZuluString(),
                ];
            }
        }
        usort($out, static fn (array $a, array $b) => strcmp($b['at'], $a['at']));

        return response()->json(['data' => array_slice($out, 0, (int) ($data['limit'] ?? 10))]);
    }

    private function targetName(HomeScreen $h): ?string
    {
        return match ($h->target_type) {
            'role' => $h->target_id === null ? null : ($this->translator->get('role', $h->target_id, 'name') ?? (string) DB::table('roles')->where('id', $h->target_id)->value('key')),
            'department' => $h->target_id === null ? null : ($this->translator->get('department', $h->target_id, 'name') ?? (string) DB::table('departments')->where('id', $h->target_id)->value('code')),
            'user' => $h->target_id === null ? null : (string) DB::table('users')->where('id', $h->target_id)->value('name'),
            default => null,
        };
    }

    private function find(string $uuid): HomeScreen
    {
        $h = Str::isUuid($uuid) ? HomeScreen::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($h === null, 404);

        return $h;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
