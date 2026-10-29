<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Modules\Integrations\ExternalSources;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Http\Message\RequestInterface;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    EgressAllowlistEntry::query()->create(['host_pattern' => 'api.example.com', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
});

afterEach(function () {
    DB::statement('DROP VIEW IF EXISTS zz_ext_cities_v');
    Schema::dropIfExists('zz_ext_cities');
});

/** @param  list<Response>  $answers  @param  list<RequestInterface>  $seen */
function sourceEgress(array $answers, array &$seen): void
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
    app()->forgetInstance(ExternalSources::class);
}

function countriesJson(): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['data' => ['items' => [
        ['code' => 'SA', 'name' => 'Saudi Arabia', 'region' => 'asia', 'capital' => 'Riyadh'],
        ['code' => 'EG', 'name' => 'Egypt', 'region' => 'africa', 'capital' => 'Cairo'],
        ['code' => 'JO', 'name' => 'Jordan', 'region' => 'asia', 'capital' => 'Amman'],
    ]]]));
}

function restSource(array $extra = []): array
{
    return array_replace_recursive(['key' => 'countries', 'type' => 'rest', 'name' => ['en' => 'Countries', 'ar' => 'الدول'],
        'rest_config' => ['url' => 'https://api.example.com/countries', 'method' => 'GET', 'query' => [['name' => 'lang', 'value' => 'en']], 'records' => 'data.items'],
        'auth_type' => 'api_key', 'credentials' => ['header' => 'X-Api-Key', 'key' => 'secret-key-1'],
        'response_mapping' => ['value' => 'code', 'label' => 'name', 'parent' => 'region', 'columns' => [['key' => 'capital', 'path' => 'capital', 'label' => ['en' => 'Capital']]]],
        'cache_policy' => 'ttl', 'cache_ttl_seconds' => 300, 'active' => true], $extra);
}

it('reads a REST source through the egress gateway, keeps its key secret, and refuses a host off the allowlist', function () {
    $seen = [];
    sourceEgress([countriesJson()], $seen);
    $this->postJson('/api/v1/data-sources', ['source' => restSource(['rest_config' => ['url' => 'https://elsewhere.example.org/x']])])->assertUnprocessable()->assertJsonValidationErrors('rest_config.url');
    $this->postJson('/api/v1/data-sources', ['source' => restSource(['response_mapping' => ['value' => '']])])->assertUnprocessable()->assertJsonValidationErrors('response_mapping.value');
    $made = $this->postJson('/api/v1/data-sources', ['source' => restSource()])->assertCreated()->json('data');
    expect($made['credentials']['key'])->toBeNull()->and($made['credentials']['key_set'])->toBeTrue()->and(json_encode($made))->not->toContain('secret-key-1');

    $test = $this->postJson("/api/v1/data-sources/{$made['uuid']}/test")->assertOk()->json('data');
    expect($test['ok'])->toBeTrue()->and($test['count'])->toBe(3)->and($test['rows'][0])->toMatchArray(['value' => 'SA', 'label' => 'Saudi Arabia', 'parent' => 'asia'])
        ->and($test['sample']['capital'])->toBe('Riyadh')
        ->and($seen[0]->getHeaderLine('X-Api-Key'))->toBe('secret-key-1')->and($seen[0]->getUri()->getQuery())->toBe('lang=en');

    // An empty secret keeps the saved one.
    $this->putJson("/api/v1/data-sources/{$made['uuid']}", ['source' => restSource(['credentials' => ['key' => '']]), 'base_hash' => $made['hash']])->assertOk();
    expect(DB::table('external_data_sources')->where('uuid', $made['uuid'])->value('credentials'))->not->toContain('secret-key-1');

    $viewer = $this->makeUser();
    $this->flushSession();
    $this->actingAs($viewer, 'web');
    $this->getJson('/api/v1/data-sources')->assertForbidden();
    $this->getJson('/api/v1/data-sources/options')->assertForbidden();
});

