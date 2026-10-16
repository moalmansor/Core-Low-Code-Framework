<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\AccessResolver;
use App\Modules\Core\I18n\Translator;
use App\Modules\Extensions\ExtensionStore;
use App\Modules\Extensions\HookRunner;
use App\Modules\Extensions\Models\Extension;
use App\Modules\Extensions\Models\ExtensionVersion;
use App\Modules\Extensions\Sdk\ApiRequest;
use App\Modules\Extensions\Sdk\Records;
use App\Modules\Forms\Models\Form;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Deployed extensions at work (ADR-0051): custom API endpoints for signed-in
 * people holding the endpoint's permission, and client extensions — which
 * a form uses, and their code for the sandboxed frame.
 */
final class ExtensionRuntimeController extends Controller
{
    public function __construct(private readonly HookRunner $runner, private readonly ExtensionStore $store, private readonly AccessResolver $access) {}

    public function call(Request $request, string $key): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $ext = Extension::query()->where('key', strtolower($key))->where('type', 'api_endpoint')->first();
        $version = $ext?->deployed_version_id === null ? null : ExtensionVersion::query()->find($ext->deployed_version_id);
        abort_if($ext === null || $version === null || $ext->status === 'disabled', 404);
        $permission = (string) ($version->manifest['permission'] ?? '');
        abort_if($permission === '' || ! $this->access->allows($user, $permission), 403, __('extensions.forbidden'));
        $body = $request->isJson() ? (array) $request->json()->all() : [];
        try {
            $answer = $this->runner->call($ext, new ApiRequest($request->method(), $request->query(), $body, HookRunner::who($user), new Records($user)));
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json(['data' => $answer]);
    }

    /** The client extensions a form uses: its validators and scripts, and the field components its fields name. */
    public function forForm(Form $form): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->access->allows($user, "form.{$form->uuid}.view"), 404);
        $rows = Extension::query()->whereIn('type', Extension::CLIENT)->whereNotNull('deployed_version_id')->where('status', '!=', 'disabled')
            ->where(static fn ($q) => $q->where('form_id', $form->id)->orWhere('type', 'client_field'))->orderBy('key')->get();
        $versions = ExtensionVersion::query()->whereIn('id', $rows->pluck('deployed_version_id'))->get()->keyBy('id');

        return response()->json(['data' => $rows->map(fn (Extension $e) => $this->client($e, $versions[$e->deployed_version_id]))->values()->all()]);
    }

    /** Field components for the form builder's palette. */
    public function components(): JsonResponse
    {
        abort_unless(Auth::user() !== null, 401);
        $rows = Extension::query()->where('type', 'client_field')->whereNotNull('deployed_version_id')->where('status', '!=', 'disabled')->orderBy('key')->get();
        $versions = ExtensionVersion::query()->whereIn('id', $rows->pluck('deployed_version_id'))->get()->keyBy('id');

        return response()->json(['data' => $rows->map(fn (Extension $e) => $this->client($e, $versions[$e->deployed_version_id]))->values()->all()]);
    }

    /**
     * A deployed client build, by version. It is code, not data: it is
     * served without a session to the sandboxed frame (whose origin is
     * opaque), immutable, and checked by the frame's integrity hash.
     */
    public function bundle(string $version): Response
    {
        $v = ExtensionVersion::query()->where('uuid', strtolower($version))->first();
        $ext = $v === null ? null : Extension::query()->withoutGlobalScope('organization')->find($v->extension_id);
        abort_if($v === null || $ext === null || $ext->isServer() || $v->approved_at === null, 404);

        return response($this->store->build($v, $ext), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=31536000, immutable',
            'Cross-Origin-Resource-Policy' => 'cross-origin', 'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** The frame client extensions run in: its own strict policy, an opaque origin, nothing but the runtime and one build. */
    public function frame(Request $request): Response
    {
        $data = $request->validate(['v' => ['required', 'uuid']]);
        $v = ExtensionVersion::query()->where('uuid', strtolower($data['v']))->first();
        $ext = $v === null ? null : Extension::query()->withoutGlobalScope('organization')->find($v->extension_id);
        abort_if($v === null || $ext === null || $ext->isServer() || $v->approved_at === null, 404);
        $origin = $request->getSchemeAndHttpHost();
        $sri = 'sha256-'.base64_encode(hash('sha256', $this->store->build($v, $ext), true));
        $runtime = e(url('/ext/runtime.js'));
        $vue = e(url('/ext/vue.js'));
        $bundle = e(url('/ext/b/'.$v->uuid.'.js'));
        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<style>html,body{margin:0;padding:0;font:14px system-ui,sans-serif;background:transparent;color:CanvasText}</style>'
            ."<script src=\"{$vue}\"></script><script src=\"{$runtime}\"></script><script src=\"{$bundle}\" integrity=\"{$sri}\" crossorigin=\"anonymous\"></script></head><body><div id=\"app\"></div></body></html>";

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            ExtensionFrame::HEADER => "default-src 'none'; script-src {$origin}/ext/; style-src 'unsafe-inline'; img-src data:; connect-src 'none'; form-action 'none'; base-uri 'none'; frame-ancestors 'self'",
        ]);
    }

    public function runtime(): Response
    {
        return response((string) file_get_contents(resource_path('extensions/runtime.js')), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=3600',
            'Cross-Origin-Resource-Policy' => 'cross-origin', 'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** Vue's runtime build (no template compiler), for field components written with render functions. */
    public function vue(): Response
    {
        $file = (string) config('services.extensions.vue');
        abort_unless(is_file($file), 404);

        return response((string) file_get_contents($file), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'public, max-age=86400',
            'Cross-Origin-Resource-Policy' => 'cross-origin', 'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** @return array<string, mixed> */
    private function client(Extension $e, ExtensionVersion $v): array
    {
        $label = (array) ($v->manifest['label'] ?? []);

        return [
            'uuid' => $e->uuid, 'key' => $e->key, 'type' => $e->type, 'version' => $v->uuid, 'label' => app(Translator::class)->labelOf($label, $e->key),
            'frame' => url('/ext/frame?v='.$v->uuid),
        ];
    }
}
