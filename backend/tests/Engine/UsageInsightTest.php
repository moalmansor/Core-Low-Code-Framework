<?php

declare(strict_types=1);

use App\Modules\Adoption\UsageRecorder;
use App\Modules\Core\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('counts views, starts, submissions, abandons and empty fields per form, and shows them to form builders', function () {
    [, $form] = createForm($this, 'leave');
    $reason = fieldDoc('reason', 'text');
    saveDraft($this, $form, [], [fieldDoc('days', 'number'), $reason])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');
    [, $other] = createForm($this, 'secret_form');
    saveDraft($this, $other, [], [fieldDoc('x', 'text')])->assertOk();
    publish($this, $other);

    $user = $this->makeUser();
    grantPermission($user->id, "form.{$form}.view");
    grantPermission($user->id, "form.{$form}.create");
    $this->flushSession();
    $this->actingAs($user, 'web');
    // Events for a form the person cannot open, and server-side metrics, are ignored.
    $this->postJson('/api/v1/usage', ['events' => [
        ['metric' => 'form_view', 'form' => $form], ['metric' => 'form_view', 'form' => $form], ['metric' => 'form_start', 'form' => $form],
        ['metric' => 'form_abandon', 'form' => $form], ['metric' => 'form_view', 'form' => $other],
    ]])->assertNoContent();
    $this->postJson('/api/v1/usage', ['events' => [['metric' => 'form_submit', 'form' => $form]]])->assertUnprocessable();
    $this->postJson("/api/v1/r/{$form}", ['values' => ['days' => 2]])->assertCreated();
    $this->getJson('/api/v1/usage/forms')->assertForbidden();

    $this->flushSession();
    $this->actingAs($this->admin, 'web');
    $rows = collect($this->getJson('/api/v1/usage/forms')->assertOk()->json('data.forms'));
    $leave = $rows->firstWhere('uuid', $form);
    expect([$leave['views'], $leave['starts'], $leave['submits'], $leave['abandons'], $leave['people']])->toBe([2, 1, 1, 1, 1]);
    expect($leave['completion'])->toEqual(1);
    expect($rows->firstWhere('uuid', $other)['views'])->toBe(0);

    $detail = $this->getJson("/api/v1/usage/forms/{$form}")->assertOk()->json('data');
    expect($detail['people'][0]['name'])->toBe($user->name);
    expect($detail['people'][0]['submits'])->toBe(1);
    expect($detail['empty_fields'])->toHaveCount(1);
    expect($detail['empty_fields'][0]['uuid'])->toBe($reason['uuid']);
    expect($detail['empty_fields'][0]['share'])->toEqual(1);
    expect($detail['daily'][0]['form_view'])->toBe(2);
});

it('counts without names when set to anonymous, not at all when off, and forgets old counts', function () {
    [, $form] = createForm($this, 'visits');
    saveDraft($this, $form, [], [fieldDoc('note', 'text')])->assertOk();
    publish($this, $form);
    $settings = app(SettingsService::class);

    $settings->write('adoption', 'usage_tracking', 'anonymous');
    $this->postJson('/api/v1/usage', ['events' => [['metric' => 'form_view', 'form' => $form]]])->assertNoContent();
    expect(DB::table('usage_metrics')->whereNotNull('user_id')->count())->toBe(0);
    expect((int) DB::table('usage_metrics')->sum('count'))->toBe(1);
    expect($this->getJson("/api/v1/usage/forms/{$form}")->json('data.anonymous'))->toBeTrue();

    $settings->write('adoption', 'usage_tracking', 'off');
    $this->postJson('/api/v1/usage', ['events' => [['metric' => 'form_view', 'form' => $form]]])->assertNoContent();
    $this->getJson('/api/v1/search?q=anything')->assertOk();
    expect((int) DB::table('usage_metrics')->sum('count'))->toBe(1);

    $settings->write('adoption', 'usage_tracking', 'people');
    app(UsageRecorder::class)->count($this->admin, 'search', at: Carbon::now('UTC')->subDays(400));
    expect(app(UsageRecorder::class)->prune())->toBe(1);
    expect((int) DB::table('usage_metrics')->sum('count'))->toBe(1);
});
