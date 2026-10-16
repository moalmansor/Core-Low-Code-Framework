<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Platform\Webhooks;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    signedIn($this, $this->admin);
});

/** Orders: reference (text), amount (number), secret note (sensitive); new → shipped. */
function orderForm(TestCase $t): array
{
    [, $form] = createForm($t, 'orders');
    $f = [
        'reference' => fieldDoc('reference', 'text', null, ['validation' => ['required' => true]]),
        'amount' => fieldDoc('amount', 'number'),
        'note' => fieldDoc('note', 'text', null, ['flags' => ['sensitive' => true]]),
    ];
    saveDraft($t, $form, [], array_values($f))->assertOk();
    $new = statusDoc('new', true);
    $shipped = statusDoc('shipped', false, true);
    $ship = transitionDoc('ship', $new, $shipped);
    saveWorkflow($t, $form, [$new, $shipped], [$ship])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return ['form' => $form, 'f' => $f, 'ship' => $ship];
}

/** @param  list<Response|Closure>  $answers  @param  list<RequestInterface>  $seen */
function fakeEgress(array $answers, array &$seen): void
{
    $resolver = new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    };
    $queue = [];
    foreach ($answers as $a) {
        $queue[] = static function (RequestInterface $r) use ($a, &$seen) {
            $seen[] = $r;

            return $a instanceof Closure ? $a($r) : $a;
        };
    }
    app()->instance(EgressGateway::class, new EgressGateway(new AddressPolicy, $resolver, app(CorrelationId::class), HandlerStack::create(new MockHandler($queue))));
    app()->forgetInstance(Webhooks::class);
}

function token(TestCase $t, array $abilities, array $extra = []): string
{
    return $t->postJson('/api/v1/me/tokens', ['name' => 'Integration', 'abilities' => $abilities] + $extra)->assertCreated()->json('data.secret');
}

/** Back to the signed-in application, without the token header. */
function signedIn(TestCase $t, $user): void
{
    $t->flushHeaders();
    $t->flushSession();
    $t->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/']);
    $t->actingAs($user, 'web');
}

/** A request with only the bearer token: no session cookie, no SPA headers. */
function bearer(TestCase $t, string $token): TestCase
{
    app('auth')->forgetGuards();
    $t->flushSession();

    return $t->withHeaders(['Authorization' => 'Bearer '.$token, 'Origin' => '', 'Referer' => '']);
}

it('issues a token once, limited to what its owner can do, and the data API applies the owner\'s permissions through the record pipeline', function () {
    $o = orderForm($this);
    $clerk = $this->makeUser();
    grantPermission($clerk->id, "form.{$o['form']}.view");
    grantPermission($clerk->id, "form.{$o['form']}.create");
    signedIn($this, $clerk);

    $grantable = $this->getJson('/api/v1/me/tokens')->assertOk()->json('meta.grantable');
    expect(collect($grantable)->firstWhere('form', $o['form']))->toMatchArray(['read' => true, 'write' => true]);
    // An ability on a form the clerk cannot see is refused.
    $this->postJson('/api/v1/me/tokens', ['name' => 'x', 'abilities' => ["form:{$o['form']}:write", 'form:'.uid().':read']])->assertStatus(422);
    $made = $this->postJson('/api/v1/me/tokens', ['name' => 'Shop', 'abilities' => ["form:{$o['form']}:write"], 'expires_in_days' => 30])->assertCreated();
    $secret = $made->json('data.secret');
    expect($secret)->toStartWith('lcf_')
        ->and(DB::table('api_tokens')->value('token'))->toBe(hash('sha256', $secret))
        ->and($this->getJson('/api/v1/me/tokens')->json('data.0'))->not->toHaveKey('secret');

    $created = bearer($this, $secret)->postJson('/api/v1/data/orders', ['values' => ['reference' => 'A-1', 'amount' => 12, 'note' => 'gate code 4411']])->assertCreated();
    $uuid = $created->json('data.uuid');
    expect(DB::table('submission_journal')->where('operation', 'create')->where('status', 'processed')->value('source'))->toBe('api');
    bearer($this, $secret)->getJson('/api/v1/data/orders')->assertOk()->assertJsonPath('data.0.values.reference', 'A-1');
    // The clerk may not edit: the token's write ability does not widen that.
    bearer($this, $secret)->patchJson("/api/v1/data/orders/{$uuid}", ['values' => ['amount' => 13], 'row_version' => 1])->assertForbidden();
    // Missing validation is the pipeline's.
    bearer($this, $secret)->postJson('/api/v1/data/orders', ['values' => ['amount' => 1]])->assertStatus(422)->assertJsonStructure(['errors' => ['reference']]);
    // A token does not open the application's own endpoints.
    bearer($this, $secret)->getJson('/api/v1/forms')->assertUnauthorized();
    bearer($this, $secret)->getJson('/api/v1/me')->assertUnauthorized();
    expect(DB::table('audit_logs')->where('event', 'api_token.created')->count())->toBe(1);
});

