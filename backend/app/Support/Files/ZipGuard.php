<?php

declare(strict_types=1);

namespace App\Support\Files;

use ZipArchive;

/**
 * Refuses an uploaded zip container (XLSX, DOCX) before it is parsed when it
 * would unpack into far more than it weighs (specification §5, untrusted
 * input): too many entries, too many bytes in all, or a compression ratio no
 * real office file has. The sizes come from the zip's directory, so nothing
 * is decompressed to decide.
 */
final class ZipGuard
{
    public const MAX_ENTRIES = 10_000;

    public const MAX_BYTES = 300 * 1024 * 1024;

    public const MAX_RATIO = 200;

    public static function safe(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                return false;
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    return false;
                }
                $total += (int) $stat['size'];
                if ($total > self::MAX_BYTES || ((int) $stat['size'] > 1024 * 1024 && (int) $stat['size'] > self::MAX_RATIO * max(1, (int) $stat['comp_size']))) {
                    return false;
                }
            }

            return true;
        } finally {
            $zip->close();
        }
    }
}
