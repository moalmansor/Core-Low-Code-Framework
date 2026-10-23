<?php

declare(strict_types=1);

use App\Modules\Blueprints\BlueprintService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** An application with customers and orders linking to them; orders have a workflow and a notification rule. */
function salesApplication($t): array
{
    $app = $t->postJson('/api/v1/applications', ['key' => 'sales', 'name' => ['en' => 'Sales', 'ar' => 'المبيعات']])->assertCreated()->json('data.uuid');
    $customers = $t->postJson('/api/v1/forms', ['kind' => 'form', 'key' => 'customers', 'application' => $app, 'name' => ['en' => 'Customers']])->assertCreated()->json('data.uuid');
    $name = fieldDoc('name', 'text', null, ['validation' => ['required' => true]]);
    saveDraft($t, $customers, [], [$name])->assertOk();
    expect(publish($t, $customers)['status'])->toBe('applied');

    $orders = $t->postJson('/api/v1/forms', ['kind' => 'form', 'key' => 'orders', 'application' => $app, 'name' => ['en' => 'Orders']])->assertCreated()->json('data.uuid');
    $relation = ['uuid' => uid(), 'key' => 'customer', 'type' => 'many_to_one', 'target' => $customers, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $name['uuid'], 'value' => null, 'inverse' => null];
    $item = fieldDoc('item', 'text');
    $customer = fieldDoc('customer', 'lookup', null, ['relation' => $relation['uuid']]);
    saveDraft($t, $orders, [], [$item, $customer], [$relation])->assertOk();
    $new = statusDoc('new', true);
    $done = statusDoc('done', false, true);
    saveWorkflow($t, $orders, [$new, $done], [transitionDoc('finish', $new, $done)])->assertOk();
    expect(publish($t, $orders)['status'])->toBe('applied');
    $hash = $t->getJson("/api/v1/forms/{$orders}/notification-rules")->assertOk()->json('data.hash');
    $t->putJson("/api/v1/forms/{$orders}/notification-rules", ['base_hash' => $hash, 'rules' => [[
        'uuid' => uid(), 'key' => 'order_created', 'name' => ['en' => 'New order'], 'trigger' => 'record_created',
        'fromStatus' => null, 'toStatus' => null, 'field' => null, 'condition' => null, 'schedule' => null, 'template' => null,
        'channels' => ['in_app'], 'recipients' => ['to' => [['type' => 'creator', 'ref' => null]], 'cc' => [], 'bcc' => []],
        'attachments' => null, 'delayMinutes' => null, 'respectPreferences' => true, 'active' => true,
    ]]])->assertOk();

    return compact('app', 'customers', 'orders', 'name', 'item', 'relation');
}

it('clones a whole application: forms published in link order, links pointing at the new forms, workflow, rules and permissions', function () {
    $s = salesApplication($this);
    $this->postJson("/api/v1/applications/{$s['app']}/clone", ['key' => 'sales', 'name' => ['en' => 'Copy'], 'include_mode' => 'everything'])->assertUnprocessable()->assertJsonValidationErrors('key');
    $copy = $this->postJson("/api/v1/applications/{$s['app']}/clone", ['key' => 'sales_west', 'name' => ['en' => 'Sales west'], 'include_mode' => 'everything'])
        ->assertCreated()->json('data');
    expect($copy['forms'])->toHaveCount(2);
    expect(array_column($copy['forms'], 'state'))->toBe(['published', 'published']);
    expect(array_column($copy['forms'], 'key'))->toBe(['sales_west_customers', 'sales_west_orders']);

    [$newCustomers, $newOrders] = array_column($copy['forms'], 'uuid');
    expect($newCustomers)->toBe(BlueprintService::derive($copy['uuid'], $s['customers']));
    // The order's link points at the new customers form, not the original.
    $relations = $this->getJson("/api/v1/forms/{$newOrders}/draft")->assertOk()->json('data.document.relations');
    expect($relations[0]['target'])->toBe($newCustomers);
    expect($this->getJson("/api/v1/forms/{$newOrders}/workflow")->json('data.document.statuses'))->toHaveCount(2);
    expect($this->getJson("/api/v1/forms/{$newOrders}/notification-rules")->json('data.rules'))->toHaveCount(1);

    // The copy works on its own.
    $acme = $this->postJson("/api/v1/r/{$newCustomers}", ['values' => ['name' => 'Acme']])->assertCreated()->json('data.uuid');
    $this->postJson("/api/v1/r/{$newOrders}", ['values' => ['item' => 'Desk', 'customer' => $acme]])->assertCreated();
    $this->postJson("/api/v1/r/{$s['orders']}", ['values' => ['item' => 'Desk', 'customer' => $acme]])->assertUnprocessable();
    expect(DB::table('audit_logs')->where('event', 'application.cloned')->count())->toBe(1);
});

