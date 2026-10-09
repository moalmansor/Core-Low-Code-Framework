<?php

declare(strict_types=1);

use App\Modules\Reference\Models\BusinessCalendar;
use App\Modules\Reference\Models\NumberSequence;
use App\Modules\Reference\NumberGenerator;
use App\Modules\Reference\WorkingCalendars;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->completeSetup();
    $this->admin = $this->superAdmin();
    $this->flushSession();
    $this->actingAs($this->admin, 'web');
});

it('computes working time over working days, hours and holidays', function () {
    $cal = $this->postJson('/api/v1/business-calendars', [
        'key' => 'riyadh', 'name' => ['en' => 'Riyadh office', 'ar' => 'مكتب الرياض'], 'timezone' => 'Asia/Riyadh',
        'working_days' => [0, 1, 2, 3, 4],
        'working_hours' => array_map(static fn ($d) => ['day' => $d, 'start' => '08:00', 'end' => '16:00'], [0, 1, 2, 3, 4]),
    ])->assertCreated()->json('data');
    // Sunday 2026-10-04 is a one-day holiday.
    $this->postJson("/api/v1/business-calendars/{$cal['uuid']}/holidays", [
        'name' => ['en' => 'Office closure'], 'starts_on' => '2026-10-04', 'ends_on' => '2026-10-04', 'recurrence' => 'none',
    ])->assertCreated();
    $this->postJson('/api/v1/business-calendars', ['key' => 'bad', 'name' => ['en' => 'x'], 'timezone' => 'Nowhere/City', 'working_days' => [], 'working_hours' => []])
        ->assertStatus(422)->assertJsonValidationErrors('timezone');

    $model = BusinessCalendar::query()->where('uuid', $cal['uuid'])->firstOrFail();
    $calendars = app(WorkingCalendars::class);
    // Thursday 2026-10-01 15:00 Riyadh + 120 working minutes: 60 on Thursday, Fri/Sat off, Sunday holiday, 60 on Monday.
    $start = new DateTimeImmutable('2026-10-01T12:00:00Z');
    $end = $calendars->addWorkingMinutes($model, $start, 120);
    expect($end->format('Y-m-d\TH:i:s\Z'))->toBe('2026-10-05T06:00:00Z')
        ->and($calendars->workingMinutesBetween($model, $start, $end))->toBe(120);
});

it('issues numbers from sequences with tokens, padding and yearly reset', function () {
    $this->postJson('/api/v1/number-sequences', [
        'key' => 'bad', 'scope' => 'shared', 'pattern' => 'INV-{nope}', 'padding' => 5, 'step' => 1, 'reset_period' => 'never', 'calendar' => 'gregorian',
    ])->assertStatus(422)->assertJsonValidationErrors('pattern');
    $seq = $this->postJson('/api/v1/number-sequences', [
        'key' => 'invoices', 'scope' => 'shared', 'pattern' => '{prefix}-{yyyy}-{seq:5}', 'prefix' => 'INV', 'padding' => 4,
        'step' => 1, 'reset_period' => 'yearly', 'calendar' => 'gregorian',
    ])->assertCreated()->json('data');
    expect($seq['next_preview'])->toEndWith('-00001');

    $model = NumberSequence::query()->where('uuid', $seq['uuid'])->firstOrFail();
    $numbers = app(NumberGenerator::class);
    $in2026 = new DateTimeImmutable('2026-06-01T10:00:00Z');
    expect($numbers->next($model, $in2026))->toBe('INV-2026-00001')
        ->and($numbers->next($model, $in2026))->toBe('INV-2026-00002')
        ->and($numbers->next($model, new DateTimeImmutable('2027-01-02T10:00:00Z')))->toBe('INV-2027-00001');

    // Adjusting needs a reason and is audited.
    $this->postJson("/api/v1/number-sequences/{$seq['uuid']}/adjust", ['current_value' => 100])->assertStatus(422);
    $this->postJson("/api/v1/number-sequences/{$seq['uuid']}/adjust", ['current_value' => 100, 'reason' => 'Aligning with the legacy system.'])->assertOk();
    expect($numbers->next($model->refresh(), new DateTimeImmutable('2027-03-01T10:00:00Z')))->toBe('INV-2027-00101')
        ->and(DB::table('audit_logs')->where('event', 'number_sequence.adjusted')->exists())->toBeTrue();
});

it('manages currencies, exchange rates and units of measure', function () {
    $this->postJson('/api/v1/currencies', ['code' => 'SAR', 'name' => ['en' => 'Saudi riyal', 'ar' => 'ريال سعودي'], 'symbol' => 'ر.س', 'decimals' => 2, 'rounding' => 'half_up', 'symbol_position' => 'after', 'is_base' => true])->assertCreated();
    $this->postJson('/api/v1/currencies', ['code' => 'USD', 'name' => ['en' => 'US dollar'], 'symbol' => '$', 'decimals' => 2, 'rounding' => 'half_even', 'symbol_position' => 'before'])->assertCreated();
    $this->postJson('/api/v1/currencies', ['code' => 'usd', 'name' => ['en' => 'x'], 'symbol' => 'x', 'decimals' => 2, 'rounding' => 'up', 'symbol_position' => 'before'])->assertStatus(422);
    $this->postJson('/api/v1/exchange-rates', ['base' => 'USD', 'quote' => 'SAR', 'rate' => '3.75', 'effective_at' => '2026-10-01T00:00:00Z'])->assertCreated();
    $this->postJson('/api/v1/exchange-rates', ['base' => 'USD', 'quote' => 'USD', 'rate' => '1', 'effective_at' => '2026-10-01T00:00:00Z'])->assertStatus(422);
    $this->getJson('/api/v1/exchange-rates?base=USD')->assertOk()->assertJsonPath('data.0.rate', '3.75');

    $this->postJson('/api/v1/units', ['code' => 'm', 'name' => ['en' => 'Metre'], 'dimension' => 'length', 'symbol' => 'm', 'factor' => '1', 'offset' => '0', 'precision' => 3])->assertCreated();
    $this->postJson('/api/v1/units', ['code' => 'km', 'name' => ['en' => 'Kilometre'], 'dimension' => 'length', 'symbol' => 'km', 'base_unit' => 'm', 'factor' => '1000', 'offset' => '0', 'precision' => 3])->assertCreated();
    $units = collect($this->getJson('/api/v1/units')->assertOk()->json('data'))->keyBy('code');
    expect($units['km']['base_unit'])->toBe('m')->and($units['km']['factor'])->toStartWith('1000');
});

it('keeps reference data administration behind its permissions', function () {
    $user = $this->makeUser();
    $this->flushSession();
    $this->actingAs($user, 'web');
    $this->postJson('/api/v1/business-calendars', [])->assertForbidden();
    $this->postJson('/api/v1/number-sequences', [])->assertForbidden();
    $this->postJson('/api/v1/currencies', [])->assertForbidden();
    $this->postJson('/api/v1/units', [])->assertForbidden();
});
