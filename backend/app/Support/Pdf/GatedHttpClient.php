<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use App\Infrastructure\Egress\EgressGateway;
use GuzzleHttp\Psr7\Response;
use Mpdf\Http\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The only way mPDF reaches a remote image or stylesheet (specification §5,
 * SSRF): `https` GETs go through the egress gateway (allowlist, blocked
 * ranges, address pinning, size and time limits); anything else is answered
 * with an empty refusal, so a template can never call an internal address.
 */
final class GatedHttpClient implements ClientInterface
{
    /** Largest image or stylesheet a PDF may embed. */
    public const MAX_BYTES = 5_242_880;

    public function __construct(private readonly EgressGateway $egress) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || strtolower($request->getUri()->getScheme()) !== 'https') {
            return new Response(403);
        }
        try {
            return $this->egress->request('GET', (string) $request->getUri(), [], 10, self::MAX_BYTES);
        } catch (Throwable) {
            return new Response(403);
        }
    }
}