it('makes applications from an application blueprint and propagates a newer version into their drafts, keeping local changes', function () {
    $s = salesApplication($this);
    $bp = $this->postJson('/api/v1/blueprints', ['source_type' => 'application', 'source' => $s['app'], 'include_mode' => 'structure', 'name' => ['en' => 'Sales starter'], 'category' => 'sales', 'is_library' => true])
        ->assertCreated()->json('data.uuid');
    $made = $this->postJson("/api/v1/blueprints/{$bp}/instantiate", ['key' => 'sales_north', 'name' => ['en' => 'Sales north']])->assertCreated()->json('data');
    [$customers, $orders] = array_column($made['forms'], 'uuid');
    expect($this->getJson("/api/v1/forms/{$orders}/notification-rules")->json('data.rules'))->toBe([]);
    $shown = $this->getJson("/api/v1/blueprints/{$bp}")->assertOk()->json('data');
    expect($shown['source']['key'])->toBe('sales');
    expect($shown['instances'][0]['application']['name'])->toBe('Sales north');

    // Locally, the copy's customers form renames its name field's label.
    $doc = $this->getJson("/api/v1/forms/{$customers}/draft")->json('data.document');
    $doc['fields'][0]['i18n']['label']['en'] = 'Customer name';
    $this->putJson("/api/v1/forms/{$customers}/draft", ['document' => $doc, 'draft_updated_at' => $this->getJson("/api/v1/forms/{$customers}/draft")->json('data.draft_updated_at')])->assertOk();

    // The source gains a phone field, changes the name label too, and a new collection of regions.
    $src = $this->getJson("/api/v1/forms/{$s['customers']}/draft")->json('data.document');
    $src['fields'][0]['i18n']['label']['en'] = 'Full name';
    $src['fields'][] = fieldDoc('phone', 'tel');
    $this->putJson("/api/v1/forms/{$s['customers']}/draft", ['document' => $src, 'draft_updated_at' => $this->getJson("/api/v1/forms/{$s['customers']}/draft")->json('data.draft_updated_at')])->assertOk();
    $regions = $this->postJson('/api/v1/forms', ['kind' => 'form', 'key' => 'regions', 'application' => $s['app'], 'name' => ['en' => 'Regions']])->assertCreated()->json('data.uuid');
    saveDraft($this, $regions, [], [fieldDoc('title', 'text')])->assertOk();
    $this->postJson("/api/v1/blueprints/{$bp}/versions", ['include_mode' => 'structure'])->assertCreated()->assertJsonPath('data.version', 2);

    $preview = collect($this->getJson("/api/v1/blueprints/{$bp}/propagation-preview")->assertOk()->json('data'));
    $customerChanges = collect($preview->firstWhere('form.uuid', $customers)['changes']);
    expect($customerChanges->firstWhere('label', 'phone')['status'])->toBe('apply');
    expect($customerChanges->firstWhere('label', 'name')['status'])->toBe('conflict');
    expect($preview->firstWhere('form.state', 'new')['form']['key'])->toBe('regions');

    $results = collect($this->postJson("/api/v1/blueprints/{$bp}/propagate")->assertOk()->json('data'));
    expect($results->pluck('status')->sort()->values()->all())->toBe(['applied', 'created']);
    $after = $this->getJson("/api/v1/forms/{$customers}/draft")->json('data.document.fields');
    expect(array_column($after, 'key'))->toContain('phone');
    expect(collect($after)->firstWhere('key', 'name')['i18n']['label']['en'])->toBe('Customer name');
    expect(DB::table('forms')->where('key', 'sales_north_regions')->value('state'))->toBe('draft');
    // Nothing left to propagate.
    expect($this->getJson("/api/v1/blueprints/{$bp}/propagation-preview")->json('data'))->toBe([]);
});
