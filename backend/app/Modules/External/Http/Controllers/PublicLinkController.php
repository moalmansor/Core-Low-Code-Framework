<?php

declare(strict_types=1);

namespace App\Modules\External\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\External\AccessLinks;
use App\Modules\External\Models\AccessToken;
use App\Modules\External\Models\SignatureRequest;
use App\Modules\External\Signatures;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tokenized record links and signing links as their recipients use them
 * (specification §4.32, ADR-0058). The token in the address is the only
 * credential; an unknown, revoked, expired or used-up link says so and
 * shows nothing of the record.
 */
final class PublicLinkController extends Controller
{
    public function __construct(private readonly AccessLinks $links, private readonly Signatures $signatures) {}

    public function show(Request $request, string $token): JsonResponse
    {
        $t = $this->token($token);
        try {
            return response()->json(['data' => $this->links->page($t, (string) $request->ip())]);
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }
    }

    public function perform(Request $request, string $token): JsonResponse
    {
        $t = $this->token($token);
        try {
            return response()->json(['data' => $this->links->perform($t, (array) $request->only(['values', 'row_version', 'comment']), (string) $request->ip())]);
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }
    }

    public function signature(string $token): JsonResponse
    {
        return response()->json(['data' => $this->signatures->page($this->request($token, false))]);
    }

    /** The document to sign, downloaded to be read before signing. */
    public function document(string $token): StreamedResponse
    {
        $file = $this->signatures->document($this->request($token, true));
        abort_if($file === null, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function sign(Request $request, string $token): JsonResponse
    {
        $r = $this->request($token, true);
        try {
            $this->signatures->sign($r, $request->input('signature'), (string) $request->ip(), (string) $request->userAgent());
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json(['data' => ['status' => 'signed']]);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $r = $this->request($token, true);
        try {
            $this->signatures->decline($r, $request->input('reason'));
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status);
        }

        return response()->json(['data' => ['status' => 'declined']]);
    }

    private function token(string $plain): AccessToken
    {
        $t = $this->links->find($plain);
        abort_if($t === null, 404, __('external.link_state.unknown'));
        if (($state = $this->links->state($t)) !== null) {
            abort(410, __('external.link_state.'.$state));
        }

        return $t;
    }

    private function request(string $plain, bool $open): SignatureRequest
    {
        $r = $this->signatures->find($plain);
        abort_if($r === null, 404, __('external.link_state.unknown'));
        if ($open && ($state = $this->signatures->state($r)) !== null) {
            abort(410, __('external.signature_state.'.$state));
        }

        return $r;
    }
}
