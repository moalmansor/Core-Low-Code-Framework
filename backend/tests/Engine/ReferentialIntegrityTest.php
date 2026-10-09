<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

/** A form with one lookup to the collection, using the given on-delete rule. */
function referencingForm(TestCase $t, string $key, string $collection, string $rule): string
{
    [, $form] = createForm($t, $key);
    $relation = ['uuid' => uid(), 'key' => 'vendor', 'type' => 'many_to_one', 'target' => $collection, 'kind' => 'reference', 'onDelete' => $rule, 'display' => null, 'value' => null, 'inverse' => null];
    saveDraft($t, $form, [], [fieldDoc('title', 'text'), fieldDoc('vendor', 'lookup', null, ['relation' => $relation['uuid']])], [$relation])->assertOk();
    expect(publish($t, $form)['status'])->toBe('applied');

    return $form;
}

it('applies restrict, cascade and set-null rules when a referenced record is deleted', function () {
    [, $col] = createForm($this, 'vendors', 'collection');
    saveDraft($this, $col, [], [fieldDoc('name', 'text')])->assertOk();
    expect(publish($this, $col)['status'])->toBe('applied');
    $restricted = referencingForm($this, 'contracts', $col, 'restrict');
    $cascaded = referencingForm($this, 'shipments', $col, 'cascade');
    $nulled = referencingForm($this, 'invoices', $col, 'set_null');

    $vendor = $this->postJson("/api/v1/r/{$col}", ['values' => ['name' => 'Acme']])->assertCreated()->json('data');
    $contract = $this->postJson("/api/v1/r/{$restricted}", ['values' => ['title' => 'C-1', 'vendor' => $vendor['uuid']]])->assertCreated()->json('data');
    $shipment = $this->postJson("/api/v1/r/{$cascaded}", ['values' => ['title' => 'S-1', 'vendor' => $vendor['uuid']]])->assertCreated()->json('data');
    $invoice = $this->postJson("/api/v1/r/{$nulled}", ['values' => ['title' => 'I-1', 'vendor' => $vendor['uuid']]])->assertCreated()->json('data');

    // Restrict: refused as a whole while the contract references the vendor; nothing changed.
    $this->deleteJson("/api/v1/r/{$col}/{$vendor['uuid']}", ['row_version' => 1])
        ->assertStatus(409)->assertJsonPath('code', 'referenced')->assertJsonPath('referenced_by.count', 1);
    expect(DB::table('f_shipments')->whereNull('deleted_at')->count())->toBe(1)
        ->and($this->getJson("/api/v1/r/{$nulled}/{$invoice['uuid']}")->json('data.values.vendor'))->toBe($vendor['uuid']);

    // Once the contract is gone, cascade deletes the shipment and set-null clears the invoice.
    $this->deleteJson("/api/v1/r/{$restricted}/{$contract['uuid']}", ['row_version' => 1])->assertNoContent();
    $this->deleteJson("/api/v1/r/{$col}/{$vendor['uuid']}", ['row_version' => 1])->assertNoContent();
    expect(DB::table('f_shipments')->where('uuid', $shipment['uuid'])->value('deleted_at'))->not->toBeNull();
    $after = $this->getJson("/api/v1/r/{$nulled}/{$invoice['uuid']}")->assertOk()->json('data');
    expect($after['values']['vendor'])->toBeNull()->and($after['row_version'])->toBe(2);
    expect(DB::table('audit_logs')->where('event', 'record.deleted')->where('form_id', DB::table('forms')->where('uuid', $cascaded)->value('id'))->exists())->toBeTrue();
});
