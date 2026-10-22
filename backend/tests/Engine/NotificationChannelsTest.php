<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Modules\Notifications\Channels\ChannelDeliveries;
use App\Modules\Notifications\Channels\ChannelProviders;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    EgressAllowlistEntry::query()->create(['host_pattern' => 'sms.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    EgressAllowlistEntry::query()->create(['host_pattern' => 'hooks.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
});

/** @param  list<Response>  $answers  @param  list<RequestInterface>  $seen */
function channelEgress(array $answers, array &$seen): void
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

            return $a;
        };
    }
    app()->instance(EgressGateway::class, new EgressGateway(new AddressPolicy, $resolver, app(CorrelationId::class), HandlerStack::create(new MockHandler($queue))));
    foreach ([ChannelProviders::class, ChannelDeliveries::class] as $c) {
        app()->forgetInstance($c);
    }
}

function smsChannel(array $extra = []): array
{
    return array_replace_recursive(['key' => 'sms_main', 'type' => 'sms', 'provider' => 'http', 'name' => ['en' => 'Text messages', 'ar' => 'الرسائل النصية'],
        'config' => ['url' => 'https://sms.example.com/send', 'body' => '{"to": {{to}}, "message": {{text}}}', 'header_name' => 'X-Api-Key', 'header_value' => 'k-123'], 'active' => true], $extra);
}

it('keeps provider secrets on the server, checks settings, and refuses to remove a channel in use', function () {
    $seen = [];
    channelEgress([new Response(200, [], '{"id":"m1"}')], $seen);
    $this->postJson('/api/v1/notification-channels', ['channel' => smsChannel(['config' => ['url' => 'https://elsewhere.example.org/x']])])->assertUnprocessable()->assertJsonValidationErrors('config.url');
    $this->postJson('/api/v1/notification-channels', ['channel' => smsChannel(['config' => ['body' => '{"to": "n: {{to}}"}']])])->assertUnprocessable()->assertJsonValidationErrors('config.body');
    $this->postJson('/api/v1/notification-channels', ['channel' => smsChannel(['key' => 'email'])])->assertUnprocessable()->assertJsonValidationErrors('key');
    $made = $this->postJson('/api/v1/notification-channels', ['channel' => smsChannel()])->assertCreated()->json('data');
    expect($made['config']['header_value'])->toBeNull()->and($made['config']['header_value_set'])->toBeTrue()
        ->and(json_encode($made))->not->toContain('k-123')
        ->and(DB::table('notification_channels')->where('key', 'sms_main')->value('config'))->not->toContain('k-123');

    // An empty secret keeps the saved one; a stale save is refused.
    $doc = smsChannel(['config' => ['header_value' => '']]);
    $this->putJson("/api/v1/notification-channels/{$made['uuid']}", ['channel' => $doc + ['rate_limit_per_minute' => 30], 'base_hash' => $made['hash']])->assertOk();
    $this->putJson("/api/v1/notification-channels/{$made['uuid']}", ['channel' => $doc, 'base_hash' => $made['hash']])->assertStatus(409);

    // A test message goes through the egress gateway with the filled-in body.
    $answer = $this->postJson("/api/v1/notification-channels/{$made['uuid']}/test", ['to' => '+966500000001'])->assertOk()->json('data');
    expect($answer['ok'])->toBeTrue()->and($seen[0]->getHeaderLine('X-Api-Key'))->toBe('k-123')
        ->and(json_decode((string) $seen[0]->getBody(), true))->toBe(['to' => '+966500000001', 'message' => "Test message\nThis channel is set up correctly."]);

    // Rule authors see the channel to choose it; a rule that uses it keeps it.
    $form = ordersFormForChannels($this);
    $this->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => [channelRule(['sms_main'])], 'base_hash' => $this->getJson("/api/v1/forms/{$form}/notification-rules")->json('data.hash')])->assertOk();
    expect($this->getJson('/api/v1/notification-channels/options')->json('data.0.key'))->toBe('sms_main');
    $this->deleteJson("/api/v1/notification-channels/{$made['uuid']}")->assertUnprocessable()->assertJsonValidationErrors('channel');

    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson('/api/v1/notification-channels')->assertForbidden();
    $this->getJson('/api/v1/notification-channels/options')->assertForbidden();
});

function ordersFormForChannels($t): string
{
    [, $form] = createForm($t, 'orders');
    saveDraft($t, $form, [], [fieldDoc('customer', 'text')])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return $form;
}

/** @param  list<string>  $channels */
function channelRule(array $channels, array $extra = []): array
{
    return array_replace(['uuid' => uid(), 'key' => 'on_create', 'name' => ['en' => 'New order'], 'trigger' => 'record_created', 'fromStatus' => null, 'toStatus' => null, 'field' => null,
        'condition' => null, 'schedule' => null, 'template' => null, 'channels' => $channels,
        'recipients' => ['to' => [['type' => 'creator', 'ref' => null]], 'cc' => [], 'bcc' => []],
        'attachments' => null, 'delayMinutes' => null, 'respectPreferences' => true, 'active' => true], $extra);
}

