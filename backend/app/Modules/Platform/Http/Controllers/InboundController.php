<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Inbound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The public address of inbound endpoints: `POST /api/inbound/{slug}` (specification §4.33). */
final class InboundController extends Controller
{
    public function receive(Request $request, string $slug, Inbound $inbound): JsonResponse
    {
        return $inbound->receive($request, $slug);
    }
}
