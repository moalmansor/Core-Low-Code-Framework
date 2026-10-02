<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Modules\Core\Settings\SettingsService;
use RuntimeException;

/**
 * ClamAV scanning over clamd's INSTREAM protocol (specification §3, §5),
 * enabled or disabled from the `clamav` settings group.
 */
class VirusScanner
{
    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('clamav', 'enabled');
    }

    /** @return 'clean'|'infected' */
    public function scan(string $path): string
    {
        $socket = @fsockopen(
            (string) $this->settings->get('clamav', 'host'),
            (int) $this->settings->get('clamav', 'port'),
            $errno,
            $error,
            (float) $this->settings->get('clamav', 'timeout_seconds'),
        );
        if ($socket === false) {
            throw new RuntimeException("ClamAV is unreachable: {$error}");
        }
        stream_set_timeout($socket, (int) $this->settings->get('clamav', 'timeout_seconds'));
        fwrite($socket, "zINSTREAM\0");
        $file = fopen($path, 'rb');
        while ($file !== false && ! feof($file)) {
            $chunk = (string) fread($file, 8192);
            if ($chunk === '') {
                break;
            }
            fwrite($socket, pack('N', strlen($chunk)).$chunk);
        }
        if ($file !== false) {
            fclose($file);
        }
        fwrite($socket, pack('N', 0));
        $reply = trim((string) stream_get_contents($socket));
        fclose($socket);
        if (str_ends_with($reply, 'OK')) {
            return 'clean';
        }
        if (str_contains($reply, 'FOUND')) {
            return 'infected';
        }
        throw new RuntimeException('Unexpected ClamAV reply.');
    }

    public function ping(): bool
    {
        $socket = @fsockopen((string) $this->settings->get('clamav', 'host'), (int) $this->settings->get('clamav', 'port'), $errno, $error, 3.0);
        if ($socket === false) {
            return false;
        }
        fwrite($socket, "zPING\0");
        $reply = trim((string) fgets($socket));
        fclose($socket);

        return $reply === 'PONG';
    }
}