it('refuses revoked, expired, wrong-address and switched-off tokens, and read-only tokens cannot write', function () {
    $o = orderForm($this);
    $read = token($this, ["form:{$o['form']}:read"]);
    bearer($this, $read)->getJson('/api/v1/data/orders')->assertOk();
    bearer($this, $read)->postJson('/api/v1/data/orders', ['values' => ['reference' => 'X']])->assertForbidden()->assertJsonPath('message', __('platform.token_read_only'));
    bearer($this, 'lcf_'.str_repeat('a', 48))->getJson('/api/v1/data/orders')->assertUnauthorized();

    signedIn($this, $this->admin);
    $walled = token($this, ["form:{$o['form']}:read"], ['ip_allowlist' => ['203.0.113.0/24']]);
    bearer($this, $walled)->getJson('/api/v1/data/orders')->assertUnauthorized();
    bearer($this, $walled)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/v1/data/orders')->assertOk();

    DB::table('api_tokens')->update(['expires_at' => now()->subMinute()->format('Y-m-d H:i:s.u')]);
    bearer($this, $read)->getJson('/api/v1/data/orders')->assertUnauthorized();
    DB::table('api_tokens')->update(['expires_at' => now()->addDay()->format('Y-m-d H:i:s.u')]);

    app(SettingsService::class)->write('api', 'enabled', false);
    bearer($this, $read)->getJson('/api/v1/data/orders')->assertUnauthorized();
    app(SettingsService::class)->write('api', 'enabled', true);

    signedIn($this, $this->admin);
    $id = DB::table('api_tokens')->where('token', hash('sha256', $read))->value('uuid');
    $this->deleteJson("/api/v1/me/tokens/{$id}")->assertSuccessful();
    bearer($this, $read)->getJson('/api/v1/data/orders')->assertUnauthorized();
    // A form outside the token's abilities does not exist for it.
    signedIn($this, $this->admin);
    [, $hidden] = createForm($this, 'hidden_things');
    saveDraft($this, $hidden, [], [fieldDoc('name', 'text')])->assertOk();
    publish($this, $hidden);
    bearer($this, $walled)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/v1/data/hidden_things')->assertNotFound();
});

it('runs workflow transitions by key and describes the API in OpenAPI 3.1 for the token', function () {
    $o = orderForm($this);
    $secret = token($this, ["form:{$o['form']}:write"]);
    $uuid = bearer($this, $secret)->postJson('/api/v1/data/orders', ['values' => ['reference' => 'B-7']])->assertCreated()->json('data.uuid');
    bearer($this, $secret)->postJson("/api/v1/data/orders/{$uuid}/transitions/ship", ['row_version' => 1])->assertOk();
    expect(DB::table('status_history')->where('source', 'api')->count())->toBe(1);
    bearer($this, $secret)->postJson("/api/v1/data/orders/{$uuid}/transitions/nope", ['row_version' => 2])->assertNotFound();

    $doc = bearer($this, $secret)->getJson('/api/v1/openapi.json')->assertOk()->json();
    expect($doc['openapi'])->toStartWith('3.1')
        ->and(array_keys($doc['paths']))->toContain('/data/orders', '/data/orders/{record}', '/data/orders/{record}/transitions/{transition}')
        ->and($doc['components']['schemas'])->toHaveKey('Record_orders_values')
        ->and($doc['components']['schemas']['Record_orders_values']['properties'])->toHaveKeys(['reference', 'amount']);
});

