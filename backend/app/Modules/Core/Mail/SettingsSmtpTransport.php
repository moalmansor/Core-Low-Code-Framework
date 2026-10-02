<?php

declare(strict_types=1);

namespace App\Modules\Core\Mail;

use App\Modules\Core\Settings\SettingsService;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * The `lcf` mail transport: SMTP configured from the admin UI (specification
 * §2) instead of files. Settings are read on every send, so a change applies
 * at once in web requests and in long-running queue workers alike.
 */
final class SettingsSmtpTransport implements TransportInterface
{
    private ?EsmtpTransport $transport = null;

    private ?string $signature = null;

    public function __construct(private readonly SettingsService $settings) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $config = $this->config();
        if ($config === null) {
            throw new TransportException('Outgoing mail is not configured.');
        }
        if ($message instanceof Email) {
            $from = new Address($config['from_address'], (string) ($config['from_name'] ?? ''));
            $message->getHeaders()->remove('From');
            $message->from($from);
            $envelope = null; // rebuilt from the message with the configured sender
        }

        return $this->transport($config)->send($message, $envelope);
    }

    public function __toString(): string
    {
        return 'lcf';
    }

    /** @return array<string, mixed>|null */
    public function config(): ?array
    {
        $host = $this->settings->get('mail', 'host');
        $from = $this->settings->get('mail', 'from_address');
        if (! is_string($host) || $host === '' || ! is_string($from) || $from === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => (int) $this->settings->get('mail', 'port'),
            'encryption' => (string) $this->settings->get('mail', 'encryption'),
            'username' => $this->settings->get('mail', 'username'),
            'password' => $this->settings->get('mail', 'password'),
            'from_address' => $from,
            'from_name' => $this->settings->get('mail', 'from_name'),
        ];
    }

    /**
     * An SMTP transport for the given settings (also used by the setup wizard to
     * test values before they are saved).
     *
     * @param  array<string, mixed>  $config
     */
    public static function build(array $config): EsmtpTransport
    {
        $transport = new EsmtpTransport((string) $config['host'], (int) $config['port'], $config['encryption'] === 'ssl');
        if ($config['encryption'] === 'tls') {
            $transport->setRequireTls(true);
        } elseif ($config['encryption'] === 'none') {
            $transport->setAutoTls(false);
        }
        if (is_string($config['username'] ?? null) && $config['username'] !== '') {
            $transport->setUsername($config['username']);
            $transport->setPassword((string) ($config['password'] ?? ''));
        }
        $stream = $transport->getStream();
        if ($stream instanceof \Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream) {
            $stream->setTimeout(15);
        }

        return $transport;
    }

    /** @param array<string, mixed> $config */
    private function transport(array $config): EsmtpTransport
    {
        $signature = hash('sha256', serialize($config));
        if ($this->transport === null || $this->signature !== $signature) {
            $this->transport = self::build($config);
            $this->signature = $signature;
        }

        return $this->transport;
    }
}
