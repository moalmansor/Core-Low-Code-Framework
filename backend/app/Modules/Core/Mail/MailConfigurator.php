<?php

declare(strict_types=1);

namespace App\Modules\Core\Mail;

/**
 * Whether outgoing mail is configured from the admin UI (specification §2).
 * The `lcf` transport reads the settings on every send.
 */
final class MailConfigurator
{
    public function __construct(private readonly SettingsSmtpTransport $transport) {}

    public function apply(): bool
    {
        return $this->transport->config() !== null;
    }
}