it('signs outgoing webhooks, retries failures with backoff, masks the log and never sends what the owner cannot see', function () {
    $o = orderForm($this);
    EgressAllowlistEntry::query()->create(['host_pattern' => 'hooks.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    $this->postJson('/api/v1/webhooks', ['key' => 'bad', 'name' => ['en' => 'Bad'], 'url' => 'https://elsewhere.example.org/x', 'method' => 'POST', 'events' => ['record.created'],
        'timeout_seconds' => 5, 'max_retries' => 0, 'is_active' => true])->assertStatus(422)->assertJsonValidationErrors('url');
    $made = $this->postJson('/api/v1/webhooks', ['key' => 'shop', 'name' => ['en' => 'Shop', 'ar' => 'المتجر'], 'form' => $o['form'], 'url' => 'https://hooks.example.com/in',
        'method' => 'POST', 'events' => ['record.created', 'record.status_changed'], 'headers' => [['name' => 'X-Api-Key', 'value' => 'k-123']],
        'timeout_seconds' => 5, 'max_retries' => 2, 'is_active' => true])->assertCreated();
    $secret = $made->json('data.secret');
    expect($secret)->toStartWith('whsec_')->and($this->getJson('/api/v1/webhooks/'.$made->json('data.uuid'))->json('data'))->not->toHaveKey('secret');

    $seen = [];
    fakeEgress([new Response(503), new Response(500), new Response(200, [], 'ok')], $seen);
    $this->postJson("/api/v1/r/{$o['form']}", ['values' => ['reference' => 'C-1', 'note' => 'private']])->assertCreated();

    expect($seen)->toHaveCount(3);
    $r = $seen[2];
    $body = (string) $r->getBody();
    $ts = $r->getHeaderLine('X-LCF-Timestamp');
    expect($r->getHeaderLine('X-LCF-Signature'))->toBe('sha256='.hash_hmac('sha256', $ts.'.'.$body, $secret))
        ->and($r->getHeaderLine('X-LCF-Event'))->toBe('record.created')
        ->and($r->getHeaderLine('X-Api-Key'))->toBe('k-123')
        ->and(json_decode($body, true)['record']['values'])->toMatchArray(['reference' => 'C-1', 'note' => 'private']);
    $log = DB::table('webhook_deliveries')->first();
    expect($log->status)->toBe('succeeded')->and((int) $log->attempt)->toBe(3)
        ->and($log->request_body)->toContain('"note":"***"')->not->toContain('private')
        ->and(json_decode((string) $log->request_headers, true))->toMatchArray(['X-LCF-Signature' => '***', 'X-Api-Key' => '***']);

    // A record the owner can no longer see is logged as blocked and never sent.
    DB::table('webhooks')->update(['updated_by' => $this->makeUser()->id]);
    $seen = [];
    fakeEgress([], $seen);
    $this->postJson("/api/v1/r/{$o['form']}", ['values' => ['reference' => 'C-2']])->assertCreated();
    expect($seen)->toBe([])->and(DB::table('webhook_deliveries')->orderByDesc('id')->value('status'))->toBe('blocked');

    // A failure past the retries is listed in the Operations Center and can be sent again from there.
    DB::table('webhooks')->update(['updated_by' => $this->admin->id]);
    fakeEgress([new Response(400), new Response(200)], $seen);
    $this->postJson("/api/v1/r/{$o['form']}", ['values' => ['reference' => 'C-3']])->assertCreated();
    $failed = DB::table('webhook_deliveries')->orderByDesc('id')->first();
    expect($failed->status)->toBe('failed');
    $jobs = $this->getJson('/api/v1/ops/jobs?kind=webhook_delivery')->assertOk()->json('data');
    expect(collect($jobs)->pluck('id')->all())->toContain((int) $failed->id);
    $this->postJson('/api/v1/ops/jobs/webhook_delivery/'.$failed->id.'/retry')->assertSuccessful();
    expect(DB::table('webhook_deliveries')->orderByDesc('id')->value('status'))->toBe('succeeded');
});

