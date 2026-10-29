<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Mpdf\Container\SimpleContainer;
use Mpdf\Mpdf;

/**
 * Builds every mPDF instance with a gated HTTP client and a data-only local
 * loader, so no PDF renderer reaches the network or the server's files on its
 * own (§5). Use this instead of `new Mpdf`.
 */
final class PdfFactory
{
    public function __construct(private readonly GatedHttpClient $http, private readonly DataOnlyContentLoader $local) {}

    /** @param array<string, mixed> $config */
    public function make(array $config): Mpdf
    {
        $container = new SimpleContainer(['httpClient' => $this->http, 'localContentLoader' => $this->local]);

        // Only https and data streams are allowed; local files and archives are never read.
        return new Mpdf($config + ['whitelistStreamWrappers' => ['https', 'data']], $container);
    }
}
