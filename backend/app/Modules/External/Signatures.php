<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\Access\FieldAccessResolver;
use App\Modules\Access\RecordScope;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Documents\DocumentRenderer;
use App\Modules\Documents\Models\DocumentTemplate;
use App\Modules\External\Models\AccessToken;
use App\Modules\External\Models\ExternalUser;
use App\Modules\External\Models\SignatureRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\EmailQueue;
use App\Modules\Records\FileStore;
use App\Modules\Records\Models\StoredFile;
use App\Modules\Records\Runtime\FormRuntime;
use App\Modules\Records\Runtime\RecordException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Signature requests (specification §4.32, architecture §19.12, ADR-0058).
 *
 * - Someone who can view and print a record sends one of the form's
 *   document templates for signature to a person by email, an internal
 *   user or an external user. The document is generated as a PDF at that
 *   moment and kept, so the signer signs exactly what was sent.
 * - The signer opens a single-use tokenized link, reads the PDF, and draws a
 *   signature or declines with a reason. The server stamps the signature,
 *   the signer, the time, the address and the original document's SHA-256
 *   onto a final page of the signed PDF, stores it with its own SHA-256, and
 *   writes an audit entry. A request can be cancelled until it is signed.
 */
final class Signatures
{
    private const MAX_SIGNATURE_BYTES = 300_000;

    public function __construct(
        private readonly RecordScope $scope,
        private readonly FieldAccessResolver $fieldAccess,
        private readonly DocumentRenderer $renderer,
        private readonly FileStore $files,
        private readonly EmailQueue $emails,
        private readonly AuditWriter $audit,
        private readonly Translator $translator,
        private readonly AccessLinks $links,
    ) {}

    /**
     * The document templates of this form the person may send for signature.
     *
     * @return list<array{uuid: string, name: string}>
     */
    public function templates(FormRuntime $rt, User $user, int $recordId): array
    {
        if (! $this->maySend($rt, $user, $recordId)) {
            return [];
        }
        $out = [];
        foreach (DocumentTemplate::query()->where('form_id', $rt->form->id)->where('is_active', true)->orderBy('id')->get() as $t) {
            if (in_array('pdf', $t->output_formats, true)) {
                $out[] = ['uuid' => strtolower($t->uuid), 'name' => $this->translator->labelOf($t->translationsFor('name'), Translator::humanize($t->key))];
            }
        }

        return $out;
    }

