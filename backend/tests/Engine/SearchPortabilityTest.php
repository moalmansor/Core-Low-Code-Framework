<?php

declare(strict_types=1);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

// LIKE wildcards in a search term are literal on both engines (`_` is common in keys).
it('finds forms and people by terms containing LIKE wildcards on every engine', function () {
    [, $form] = createForm($this, 'lk_probe_a');
    createForm($this, 'lkxprobexa');

    $found = collect($this->getJson('/api/v1/forms?search=lk_probe_a')->assertOk()->json('data'))->pluck('uuid')->all();
    expect($found)->toBe([$form]);
    expect($this->getJson('/api/v1/forms?search=50%25')->assertOk()->json('data'))->toBe([]);

    $self = $this->getJson('/api/v1/subject-options?type=user&search='.urlencode(substr($this->admin->email, 0, 4)))->assertOk()->json('data');
    expect(array_column($self, 'uuid'))->toContain(strtolower($this->admin->uuid));
    expect($this->getJson('/api/v1/subject-options?type=user&search=a_b_c_d')->assertOk()->json('data'))->toBe([]);
});