it('sends a rule by SMS to each recipient with a mobile number and once to a messaging conversation, honouring the SMS preference', function () {
    $seen = [];
    channelEgress([new Response(200), new Response(200), new Response(200)], $seen);
    $sms = $this->postJson('/api/v1/notification-channels', ['channel' => smsChannel()])->assertCreated()->json('data');
    $this->postJson('/api/v1/notification-channels', ['channel' => ['key' => 'ops_room', 'type' => 'messaging', 'provider' => 'slack', 'name' => ['en' => 'Operations room'],
        'config' => ['webhook_url' => 'https://hooks.example.com/services/T0/B0/x']]])->assertCreated();
    $form = ordersFormForChannels($this);
    $this->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => [channelRule(['sms_main', 'ops_room'])], 'base_hash' => $this->getJson("/api/v1/forms/{$form}/notification-rules")->json('data.hash')])->assertOk();
    // An unknown channel is refused.
    $this->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => [channelRule(['pager'])], 'base_hash' => $this->getJson("/api/v1/forms/{$form}/notification-rules")->json('data.hash')])
        ->assertUnprocessable()->assertJsonValidationErrors('rules.0.channels');

    DB::table('users')->where('id', $this->admin->id)->update(['phone' => '+966 50 000 0001']);
    $this->postJson("/api/v1/r/{$form}", ['values' => ['customer' => 'Acme']])->assertCreated();
    $rows = DB::table('notification_deliveries')->orderBy('id')->get();
    expect($rows)->toHaveCount(2)->and($rows->pluck('status')->all())->toBe(['sent', 'sent'])
        ->and($rows->pluck('recipient')->sort()->values()->all())->toBe(['+966500000001', 'ops_room']);
    $hosts = array_map(static fn (RequestInterface $r) => $r->getUri()->getHost(), $seen);
    expect($hosts)->toEqualCanonicalizing(['sms.example.com', 'hooks.example.com']);
    $slack = collect($seen)->first(fn (RequestInterface $r) => $r->getUri()->getHost() === 'hooks.example.com');
    expect(json_decode((string) $slack->getBody(), true)['text'])->toContain('/app/'.$form.'/');

    // Turned off by the person: no SMS, the conversation still gets its message.
    $this->patchJson('/api/v1/me/preferences', ['notification_channels' => ['sms' => false]])->assertOk()->assertJsonPath('data.notification_channels.email', true);
    $this->postJson("/api/v1/r/{$form}", ['values' => ['customer' => 'Globex']])->assertCreated();
    expect(DB::table('notification_deliveries')->count())->toBe(3)->and(DB::table('notification_deliveries')->orderByDesc('id')->value('recipient'))->toBe('ops_room');
    $log = $this->getJson("/api/v1/notification-channels/{$sms['uuid']}/deliveries")->assertOk()->json('data');
    expect($log)->toHaveCount(1)->and($log[0]['recipient'])->toEndWith('0001')->and($log[0]['recipient'])->not->toContain('966');
});

it('retries a provider failure with backoff, then shows it in the Operations Center to send again', function () {
    $seen = [];
    channelEgress([new Response(503), new Response(500), new Response(502), new Response(500), new Response(200)], $seen);
    $sms = $this->postJson('/api/v1/notification-channels', ['channel' => smsChannel()])->assertCreated()->json('data');
    $form = ordersFormForChannels($this);
    $this->putJson("/api/v1/forms/{$form}/notification-rules", ['rules' => [channelRule(['sms_main'])], 'base_hash' => $this->getJson("/api/v1/forms/{$form}/notification-rules")->json('data.hash')])->assertOk();
    DB::table('users')->where('id', $this->admin->id)->update(['phone' => '+966500000001']);
    $this->postJson("/api/v1/r/{$form}", ['values' => ['customer' => 'Acme']])->assertCreated();

    // The synchronous test queue makes one attempt; the rest are what the queued retries would do.
    $id = (int) DB::table('notification_deliveries')->value('id');
    expect(DB::table('notification_deliveries')->where('id', $id)->value('status'))->toBe('pending');
    $deliveries = app(ChannelDeliveries::class);
    expect($deliveries->attempt($id))->toBe(300)->and($deliveries->attempt($id))->toBe(1800)->and($deliveries->attempt($id))->toBeNull();
    $row = DB::table('notification_deliveries')->where('id', $id)->first();
    expect($row->status)->toBe('failed')->and((int) $row->attempts)->toBe(4)->and($row->error)->toContain('500');

    $failure = collect($this->getJson('/api/v1/ops/jobs?kind=channel_delivery')->assertOk()->json('data'))->firstWhere('id', $id);
    expect($failure['subject'])->toBe('Text messages')->and($failure['retryable'])->toBeTrue();
    $this->postJson("/api/v1/ops/jobs/channel_delivery/{$id}/retry")->assertSuccessful();
    expect(DB::table('notification_deliveries')->where('id', $id)->value('status'))->toBe('sent');
    unset($sms);
});
