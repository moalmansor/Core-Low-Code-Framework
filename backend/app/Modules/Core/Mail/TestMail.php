<?php

declare(strict_types=1);

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('ui.mail.test.subject'));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e(__('ui.mail.test.body')).'</p>');
    }
}
