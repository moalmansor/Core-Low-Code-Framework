<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Http\Middleware\ThrottlePerRoute;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * Drives the application's own API in-process, as a signed-in administrator
 * would from the builder screens, so the performance dataset is built through
 * the same validation, compilation and publishing as real configuration.
 *
 * The CSRF check and the rate limiter are lifted for this client only: it
 * runs inside the application container and sends thousands of configuration
 * requests, which is exactly what both protections exist to refuse.
 */
final class Client
{
    use InteractsWithAuthentication;
    use MakesHttpRequests;

    public function __construct(public Application $app, User $as)
    {
        $this->withoutMiddleware([ValidateCsrfToken::class, ThrottlePerRoute::class]);
        // Requests look like the SPA's: from a stateful origin, so Sanctum starts the session.
        $origin = 'http://'.((array) config('sanctum.stateful'))[0];
        $this->withHeaders(['Accept' => 'application/json', 'Accept-Language' => 'en', 'Origin' => $origin, 'Referer' => $origin.'/']);
        $this->actingAs($as, 'web');
    }

    public function actingAs(User $user, string $guard = 'web'): self
    {
        $this->app['auth']->forgetGuards();
        $this->be($user, $guard);

        return $this;
    }

    /**
     * A JSON request that must succeed; returns the decoded body.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function send(string $method, string $uri, array $data = []): array
    {
        $response = $this->json($method, '/api/v1'.$uri, $data);

        return $this->checked($response, $method, $uri);
    }

    /**
     * A multipart upload that must succeed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upload(string $uri, array $data): array
    {
        $response = $this->post('/api/v1'.$uri, $data, ['Accept' => 'application/json']);

        return $this->checked($response, 'POST', $uri);
    }

    /** @return array<string, mixed> */
    private function checked(TestResponse $response, string $method, string $uri): array
    {
        $status = $response->getStatusCode();
        if ($status >= 300) {
            throw new RuntimeException(sprintf('%s %s answered %d: %s', $method, $uri, $status, mb_substr((string) $response->getContent(), 0, 2000)));
        }

        return (array) $response->json();
    }
}