it('feeds a choice field: searched and cascading options, labels on the record, and every new value checked on save', function () {
    $seen = [];
    sourceEgress([countriesJson(), new Response(500)], $seen);
    $source = $this->postJson('/api/v1/data-sources', ['source' => restSource()])->assertCreated()->json('data');
    [, $form] = createForm($this, 'trips');
    $region = fieldDoc('region', 'text');
    $country = fieldDoc('country', 'select', null, ['options' => ['source' => 'external', 'external' => $source['uuid'], 'dependsOn' => $region['uuid']]]);
    saveDraft($this, $form, [], [$region, $country])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    $options = $this->getJson("/api/v1/r/{$form}/options/country?depends=asia")->assertOk()->json();
    expect(array_column($options['data'], 'value'))->toBe(['SA', 'JO'])->and($options['meta']['total'])->toBe(2);
    expect(array_column($this->getJson("/api/v1/r/{$form}/options/country?q=egy")->json('data'), 'label'))->toBe(['Egypt']);

    $this->postJson("/api/v1/r/{$form}", ['values' => ['region' => 'asia', 'country' => 'XX']])->assertUnprocessable()->assertJsonValidationErrors('country');
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['region' => 'asia', 'country' => 'JO']])->assertCreated()->json('data');
    expect($this->getJson("/api/v1/r/{$form}/{$rec['uuid']}")->json('data.references.country'))->toBe(['JO' => 'Jordan']);
    // One call served all of it: the list is cached for the source's five minutes.
    expect($seen)->toHaveCount(1);

    // Without a cache, a source that is down blocks a new choice, never the record's existing one.
    DB::table('external_data_sources')->where('uuid', $source['uuid'])->update(['cache_policy' => 'none', 'cache_ttl_seconds' => null, 'updated_at' => now()->format('Y-m-d H:i:s.u')]);
    $this->patchJson("/api/v1/r/{$form}/{$rec['uuid']}", ['row_version' => $rec['row_version'], 'values' => ['region' => 'asia']])->assertOk();
    $this->postJson("/api/v1/r/{$form}", ['values' => ['country' => 'SA']])->assertUnprocessable()->assertJsonPath('errors.country.0', __('integrations.cannot_check'));
    $failure = collect($this->getJson('/api/v1/ops/jobs?kind=data_source')->json('data'))->first();
    expect($failure['subject'])->toBe('Countries')->and($failure['error'])->toContain('500');

    // The source cannot be deleted while a form chooses from it.
    $this->deleteJson("/api/v1/data-sources/{$source['uuid']}")->assertUnprocessable()->assertJsonValidationErrors('source');
});

it('reads a view of an environment-defined database connection and shows matching rows in a record panel', function () {
    config(['database.connections.external_main' => config('database.connections.'.config('database.default'))]);
    Schema::create('zz_ext_cities', function ($t): void {
        $t->string('code', 8);
        $t->string('name', 64);
        $t->string('country', 2);
    });
    DB::table('zz_ext_cities')->insert([['code' => 'RUH', 'name' => 'Riyadh', 'country' => 'SA'], ['code' => 'JED', 'name' => 'Jeddah', 'country' => 'SA'], ['code' => 'CAI', 'name' => 'Cairo', 'country' => 'EG']]);
    DB::statement('CREATE VIEW zz_ext_cities_v AS SELECT code, name, country FROM zz_ext_cities');

    $doc = ['key' => 'cities', 'type' => 'db_view', 'name' => ['en' => 'Cities'], 'db_connection' => 'main', 'db_view' => 'zz_ext_cities_v',
        'response_mapping' => ['value' => 'code', 'label' => 'name', 'parent' => 'country', 'columns' => [['key' => 'country', 'path' => 'country', 'label' => ['en' => 'Country']]]],
        'cache_policy' => 'none', 'active' => true];
    $this->postJson('/api/v1/data-sources', ['source' => ['db_connection' => 'nowhere'] + $doc])->assertUnprocessable()->assertJsonValidationErrors('db_connection');
    $this->postJson('/api/v1/data-sources', ['source' => ['db_view' => 'zz_ext_cities_v; drop table users'] + $doc])->assertUnprocessable()->assertJsonValidationErrors('db_view');
    $this->postJson('/api/v1/data-sources', ['source' => array_replace_recursive($doc, ['response_mapping' => ['label' => 'secret_column']])])->assertUnprocessable()->assertJsonValidationErrors('response_mapping.label');
    $source = $this->postJson('/api/v1/data-sources', ['source' => $doc])->assertCreated()->json('data');
    expect($this->postJson("/api/v1/data-sources/{$source['uuid']}/test")->json('data.count'))->toBe(3);

    [, $form] = createForm($this, 'offices');
    saveDraft($this, $form, [], [fieldDoc('country_code', 'text')])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    $panel = ['uuid' => uid(), 'parent' => null, 'type' => 'external_table', 'order' => 0, 'visibility' => null, 'i18n' => ['title' => ['en' => 'Cities'], 'content' => []],
        'config' => ['source' => $source['uuid'], 'field' => 'country_code', 'match' => 'country', 'columns' => ['country'], 'limit' => 10]];
    $hash = $this->getJson("/api/v1/forms/{$form}/view-panels")->json('data.hash');
    $this->putJson("/api/v1/forms/{$form}/view-panels", ['panels' => [array_replace_recursive($panel, ['config' => ['match' => 'nothing']])], 'base_hash' => $hash])->assertUnprocessable()->assertJsonValidationErrors('panels.0.config.match');
    $this->putJson("/api/v1/forms/{$form}/view-panels", ['panels' => [$panel], 'base_hash' => $hash])->assertOk();
    $rec = $this->postJson("/api/v1/r/{$form}", ['values' => ['country_code' => 'SA']])->assertCreated()->json('data');
    $shown = collect($this->getJson("/api/v1/r/{$form}/{$rec['uuid']}/panels")->assertOk()->json('data'))->firstWhere('type', 'external_table');
    expect(array_column($shown['data']['rows'], 'label'))->toEqualCanonicalizing(['Riyadh', 'Jeddah'])->and($shown['data']['rows'][0]['country'])->toBe('SA');
});
