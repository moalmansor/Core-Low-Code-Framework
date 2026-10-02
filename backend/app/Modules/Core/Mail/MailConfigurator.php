<?php

declare(strict_types=1);

namespace App\Modules\Core\Mail;

use App\Modules\Core\Settings\SettingsService;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Config;

/**
 * Applies the SMTP settings an administrator configured (specification §2)
 * to Laravel's mailer before mail is sent. Credentials never live in files.
 */
final class MailConfigurator
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MailManager $mail,
    ) {}

    public function apply(): bool
    {
        $host = $this->settings->get('mail', 'host');
        if ($host === null || $host === '') {
            return false;
        }
        $encryption = (string) $this->settings->get('mail', 'encryption');
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $host,
            'port' => (int) $this->settings->get('mail', 'port'),
            'username' => $this->settings->get('mail', 'username'),
            'password' => $this->settings->get('mail', 'password'),
            'timeout' => 15,
            'require_tls' => $encryption === 'tls',
        ]);
        Config::set('mail.from', [
            'address' => $this->settings->get('mail', 'from_address') ?? 'no-reply@localhost',
            'name' => $this->settings->get('mail', 'from_name') ?? config('app.name'),
        ]);
        $this->mail->purge('smtp');

        return true;
    }
}