    /**
     * Sends a record's document for signature.
     *
     * @param  array<string, mixed>  $p  template, signer_type, signer (email or uuid), days
     * @return array{request: SignatureRequest, url: string}
     */
    public function request(FormRuntime $rt, User $requester, int $recordId, array $p, string $locale): array
    {
        if (! $this->maySend($rt, $requester, $recordId)) {
            throw new RecordException(403, 'forbidden', __('external.signature_forbidden'));
        }
        $errors = [];
        $template = is_string($p['template'] ?? null) && Str::isUuid($p['template'])
            ? DocumentTemplate::query()->where('uuid', strtolower($p['template']))->where('form_id', $rt->form->id)->where('is_active', true)->first() : null;
        if ($template === null || ! in_array('pdf', $template->output_formats, true)) {
            $errors['template'] = __('external.signature_template_unknown');
        }
        $type = in_array($p['signer_type'] ?? null, ['email', 'user', 'external_user'], true) ? (string) $p['signer_type'] : null;
        $signer = ['user' => null, 'external' => null, 'email' => null];
        if ($type === 'email') {
            $signer['email'] = app(VerificationCodes::class)->normalize('email', $p['signer'] ?? null);
        } elseif ($type === 'user' && is_string($p['signer'] ?? null) && Str::isUuid($p['signer'])) {
            $u = User::query()->where('uuid', strtolower($p['signer']))->where('status', 'active')->first();
            [$signer['user'], $signer['email']] = [$u?->id, $u?->email];
        } elseif ($type === 'external_user' && is_string($p['signer'] ?? null) && Str::isUuid($p['signer'])) {
            $x = ExternalUser::query()->where('uuid', strtolower($p['signer']))->where('status', 'approved')->first();
            [$signer['external'], $signer['email']] = [$x?->id, $x?->email];
        }
        if ($type === null || $signer['email'] === null || $signer['email'] === '') {
            $errors['signer'] = __('external.signer_invalid');
        }
        $days = filter_var($p['days'] ?? 14, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($days === false) {
            $errors['days'] = __('external.link_days_invalid');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        /** @var DocumentTemplate $template */
        try {
            $out = $this->renderer->render($template, $rt, $recordId, [$requester], $locale, 'pdf', $requester);
        } catch (Throwable $e) {
            report($e);
            throw new RecordException(503, 'document_failed', __('documents.generate_failed'));
        }

        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = Carbon::now('UTC')->addDays((int) $days);
        $request = DB::transaction(function () use ($rt, $requester, $recordId, $template, $type, $signer, $expires, $plain, $out): SignatureRequest {
            $token = new AccessToken;
            $token->forceFill([
                'token_hash' => hash('sha256', $plain), 'purpose' => 'signature', 'form_id' => $rt->form->id, 'record_id' => $recordId,
                'action' => 'sign', 'recipient_email' => $signer['email'], 'max_uses' => 1, 'uses' => 0, 'expires_at' => $expires, 'created_by' => $requester->id,
            ])->save();
            $request = new SignatureRequest;
            $request->forceFill([
                'form_id' => $rt->form->id, 'record_id' => $recordId, 'signer_type' => $type, 'signer_user_id' => $signer['user'],
                'signer_external_user_id' => $signer['external'], 'signer_email' => $signer['email'], 'access_token_id' => $token->id,
                'document_template_id' => $template->id, 'status' => 'pending', 'expires_at' => $expires, 'created_by' => $requester->id,
            ])->save();
            $this->keep($out['bytes'], $out['name'], 'signature_document', $request, $requester->id);

            return $request;
        });
        $url = url('/x/s/'.$plain);
        $title = $out['name'];
        $this->emails->enqueue([
            'source' => 'system', 'locale' => $locale, 'to' => [(string) $signer['email']], 'form_id' => $rt->form->id, 'record_id' => $recordId,
            'subject' => __('external.signature_subject', ['name' => $requester->name, 'document' => $title], $locale),
            'html' => '<p dir="'.(str_starts_with($locale, 'ar') ? 'rtl' : 'ltr').'">'.e(__('external.signature_body', ['name' => $requester->name, 'document' => $title, 'date' => $expires->toDateString()], $locale))
                .'</p><p><a href="'.e($url).'">'.e(__('external.signature_open', [], $locale)).'</a></p>',
        ]);
        $this->audit->record('external.signature_requested', 'data', null, 'signature_request', $request->id,
            ['signer' => $signer['email'], 'template' => $template->key], $requester->id, null, $rt->form->id, $recordId);

        return ['request' => $request, 'url' => $url];
    }

    /** The request behind a signing link; null when unknown. */
    public function find(string $plain): ?SignatureRequest
    {
        $token = $this->links->find($plain, 'signature');
        if ($token === null) {
            return null;
        }

        return SignatureRequest::query()->where('access_token_id', $token->id)->first();
    }

    /** Why the request cannot be signed now, or null when it can. */
    public function state(SignatureRequest $r): ?string
    {
        if (in_array($r->status, ['signed', 'declined', 'cancelled', 'expired'], true)) {
            return $r->status;
        }

        return $r->expires_at->isPast() ? 'expired' : null;
    }

    /** @return array<string, mixed> */
    public function page(SignatureRequest $r): array
    {
        if ($r->status === 'pending' && $this->state($r) === null) {
            $r->forceFill(['status' => 'viewed'])->save();
            $this->audit->record('external.signature_viewed', 'data', null, 'signature_request', $r->id, ['signer' => $r->signer_email], $r->created_by, null, $r->form_id, $r->record_id);
        }

        return [
            'state' => $this->state($r), 'signer' => $r->signer_email, 'expires_at' => $r->expires_at->toIso8601ZuluString(),
            'sender' => DB::table('users')->where('id', $r->created_by)->value('name'),
            'document' => ($file = $this->document($r)) === null ? null : $file->original_name,
        ];
    }

    public function document(SignatureRequest $r): ?StoredFile
    {
        return StoredFile::query()->where('owner_type', 'signature_document')->where('owner_id', $r->id)->whereNull('deleted_at')->first();
    }

    public function signed(SignatureRequest $r): ?StoredFile
    {
        return $r->signed_file_id === null ? null : StoredFile::query()->find($r->signed_file_id);
    }

    /** Signs with a drawn signature (a PNG data URL). */
    public function sign(SignatureRequest $r, mixed $image, string $ip, string $agent): void
    {
        if ($this->state($r) !== null) {
            throw new RecordException(410, 'link_gone', __('external.signature_state.'.$this->state($r)));
        }
        $png = $this->signatureImage($image);
        $original = $this->document($r);
        if ($original === null) {
            throw new RecordException(410, 'link_gone', __('external.link_state.unavailable'));
        }
        $bytes = (string) Storage::disk($original->disk)->get($original->path);
        $now = Carbon::now('UTC');
        $signedPdf = $this->stamp($bytes, $png, $r, $original, $now, $ip);
        $this->consume($r);
        DB::transaction(function () use ($r, $png, $signedPdf, $original, $now, $ip, $agent): void {
            $signature = $this->keep($png, 'signature', 'signature_image', $r, $r->created_by, 'png', 'image/png');
            $signedName = preg_replace('/\.pdf$/i', '', $original->original_name).' ('.__('external.signed_suffix').')';
            $signed = $this->keep($signedPdf, (string) $signedName, 'signature_signed', $r, $r->created_by);
            $r->forceFill([
                'status' => 'signed', 'signature_file_id' => $signature->id, 'signed_file_id' => $signed->id, 'signed_document_hash' => $signed->sha256,
                'signed_at' => $now, 'signer_ip' => mb_substr($ip, 0, 45), 'signer_user_agent' => mb_substr($agent, 0, 1000),
            ])->save();
            $this->audit->record('external.signed', 'data', null, 'signature_request', $r->id,
                ['signer' => $r->signer_email, 'document_sha256' => $original->sha256, 'signed_sha256' => $signed->sha256], $r->created_by, null, $r->form_id, $r->record_id);
        });
    }

    public function decline(SignatureRequest $r, mixed $reason): void
    {
        if ($this->state($r) !== null) {
            throw new RecordException(410, 'link_gone', __('external.signature_state.'.$this->state($r)));
        }
        $reason = is_string($reason) ? trim($reason) : '';
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => __('external.decline_reason_required')]);
        }
        $this->consume($r);
        $r->forceFill(['status' => 'declined', 'decline_reason' => $reason])->save();
        $this->audit->record('external.signature_declined', 'data', null, 'signature_request', $r->id, ['signer' => $r->signer_email], $r->created_by, null, $r->form_id, $r->record_id);
    }

    public function cancel(SignatureRequest $r, User $actor): void
    {
        if (! in_array($r->status, ['pending', 'viewed'], true)) {
            throw ValidationException::withMessages(['status' => __('external.signature_not_cancellable')]);
        }
        $r->forceFill(['status' => 'cancelled'])->save();
        DB::table('access_tokens')->where('id', $r->access_token_id)->whereNull('revoked_at')->update(['revoked_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'revoked_by' => $actor->id]);
        $this->audit->record('external.signature_cancelled', 'data', null, 'signature_request', $r->id, ['signer' => $r->signer_email], $actor->id, null, $r->form_id, $r->record_id);
    }

    /** Marks requests past their expiry as expired. */
    public function expire(): int
    {
        return DB::table('signature_requests')->whereIn('status', ['pending', 'viewed'])->where('expires_at', '<', Carbon::now('UTC')->format('Y-m-d H:i:s.u'))
            ->update(['status' => 'expired', 'updated_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
    }

    /** @return array<string, mixed> */
    public function present(SignatureRequest $r): array
    {
        return [
            'uuid' => strtolower($r->uuid), 'status' => $this->state($r) ?? $r->status, 'signer' => $r->signer_email, 'signer_type' => $r->signer_type,
            'template' => $r->document_template_id === null ? null : (($t = DocumentTemplate::query()->find($r->document_template_id)) === null ? null : $this->translator->labelOf($t->translationsFor('name'), Translator::humanize($t->key))),
            'requested_by' => DB::table('users')->where('id', $r->created_by)->value('name'),
            'created_at' => $r->created_at?->toIso8601ZuluString(), 'expires_at' => $r->expires_at->toIso8601ZuluString(),
            'signed_at' => $r->signed_at?->toIso8601ZuluString(), 'signed_sha256' => $r->signed_document_hash, 'decline_reason' => $r->decline_reason,
            'has_signed_file' => $r->signed_file_id !== null,
        ];
    }

    private function maySend(FormRuntime $rt, User $user, int $recordId): bool
    {
        if (! $this->scope->allows($rt, $user, 'view', $recordId)) {
            return false;
        }
        $status = DB::table($rt->table)->where('id', $recordId)->value('status_id');

        return (bool) ($this->fieldAccess->resolve($user, $rt->form->id, $rt->form->uuid, $rt->definition, 'print', $status === null ? null : (int) $status)['modes']['print'] ?? false);
    }

    private function consume(SignatureRequest $r): void
    {
        $ok = DB::table('access_tokens')->where('id', $r->access_token_id)->whereNull('revoked_at')->whereColumn('uses', '<', 'max_uses')
            ->update(['uses' => DB::raw('uses + 1'), 'last_used_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]);
        if ($ok === 0) {
            throw new RecordException(410, 'link_gone', __('external.link_state.used'));
        }
    }

    /** The drawn signature, re-encoded as a clean PNG. */
    private function signatureImage(mixed $image): string
    {
        if (! is_string($image) || ! str_starts_with($image, 'data:image/png;base64,') || strlen($image) > self::MAX_SIGNATURE_BYTES * 4 / 3 + 64) {
            throw ValidationException::withMessages(['signature' => __('external.signature_missing')]);
        }
        $raw = base64_decode(substr($image, strlen('data:image/png;base64,')), true);
        $gd = $raw === false ? false : @imagecreatefromstring($raw);
        if ($gd === false || imagesx($gd) < 50 || imagesy($gd) < 20 || imagesx($gd) > 2000 || imagesy($gd) > 1000) {
            throw ValidationException::withMessages(['signature' => __('external.signature_missing')]);
        }
        imagesavealpha($gd, true);
        ob_start();
        imagepng($gd);

        return (string) ob_get_clean();
    }

    /** The signed PDF: every page of the original, then a signature page. */
    private function stamp(string $original, string $png, SignatureRequest $r, StoredFile $file, Carbon $at, string $ip): string
    {
        $dir = storage_path('app/mpdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $mpdf = new Mpdf(['mode' => 'utf-8', 'tempDir' => $dir, 'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true, 'autoArabic' => true]);
        $mpdf->showImageErrors = false;
        $pages = $mpdf->setSourceFile(StreamReader::createByString($original));
        for ($i = 1; $i <= $pages; $i++) {
            $tpl = $mpdf->importPage($i);
            $size = $mpdf->getTemplateSize($tpl);
            $mpdf->AddPageByArray(['orientation' => $size['orientation'], 'sheet-size' => [$size['width'], $size['height']]]);
            $mpdf->useTemplate($tpl);
        }
        $rtl = in_array(app()->getLocale(), DocumentRenderer::RTL, true);
        $mpdf->AddPageByArray(['orientation' => 'P', 'sheet-size' => 'A4']);
        $mpdf->SetDirectionality($rtl ? 'rtl' : 'ltr');
        $row = static fn (string $label, string $value) => '<tr><td style="padding:2mm 3mm;color:#4b5563;width:45mm">'.e($label).'</td><td style="padding:2mm 3mm">'.e($value).'</td></tr>';
        $mpdf->WriteHTML('<h2 style="font-size:14pt">'.e(__('external.signature_page_title')).'</h2>'
            .'<p><img src="data:image/png;base64,'.base64_encode($png).'" style="max-height:35mm;max-width:120mm;border-bottom:0.3mm solid #9ca3af"></p>'
            .'<table style="font-size:10pt;border-collapse:collapse">'
            .$row(__('external.signature_page_signer'), (string) $r->signer_email)
            .$row(__('external.signature_page_time'), $at->format('Y-m-d H:i:s').' UTC')
            .$row(__('external.signature_page_address'), $ip)
            .$row(__('external.signature_page_document'), $file->original_name)
            .$row('SHA-256', (string) $file->sha256)
            .$row(__('external.signature_page_request'), strtolower($r->uuid))
            .'</table>');

        return $mpdf->Output('', 'S');
    }

    private function keep(string $bytes, string $name, string $ownerType, SignatureRequest $r, ?int $userId, string $extension = 'pdf', string $mime = 'application/pdf'): StoredFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sig');
        file_put_contents((string) $tmp, $bytes);
        try {
            return $this->files->storeGenerated((string) $tmp, $name, $extension, $mime, $ownerType, ['owner_id' => $r->id, 'form_id' => $r->form_id, 'record_id' => $r->record_id, 'user_id' => $userId]);
        } finally {
            @unlink((string) $tmp);
        }
    }
}
