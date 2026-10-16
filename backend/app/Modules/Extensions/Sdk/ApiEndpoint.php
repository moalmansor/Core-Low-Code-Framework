<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Sdk;

/**
 * A custom API endpoint (specification §4.19): a class named `Endpoint`
 * that implements this interface. Its answer is sent as JSON.
 */
interface ApiEndpoint
{
    /** @return array<string, mixed> */
    public function handle(ApiRequest $request): array;
}
