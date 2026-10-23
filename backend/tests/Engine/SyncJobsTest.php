<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Modules\Integrations\ExternalSources;
use App\Modules\Integrations\SyncJobs;
use App\Modules\Scheduling\ScheduledTasks;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Psr\Http\Message\RequestInterface;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    EgressAllowlistEntry::query()->create(['host_pattern' => 'api.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
});

/** @param  list<Response>  $answers  @param  list<RequestInterface>  $seen */
function syncEgress(array $answers, array &$seen): void
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
    foreach ([ExternalSources::class, SyncJobs::class] as $c) {
        app()->forgetInstance($c);
    }
}

/** @param  list<array<string, string|null>>  $items */
function countries(array $items): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['items' => $items]));
}

function syncSetup($t): array
{
    $source = $t->postJson('/api/v1/data-sources', ['source' => ['key' => 'countries', 'type' => 'rest', 'name' => ['en' => 'Countries'],
        'rest_config' => ['url' => 'https://api.example.com/countries', 'method' => 'GET', 'query' => [], 'records' => 'items'], 'auth_type' => 'bearer', 'credentials' => ['token' => 't-1'],
        'response_mapping' => ['value' => 'code', 'label' => 'name', 'parent' => 'region', 'columns' => [['key' => 'changed', 'path' => 'changed', 'label' => ['en' => 'Changed']]]],
        'cache_policy' => 'none', 'active' => true]])->assertCreated()->json('data');
    [, $form] = createForm($t, 'countries');
    saveDraft($t, $form, [], [fieldDoc('code', 'text'), fieldDoc('name', 'text', null, ['validation' => ['required' => true]]), fieldDoc('region', 'text')])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return [$source, $form];
}

function importJob(array $source, string $form, string $runAs, array $extra = []): array
{
    return array_replace(['name' => ['en' => 'Countries import'], 'source' => $source['uuid'], 'form' => $form, 'direction' => 'import', 'run_as' => $runAs,
        'fields' => [['field' => 'code', 'from' => 'value'], ['field' => 'name', 'from' => 'label'], ['field' => 'region', 'from' => 'parent']],
        'match' => ['code'], 'updated_from' => null, 'conflict_rule' => 'source_wins', 'schedule' => null, 'active' => true], $extra);
}

it('imports through the pipeline: a dry run writes nothing, a run creates, a second run updates only what changed, and every run keeps a log', function () {
    $rows = [['code' => 'SA', 'name' => 'Saudi Arabia', 'region' => 'asia'], ['code' => 'EG', 'name' => 'Egypt', 'region' => 'africa'], ['code' => 'JO', 'name' => 'Jordan', 'region' => 'asia']];
    $renamed = $rows;
    $renamed[1]['name'] = 'Arab Republic of Egypt';
    $seen = [];
    syncEgress([countries($rows), countries($rows), countries($renamed)], $seen);
    [$source, $form] = syncSetup($this);
    $this->postJson('/api/v1/sync-jobs', ['job' => importJob($source, $form, $this->admin->uuid, ['match' => []])])->assertUnprocessable()->assertJsonValidationErrors('match');
    $this->postJson('/api/v1/sync-jobs', ['job' => importJob($source, $form, $this->admin->uuid, ['fields' => [['field' => 'code', 'from' => 'nowhere']]])])->assertUnprocessable()->assertJsonValidationErrors('fields.0.from');
    $job = $this->postJson('/api/v1/sync-jobs', ['job' => importJob($source, $form, $this->admin->uuid)])->assertCreated()->json('data');

    $dry = $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run", ['dry_run' => true])->assertStatus(202)->json('data');
    $dryRun = collect($this->getJson("/api/v1/sync-jobs/{$job['uuid']}/runs")->json('data'))->firstWhere('uuid', $dry['uuid']);
    expect($dryRun)->toMatchArray(['status' => 'succeeded', 'dry_run' => true, 'created' => 3]);
    expect($this->getJson("/api/v1/r/{$form}")->json('meta.total'))->toBe(0);
    expect($seen[0]->getHeaderLine('Authorization'))->toBe('Bearer t-1');

    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run")->assertStatus(202);
    expect($this->getJson("/api/v1/r/{$form}")->json('meta.total'))->toBe(3);
    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run")->assertStatus(202);
    $last = $this->getJson("/api/v1/sync-jobs/{$job['uuid']}/runs")->json('data.0');
    expect($last)->toMatchArray(['status' => 'succeeded', 'created' => 0, 'updated' => 1, 'skipped' => 2, 'errors' => 0]);
    $names = collect($this->getJson("/api/v1/r/{$form}")->json('data'))->pluck('values.name')->all();
    expect($names)->toContain('Arab Republic of Egypt');
    // Written by the pipeline as the run-as user, journaled as a sync.
    expect(DB::table('submission_journal')->where('source', 'sync')->count())->toBeGreaterThanOrEqual(4);

    $log = $this->get("/api/v1/sync-jobs/{$job['uuid']}/runs/{$last['uuid']}/log");
    $log->assertOk();
    expect($log->streamedContent())->toContain('updated')->toContain('Nothing to change.');
});

