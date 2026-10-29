<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Mpdf\File\LocalContentLoaderInterface;

/**
 * mPDF's loader for "local" resources (specification §5, untrusted input):
 * only `data:` URIs are decoded; a path on the server — `/etc/passwd`,
 * `file://`, `phar://` — is never read while rendering a template.
 */
final class DataOnlyContentLoader implements LocalContentLoaderInterface
{
    public function load($path)
    {
        $src = trim((string) $path);
        if (preg_match('#^data:[a-z0-9.+/-]+(;[a-z0-9=.+-]+)*;base64,#i', $src, $m) !== 1) {
            return null;
        }
        $bytes = base64_decode(substr($src, strlen($m[0])), true);

        return $bytes === false || strlen($bytes) > GatedHttpClient::MAX_BYTES ? null : $bytes;
    }
}
