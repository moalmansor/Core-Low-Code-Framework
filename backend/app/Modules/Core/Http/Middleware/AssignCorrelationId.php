<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Correlation\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public function __construct(private readonly CorrelationId $correlation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->correlation->setFromInbound($request->headers->get(CorrelationId::HEADER));
        Log::withContext(['correlation_id' => $this->correlation->get()]);
        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $this->correlation->get());

        return $response;
    }
}
