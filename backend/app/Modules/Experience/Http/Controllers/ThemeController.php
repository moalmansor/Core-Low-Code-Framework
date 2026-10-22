<?php

declare(strict_types=1);

namespace App\Modules\Experience\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\I18n\Translator;
use App\Modules\Experience\Models\Theme;
use App\Modules\Experience\Themes\Themes;
use App\Modules\Experience\Themes\ThemeTokens;
use App\Modules\Forms\Models\Application;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/** Themes (specification §4.28, ADR-0055); managing them needs "Manage branding". */
final class ThemeController extends Controller
{
    public function __construct(private readonly Themes $themes, private readonly Translator $translator) {}

    public function index(): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $apps = Application::query()->get()->keyBy('id');

        return response()->json(['data' => Theme::query()->orderByDesc('is_default')->orderBy('key')->get()->map(function (Theme $t) use ($apps): array {
            $app = $t->application_id === null ? null : ($apps[$t->application_id] ?? null);

            return [
                'uuid' => strtolower($t->uuid), 'key' => $t->key, 'label' => $this->translator->labelOf($t->translationsFor('name'), $t->key),
                'default' => $t->is_default, 'version' => $t->version,
                'application_name' => $app === null ? null : $this->translator->labelOf($app->translationsFor('name'), (string) $app->key),
                'warnings' => ThemeTokens::warnings(ThemeTokens::report($t->tokens)),
            ];
        })->values()->all()]);
    }

    public function presets(): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $out = [];
        foreach (ThemeTokens::PRESETS as $key => $tokens) {
            $out[] = ['key' => $key, 'tokens' => ['colors' => (object) ($tokens['colors'] ?? [])] + $tokens, 'resolved' => ThemeTokens::resolved($tokens)];
        }

        return response()->json(['data' => $out, 'meta' => ['defaults' => ThemeTokens::DEFAULTS]]);
    }

    /** The contrast report of tokens being edited, before saving. */
    public function check(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $tokens = ThemeTokens::normalize((array) $request->input('tokens', []));

        return response()->json(['data' => ['report' => ThemeTokens::report($tokens), 'resolved' => ThemeTokens::resolved($tokens)]]);
    }

    public function show(string $theme): JsonResponse
    {
        Gate::authorize('system.manage_branding');

        return response()->json(['data' => $this->themes->present($this->find($theme))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $t = $this->themes->save(null, $this->themes->check((array) $request->input('theme', []), null), $this->user());

        return response()->json(['data' => $this->themes->present($t)], 201);
    }

    public function update(Request $request, string $theme): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $t = $this->find($theme);
        $request->validate(['base_hash' => ['required', 'string', 'size:64']]);
        abort_unless(hash_equals($this->themes->present($t)['hash'], (string) $request->input('base_hash')), 409, __('experience.changed_meanwhile'));
        $t = $this->themes->save($t, $this->themes->check((array) $request->input('theme', []), $t), $this->user());

        return response()->json(['data' => $this->themes->present($t)]);
    }

    public function destroy(string $theme): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $this->themes->delete($this->find($theme), $this->user());

        return response()->json(null, 204);
    }

    public function makeDefault(string $theme): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $t = $this->find($theme);
        $this->themes->makeDefault($t, $this->user());

        return response()->json(['data' => $this->themes->present($t->refresh())]);
    }

    public function clearDefault(): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $this->themes->clearDefault($this->user());

        return response()->json(null, 204);
    }

    public function reset(string $theme): JsonResponse
    {
        Gate::authorize('system.manage_branding');

        return response()->json(['data' => $this->themes->present($this->themes->reset($this->find($theme), $this->user()))]);
    }

    public function export(string $theme): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $t = $this->find($theme);

        return response()->json($this->themes->export($t), 200, ['Content-Disposition' => 'attachment; filename="theme-'.$t->key.'.json"']);
    }

    public function import(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $data = $request->validate(['theme' => ['required', 'array']]);

        return response()->json(['data' => $this->themes->present($this->themes->import($data['theme'], $this->user()))], 201);
    }

    public function storeAsset(Request $request, string $theme, string $kind): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $request->validate(['file' => ['required', 'file']]);
        abort_unless(in_array($kind, Themes::ASSETS, true), 404);
        $t = $this->find($theme);
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $this->themes->storeAsset($t, $kind, $file, $this->user());

        return response()->json(['data' => $this->themes->present($t->refresh())]);
    }

    public function removeAsset(string $theme, string $kind): JsonResponse
    {
        Gate::authorize('system.manage_branding');
        $t = $this->find($theme);
        $this->themes->removeAsset($t, $kind, $this->user());

        return response()->json(['data' => $this->themes->present($t->refresh())]);
    }

    /** Public: artwork is shown on the sign-in page and in emails. */
    public function asset(string $theme, string $kind): BaseResponse
    {
        abort_unless(in_array($kind, Themes::ASSETS, true), 404);
        $t = $this->find($theme);
        $file = $this->themes->assetFile($t, $kind);
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type, 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'",
        ]);
    }

    /** Public: the theme's sanitized and scoped custom CSS, as a stylesheet of its own. */
    public function css(string $theme): Response
    {
        $t = $this->find(Str::beforeLast($theme, '.css'));
        abort_if($t->custom_css_compiled === null, 404);

        return response($t->custom_css_compiled, 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function find(string $uuid): Theme
    {
        $t = Str::isUuid($uuid) ? Theme::query()->where('uuid', strtolower($uuid))->first() : null;
        abort_if($t === null, 404);

        return $t;
    }

    private function user(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }
}
