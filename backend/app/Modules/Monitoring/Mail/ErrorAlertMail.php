<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Mail;

use App\Modules\Monitoring\Models\ErrorGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ErrorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly int $groupId, public readonly string $reference)
    {
        $this->onQueue('mail');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('ui.mail.error_alert.subject', ['reference' => $this->reference]));
    }

    public function content(): Content
    {
        $group = ErrorGroup::query()->withoutGlobalScopes()->findOrFail($this->groupId);

        return new Content(view: 'mail.error-alert', with: [
            'group' => $group,
            'reference' => $this->reference,
            'url' => rtrim((string) config('app.url'), '/').'/admin/errors/'.$group->id,
        ]);
    }
}
