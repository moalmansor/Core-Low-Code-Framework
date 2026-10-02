<?php

declare(strict_types=1);

namespace App\Infrastructure\Egress;

use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * The only component allowed to make outbound HTTP requests (architecture §7.4,
 * specification §4.15 and §5). For every request and every redirect hop it:
 * checks the scheme and host against the organization's egress allowlist,
 * resolves DNS and rejects blocked address ranges, pins the connection to the
 * validated address, follows redirects only within the same scheme/host/port,
 * and enforces timeouts and a response-size cap.
 */
final class EgressGateway
{
    public const MAX_REDIRECTS = 3;

    public function __construct(
        private readonly AddressPolicy $policy,
        private readonly HostResolver $resolver,
        private readonly CorrelationId $correlation,
        private readonly ?HandlerStack $handler = null,
    ) {}

    /**
     * Guzzle client whose every request passes through the gateway checks.
     * Used where a library needs a client (e.g. Socialite for OIDC).
     */
    public function client(int $timeoutSeconds = 15, int $maxBytes = 10_485_760): Client
    {
        $stack = $this->handler !== null ? clone $this->handler : HandlerStack::create();
        $stack->push(function (callable $next) use ($maxBytes) {
            return function (RequestInterface $request, array $options) use ($next, $maxBytes) {
                $pin = $this->authorize($request->getUri());
                $options['curl'] = ($options['curl'] ?? []) + [CURLOPT_RESOLVE => [$pin]];
                $options['allow_redirects'] = false;
                $options['progress'] = static function ($downloadTotal, $downloaded) use ($maxBytes): void {
                    if ($downloadTotal > $maxBytes || $downloaded > $maxBytes) {
                        throw new EgressViolation('Response exceeds the egress size limit.');
                    }
                };
                $request = $request->withHeader(CorrelationId::HEADER, $this->correlation->get());

                return $next($request, $options);
            };
        }, 'egress_gateway');

        return new Client([
            'handler' => $stack,
            'connect_timeout' => min(5, $timeoutSeconds),
            'timeout' => min(60, $timeoutSeconds),
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }

    /**
     * Send a request, following at most three same-origin redirects.
     *
     * @param  array<string, mixed>  $options
     */
    public function request(string $method, string $url, array $options = [], int $timeoutSeconds = 15, int $maxBytes = 10_485_760): ResponseInterface
    {
        $client = $this->client($timeoutSeconds, $maxBytes);
        $uri = new Uri($url);
        $origin = $this->origin($uri);
        for ($hop = 0; ; $hop++) {
            $response = $client->request($method, $uri, $options);
            $location = $response->getHeaderLine('Location');
            if ($response->getStatusCode() < 300 || $response->getStatusCode() >= 400 || $location === '') {
                return $response;
            }
            if ($hop >= self::MAX_REDIRECTS) {
                throw new EgressViolation('Too many redirects.');
            }
            $next = UriResolver::resolve($uri, new Uri($location));
            if ($this->origin($next) !== $origin) {
                throw new EgressViolation('Redirects to another host are not followed.');
            }
            $uri = $next;
            if (in_array($response->getStatusCode(), [301, 302, 303], true)) {
                $method = 'GET';
                unset($options['body'], $options['json'], $options['form_params']);
            }
        }
    }

    /**
     * Validate the URI and return the CURLOPT_RESOLVE pin `host:port:address`.
     */
    public function authorize(UriInterface $uri): string
    {
        $scheme = strtolower($uri->getScheme());
        $host = strtolower($uri->getHost());
        if ($uri->getUserInfo() !== '') {
            throw new EgressViolation('Credentials in URLs are not allowed.');
        }
        if (! in_array($scheme, ['https', 'http'], true) || $host === '') {
            throw new EgressViolation('Only http(s) URLs with a host are allowed.');
        }
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new EgressViolation('IP-literal hosts are not allowed; use an allowlisted hostname.');
        }
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);
        $entry = $this->allowlistEntry($host);
        if ($entry === null) {
            throw new EgressViolation("Host [{$host}] is not on the egress allowlist.");
        }
        if ($scheme === 'http' && ! $entry->allow_http) {
            throw new EgressViolation("Plain HTTP is not allowed for [{$host}].");
        }
        $ports = array_map('intval', $entry->ports ?: [443]);
        if (! in_array($port, $ports, true)) {
            throw new EgressViolation("Port {$port} is not allowed for [{$host}].");
        }
        $addresses = $this->resolver->resolve($host);
        if ($addresses === []) {
            throw new EgressViolation("Host [{$host}] does not resolve.");
        }
        foreach ($addresses as $address) {
            if ($this->policy->isBlocked($address)) {
                throw new EgressViolation("Host [{$host}] resolves to a blocked address.");
            }
        }
        $pinned = $addresses[0];

        return sprintf('%s:%d:%s', $host, $port, str_contains($pinned, ':') ? '['.$pinned.']' : $pinned);
    }

    private function allowlistEntry(string $host): ?EgressAllowlistEntry
    {
        foreach (EgressAllowlistEntry::query()->where('is_active', true)->get() as $entry) {
            $pattern = strtolower($entry->host_pattern);
            if ($pattern === $host) {
                return $entry;
            }
            if (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1)) && $host !== substr($pattern, 2)) {
                return $entry;
            }
        }

        return null;
    }

    private function origin(UriInterface $uri): string
    {
        $scheme = strtolower($uri->getScheme());

        return $scheme.'://'.strtolower($uri->getHost()).':'.($uri->getPort() ?? ($scheme === 'https' ? 443 : 80));
    }
}