it('creates and updates records from signed incoming webhooks, and logs every call', function () {
    $o = orderForm($this);
    $made = $this->postJson('/api/v1/inbound-endpoints', ['form' => $o['form'], 'kind' => 'webhook', 'slug' => 'shop-orders', 'operation' => 'upsert',
        'match_field' => $o['f']['reference']['uuid'], 'mapping' => ['reference' => 'order.ref', 'amount' => 'order.total', 'note' => 'order.note'],
        'signature_header' => 'X-Shop-Signature', 'signature_algorithm' => 'hmac_sha256', 'run_as_user' => $this->admin->uuid, 'is_active' => true])->assertCreated();
    $secret = $made->json('data.secret');
    expect($made->json('data.url'))->toEndWith('/api/inbound/shop-orders');
    // The slug is unique across the installation.
    $this->postJson('/api/v1/inbound-endpoints', ['form' => $o['form'], 'kind' => 'api', 'slug' => 'shop-orders', 'operation' => 'create', 'mapping' => ['reference' => 'r'], 'is_active' => true])
        ->assertJsonValidationErrors('slug');

    $send = function (array $body, ?string $signature = null) use ($secret) {
        $raw = json_encode($body);
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->call('POST', '/api/inbound/shop-orders', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SHOP_SIGNATURE' => $signature ?? 'sha256='.hash_hmac('sha256', $raw, $secret)], $raw);
    };
    $send(['order' => ['ref' => 'S-1', 'total' => 5]], 'sha256=00')->assertUnauthorized();
    $first = $send(['order' => ['ref' => 'S-1', 'total' => 5, 'note' => 'leave at door']])->assertCreated();
    $uuid = $first->json('data.record');
    $send(['order' => ['ref' => 'S-1', 'total' => 9]])->assertOk()->assertJsonPath('data.operation', 'updated');
    $send(['order' => ['total' => 9]])->assertStatus(422);

    signedIn($this, $this->admin);
    $record = $this->getJson("/api/v1/r/{$o['form']}/{$uuid}")->assertOk()->json('data');
    expect($record['values']['amount'])->toEqual(9)->and($record['values']['note'])->toBe('leave at door')->and($record['row_version'])->toBe(2)
        ->and(DB::table('submission_journal')->where('source', 'inbound_webhook')->count())->toBe(2);
    $calls = $this->getJson('/api/v1/inbound-endpoints/'.$made->json('data.uuid').'/deliveries')->assertOk()->json('data');
    expect(array_column($calls, 'status'))->toBe(['failed', 'succeeded', 'succeeded', 'signature_invalid']);
    $logged = DB::table('webhook_deliveries')->where('direction', 'incoming')->where('status', 'succeeded')->orderBy('id')->first();
    expect($logged->request_body)->not->toContain('leave at door')
        ->and(json_decode((string) $logged->request_headers, true)['x-shop-signature'])->toBe('***');

    // An inactive endpoint answers as if it did not exist.
    DB::table('inbound_endpoints')->update(['is_active' => false]);
    $send(['order' => ['ref' => 'S-2']])->assertNotFound();
});

it('lets an API-kind endpoint act only for a token with write access to its form', function () {
    $o = orderForm($this);
    $this->postJson('/api/v1/inbound-endpoints', ['form' => $o['form'], 'kind' => 'api', 'slug' => 'orders-api', 'operation' => 'create',
        'mapping' => ['reference' => 'ref'], 'is_active' => true])->assertCreated();
    $read = token($this, ["form:{$o['form']}:read"]);
    signedIn($this, $this->admin);
    $write = token($this, ["form:{$o['form']}:write"]);
    bearer($this, $read)->postJson('/api/inbound/orders-api', ['ref' => 'T-1'])->assertForbidden();
    bearer($this, 'nope')->postJson('/api/inbound/orders-api', ['ref' => 'T-1'])->assertUnauthorized();
    bearer($this, $write)->postJson('/api/inbound/orders-api', ['ref' => 'T-1'])->assertCreated();
    expect(DB::table('submission_journal')->where('source', 'inbound_webhook')->value('user_id'))->toBe($this->admin->id);
});

it('keeps run-as for incoming webhooks to people who manage permissions, as for automations', function () {
    $o = orderForm($this);
    $integrator = $this->makeUser();
    grantPermission($integrator->id, 'system.manage_integrations');
    signedIn($this, $integrator);
    $body = ['form' => $o['form'], 'kind' => 'webhook', 'slug' => 'as-admin', 'operation' => 'create', 'mapping' => ['reference' => 'ref'],
        'signature_header' => 'X-Sig', 'signature_algorithm' => 'hmac_sha512', 'is_active' => true];
    $this->postJson('/api/v1/inbound-endpoints', $body + ['run_as_user' => $this->admin->uuid])->assertJsonValidationErrors('run_as_user');
    $this->postJson('/api/v1/inbound-endpoints', $body + ['run_as_user' => $integrator->uuid])->assertCreated();
    $plain = $this->makeUser();
    signedIn($this, $plain);
    $this->getJson('/api/v1/inbound-endpoints')->assertForbidden();
    $this->getJson('/api/v1/webhooks')->assertForbidden();
});
