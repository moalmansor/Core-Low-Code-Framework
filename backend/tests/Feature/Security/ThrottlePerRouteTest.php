<?php

declare(strict_types=1);

use App\Http\Middleware\ThrottlePerRoute;
use Illuminate\Support\Facades\Route;

it('limits each throttled route on its own, for the same person', function () {
    Route::middleware(['api', 'throttle:2,1'])->post('/api/v1/zz-throttle-a', fn () => response()->json(['ok' => true]));
    Route::middleware(['api', 'throttle:2,1'])->post('/api/v1/zz-throttle-b', fn () => response()->json(['ok' => true]));
    expect(app('router')->getMiddleware()['throttle'])->toBe(ThrottlePerRoute::class);

    $this->postJson('/api/v1/zz-throttle-a')->assertOk();
    $this->postJson('/api/v1/zz-throttle-a')->assertOk();
    $this->postJson('/api/v1/zz-throttle-a')->assertStatus(429);
    // Another route still has its own allowance.
    $this->postJson('/api/v1/zz-throttle-b')->assertOk();
});
