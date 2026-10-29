<?php

declare(strict_types=1);

namespace App\Modules\External\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\External\ExternalAccounts;
use App\Modules\External\ExternalForms;
use App\Modules\External\Models\ExternalForm;
use App\Modules\External\ProofOfWork;
use App\Modules\External\VerificationCodes;
use App\Modules\Identity\Models\User;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * External forms as visitors use them (specification §4.32, ADR-0058):
 * the page, its proof-of-work challenge, verification codes and the
 * submission. No internal sign-in is involved.
 */
final class PublicFormController extends Controller
{
    public function __construct(
        private readonly ExternalForms $forms,
        private readonly ExternalAccounts $accounts,
        private readonly ProofOfWork $pow,
        private readonly VerificationCodes $codes,
    ) {}

    /** The page; the form itself only when no password stands in front of it. */
    public function show(Request $request, string $slug): JsonResponse
    {
        [$x, $rt, $runAs] = $this->open($slug);
        $visitor = $this->accounts->current($request);
        $admitted = $this->forms->admits($x, $visitor);

        return response()->json(['data' => $this->forms->page($x, $rt, $runAs, $admitted && $x->password_hash === null) + ['admitted' => $admitted, 'signed_in' => $visitor !== null]]);
    }

    /** Opens a password-protected form. */
    public function unlock(Request $request, string $slug): JsonResponse
    {
        [$x, $rt, $runAs] = $this->open($slug);
        abort_unless($this->forms->admits($x, $this->accounts->current($request)), 403, __('external.sign_in_required'));
        $key = 'x-pw:'.$x->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['message' => __('external.too_many')], 429);
        }
        if (! $this->forms->passwordMatches($x, $request->input('password'))) {
            RateLimiter::hit($key, 600);

            return response()->json(['message' => __('external.password_wrong'), 'errors' => ['password' => [__('external.password_wrong')]]], 422);
        }

        return response()->json(['data' => $this->forms->page($x, $rt, $runAs, true) + ['admitted' => true]]);
    }

    public function challenge(string $slug): JsonResponse
    {
        [$x] = $this->open($slug);
        abort_unless($x->captcha, 404);

        return response()->json(['data' => $this->pow->challenge()]);
    }

    /** Sends a verification code to the address the visitor gave. */
    public function code(Request $request, string $slug): JsonResponse
    {
        [$x] = $this->open($slug);
        abort_if($x->verification === 'none', 404);
        $to = $this->codes->normalize($x->verification, $request->input('to'));
        if ($to === null) {
            $message = $x->verification === 'email' ? __('external.email_invalid') : __('external.phone_invalid');

            return response()->json(['message' => $message, 'errors' => ['to' => [$message]]], 422);
        }
        $failure = $this->codes->send('form:'.$x->id, $x->verification, $to, (string) $request->ip(), app()->getLocale());

        return $failure === null ? response()->json(['data' => ['sent' => true]], 202) : response()->json(['message' => $failure], 429);
    }

    public function submit(Request $request, string $slug): JsonResponse
    {
        [$x, $rt, $runAs] = $this->open($slug);
        $visitor = $this->accounts->current($request);
        if (! $this->forms->admits($x, $visitor)) {
            return response()->json(['message' => __('external.sign_in_required')], 403);
        }
        $submission = (string) $request->input('submission', '');
        if (! Str::isUuid($submission)) {
            return response()->json(['message' => __('records.invalid')], 422);
        }
        try {
            $done = $this->forms->submit($x, $rt, $runAs, $visitor, (array) $request->input('values', []), [
                'password' => $request->input('password'), 'captcha' => $request->input('captcha'), 'verification' => (array) $request->input('verification', []),
            ], strtolower($submission), (string) $request->ip());
        } catch (RecordException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason] + $e->payload, $e->status);
        }

        return response()->json(['data' => $done], 201);
    }

    /** @return array{0: ExternalForm, 1: FormRuntime, 2: User} */
    private function open(string $slug): array
    {
        $x = $this->forms->find($slug);
        abort_if($x === null, 404, __('external.form_not_found'));
        $reason = $this->forms->availability($x);
        $rt = $this->forms->runtime($x);
        $runAs = $this->forms->runAs($x);
        if ($reason === 'inactive' || $rt === null || $runAs === null) {
            abort(404, __('external.form_not_found'));
        }
        if ($reason !== null) {
            abort(409, __('external.unavailable.'.$reason));
        }

        return [$x, $rt, $runAs];
    }
}