it('applies the conflict rules and reports rows that fail validation, which the Operations Center can run again', function () {
    $seen = [];
    $rows = [['code' => 'SA', 'name' => 'Saudi Arabia', 'region' => 'asia', 'changed' => '2020-01-01T00:00:00Z'], ['code' => '', 'name' => 'Nameless', 'region' => 'nowhere', 'changed' => null]];
    $newer = [['code' => 'SA', 'name' => 'Kingdom of Saudi Arabia', 'region' => 'asia', 'changed' => Carbon::now('UTC')->addDay()->toIso8601ZuluString()]];
    syncEgress([countries($rows), countries($newer), countries($newer), countries($rows)], $seen);
    [$source, $form] = syncSetup($this);
    $job = $this->postJson('/api/v1/sync-jobs', ['job' => importJob($source, $form, $this->admin->uuid, ['conflict_rule' => 'skip'])])->assertCreated()->json('data');

    // The row without a code cannot be matched; the run is partly done.
    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run")->assertStatus(202);
    $run = $this->getJson("/api/v1/sync-jobs/{$job['uuid']}/runs")->json('data.0');
    expect($run)->toMatchArray(['status' => 'partially_failed', 'created' => 1, 'errors' => 1]);
    $failure = collect($this->getJson('/api/v1/ops/jobs?kind=sync_run')->json('data'))->first();
    expect($failure['subject'])->toBe('Countries import')->and($failure['retryable'])->toBeTrue();

    // skip: an existing record is left alone.
    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run")->assertStatus(202);
    expect($this->getJson("/api/v1/sync-jobs/{$job['uuid']}/runs")->json('data.0'))->toMatchArray(['updated' => 0, 'skipped' => 1]);

    // newest_wins needs the "changed" column, and updates when the source is newer.
    $doc = $this->getJson("/api/v1/sync-jobs/{$job['uuid']}")->json('data');
    $this->putJson("/api/v1/sync-jobs/{$job['uuid']}", ['job' => importJob($source, $form, $this->admin->uuid, ['conflict_rule' => 'newest_wins']), 'base_hash' => $doc['hash']])
        ->assertUnprocessable()->assertJsonValidationErrors('updated_from');
    $this->putJson("/api/v1/sync-jobs/{$job['uuid']}", ['job' => importJob($source, $form, $this->admin->uuid, ['conflict_rule' => 'newest_wins', 'updated_from' => 'changed']), 'base_hash' => $doc['hash']])->assertOk();
    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run")->assertStatus(202);
    expect($this->getJson("/api/v1/sync-jobs/{$job['uuid']}/runs")->json('data.0'))->toMatchArray(['updated' => 1, 'errors' => 0]);

    // Run again from the Operations Center.
    $this->postJson("/api/v1/ops/jobs/sync_run/{$failure['id']}/retry")->assertSuccessful();
    expect($this->getJson("/api/v1/sync-jobs/{$job['uuid']}/runs")->json('data'))->toHaveCount(4);
});

it('exports what the run-as user may see, only what changed since the last run, and runs on its schedule', function () {
    $seen = [];
    syncEgress([new Response(201), new Response(201), new Response(201)], $seen);
    [$source, $form] = syncSetup($this);
    $a = $this->postJson("/api/v1/r/{$form}", ['values' => ['code' => 'SA', 'name' => 'Saudi Arabia']])->assertCreated()->json('data');
    $this->postJson("/api/v1/r/{$form}", ['values' => ['code' => 'EG', 'name' => 'Egypt']])->assertCreated();
    $doc = ['name' => ['en' => 'Push countries'], 'source' => $source['uuid'], 'form' => $form, 'direction' => 'export', 'run_as' => $this->admin->uuid,
        'fields' => [['field' => 'code', 'to' => 'country.code'], ['field' => 'name', 'to' => 'country.name']], 'export' => ['url' => 'https://elsewhere.example.org/in', 'method' => 'POST'],
        'conflict_rule' => 'source_wins', 'schedule' => ['cron' => '0 2 * * *', 'timezone' => 'Asia/Riyadh'], 'active' => true];
    $this->postJson('/api/v1/sync-jobs', ['job' => $doc])->assertUnprocessable()->assertJsonValidationErrors('export.url');
    $doc['export']['url'] = 'https://api.example.com/push';
    $job = $this->postJson('/api/v1/sync-jobs', ['job' => $doc])->assertCreated()->json('data');
    expect($job['schedule'])->toBe(['cron' => '0 2 * * *', 'timezone' => 'Asia/Riyadh'])->and($job['next_run_at'])->not->toBeNull();

    // A dry run sends nothing.
    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run", ['dry_run' => true])->assertStatus(202);
    expect($seen)->toHaveCount(0);

    // The scheduler starts the run when it is due.
    DB::table('scheduled_tasks')->where('kind', 'sync')->update(['next_run_at' => Carbon::now('UTC')->subMinute()->format('Y-m-d H:i:s.u')]);
    app(ScheduledTasks::class)->runDue();
    expect($seen)->toHaveCount(2)->and(json_decode((string) $seen[0]->getBody(), true))->toBe(['country' => ['code' => 'SA', 'name' => 'Saudi Arabia']])
        ->and($seen[0]->getHeaderLine('Authorization'))->toBe('Bearer t-1');

    // Only records changed since the last successful run go out next time.
    $this->travel(2)->seconds();
    $this->patchJson("/api/v1/r/{$form}/{$a['uuid']}", ['row_version' => $a['row_version'], 'values' => ['name' => 'Kingdom of Saudi Arabia']])->assertOk();
    $this->postJson("/api/v1/sync-jobs/{$job['uuid']}/run")->assertStatus(202);
    expect($seen)->toHaveCount(3)->and(json_decode((string) $seen[2]->getBody(), true)['country']['name'])->toBe('Kingdom of Saudi Arabia');
});
