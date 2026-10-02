<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\EgressViolation;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;

final class FakeResolver implements HostResolver
{
    /** @param array<string, list<string>> $map */
    public function __construct(private array $map) {}

    public function resolve(string $host): array
    {
        return $this->map[$host] ?? [];
    }
}

function gateway(array $dns, array $responses = []): EgressGateway
{
    return new EgressGateway(new AddressPolicy, new FakeResolver($dns), app(CorrelationId::class), HandlerStack::create(new MockHandler($responses)));
}

beforeEach(function () {
    EgressAllowlistEntry::query()->create(['host_pattern' => 'api.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    EgressAllowlistEntry::query()->create(['host_pattern' => '*.partner.test', 'ports' => [443, 8443], 'allow_http' => true, 'is_active' => true]);
});

dataset('blocked addresses', [
    '127.0.0.1', '10.1.2.3', '172.16.5.4', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '224.0.0.1',
    '::1', 'fe80::1', 'fc00::1', 'fd00:ec2::254', '::ffff:127.0.0.1', '::ffff:10.0.0.1', '64:ff9b::a00:1', '2002:7f00:1::', 'not-an-ip',
]);

it('blocks private, loopback, link-local, metadata and mapped addresses', function (string $ip) {
    expect((new AddressPolicy)->isBlocked($ip))->toBeTrue();
})->with('blocked addresses');

it('allows public addresses', function (string $ip) {
    expect((new AddressPolicy)->isBlocked($ip))->toBeFalse();
})->with(['93.184.216.34', '8.8.8.8', '2606:2800:220:1:248:1893:25c8:1946']);

it('only reaches allowlisted hosts on allowed schemes and ports', function () {
    $g = gateway(['api.example.com' => ['93.184.216.34'], 'a.partner.test' => ['93.184.216.35'], 'evil.test' => ['93.184.216.36']]);
    expect($g->authorize(new Uri('https://api.example.com/v1')))->toBe('api.example.com:443:93.184.216.34');
    expect($g->authorize(new Uri('http://a.partner.test:8443/x')))->toBe('a.partner.test:8443:93.184.216.35');
    expect(fn () => $g->authorize(new Uri('https://evil.test/')))->toThrow(EgressViolation::class);
    expect(fn () => $g->authorize(new Uri('http://api.example.com/')))->toThrow(EgressViolation::class);
    expect(fn () => $g->authorize(new Uri('https://api.example.com:8443/')))->toThrow(EgressViolation::class);
    expect(fn () => $g->authorize(new Uri('https://user:pw@api.example.com/')))->toThrow(EgressViolation::class);
    expect(fn () => $g->authorize(new Uri('https://93.184.216.34/')))->toThrow(EgressViolation::class);
    expect(fn () => $g->authorize(new Uri('file:///etc/passwd')))->toThrow(EgressViolation::class);
    expect(fn () => $g->authorize(new Uri('https://partner.test/')))->toThrow(EgressViolation::class); // wildcard needs a subdomain
});

it('rejects allowlisted hosts that resolve to blocked addresses (DNS rebinding)', function () {
    $g = gateway(['api.example.com' => ['93.184.216.34', '127.0.0.1']]);
    expect(fn () => $g->authorize(new Uri('https://api.example.com/')))->toThrow(EgressViolation::class, 'blocked address');
});

it('follows same-origin redirects only, at most three', function () {
    $dns = ['api.example.com' => ['93.184.216.34']];
    $ok = gateway($dns, [new Response(302, ['Location' => '/next']), new Response(200, [], 'done')])
        ->request('GET', 'https://api.example.com/start');
    expect((string) $ok->getBody())->toBe('done');

    expect(fn () => gateway($dns, [new Response(302, ['Location' => 'https://169.254.169.254/latest/meta-data'])])
        ->request('GET', 'https://api.example.com/start'))->toThrow(EgressViolation::class);
    expect(fn () => gateway($dns, array_fill(0, 5, new Response(302, ['Location' => '/loop'])))
        ->request('GET', 'https://api.example.com/start'))->toThrow(EgressViolation::class, 'Too many redirects');
});

it('sends the correlation ID with every outbound request', function () {
    $seen = null;
    $mock = new MockHandler([function ($request) use (&$seen) {
        $seen = $request->getHeaderLine('X-Correlation-ID');

        return new Response(200);
    }]);
    $g = new EgressGateway(new AddressPolicy, new FakeResolver(['api.example.com' => ['93.184.216.34']]), app(CorrelationId::class), HandlerStack::create($mock));
    $g->request('GET', 'https://api.example.com/');
    expect($seen)->toBe(app(CorrelationId::class)->get());
});

it('manages the allowlist through the API with validation', function () {
    $this->completeSetup();
    $this->actingAs($this->superAdmin(), 'web');
    $this->postJson('/api/v1/egress-allowlist', ['host_pattern' => 'hooks.example.org', 'ports' => [443]])->assertCreated();
    $this->postJson('/api/v1/egress-allowlist', ['host_pattern' => '10.0.0.1', 'ports' => [443]])->assertUnprocessable();
    $this->postJson('/api/v1/egress-allowlist', ['host_pattern' => '*', 'ports' => [443]])->assertUnprocessable();
    $this->getJson('/api/v1/egress-allowlist')->assertOk()->assertJsonFragment(['host_pattern' => 'hooks.example.org']);
});
