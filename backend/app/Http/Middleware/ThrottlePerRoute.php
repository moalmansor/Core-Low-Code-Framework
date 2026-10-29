<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;

/**
 * `throttle:N,M` limits each route on its own. Laravel's default counts every
 * route throttled this way under one key per user (or per address), so a
 * strict limit on one action was used up by unrelated requests elsewhere.
 * The key is now the person (or address) together with the route.
 * Named limiters (`throttle:api`, `throttle:login`, …) are unchanged.
 */
final class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $scope = $route instanceof Route ? implode(',', $route->methods()).' '.$route->uri() : '';

        return sha1(parent::resolveRequestSignature($request).'|'.$scope);
    }
}
