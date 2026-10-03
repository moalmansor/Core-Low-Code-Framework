<?php

declare(strict_types=1);

namespace App\Modules\Reference\Http\Controllers;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Models\Locale;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Reference\Models\BusinessCalendar;
use App\Modules\Reference\Models\Currency;
use App\Modules\Reference\Models\ExchangeRate;
use App\Modules\Reference\Models\Holiday;
use App\Modules\Reference\Models\NumberSequence;
use App\Modules\Reference\Models\UnitOfMeasure;
use App\Modules\Reference\NumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reference data, calendars and numbering (specification §4.34): business
 * calendars and holidays, numbering sequences with audited manual adjustment,
 * currencies with historical exchange rates, and units of measure. Lists are
 * readable by form builders, who pick these in field and form settings.
 */
final class ReferenceDataController extends Controller
{
    public function __construct(private readonly AuditWriter $audit) {}

    // ── Business calendars ───────────────────────────────────────────────

    public function calendars(): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_calendars', 'system.manage_forms', 'system.manage_users']), 403);

        return response()->json(['data' => BusinessCalendar::query()->orderBy('key')->get()->map(fn (BusinessCalendar $c) => $this->calendar($c))->values()]);
    }

    public function storeCalendar(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_calendars');
        $data = $this->calendarRules($request, null);
        $cal = DB::transaction(function () use ($data): BusinessCalendar {
            if ($data['is_default'] ?? false) {
                BusinessCalendar::query()->update(['is_default' => false]);
            }
            $cal = BusinessCalendar::query()->create($data);
            $cal->setTranslations('name', $data['name']);

            return $cal;
        });

        return response()->json(['data' => $this->calendar($cal)], 201);
    }

    public function updateCalendar(Request $request, BusinessCalendar $calendar): JsonResponse
    {
        Gate::authorize('system.manage_calendars');
        $data = $this->calendarRules($request, $calendar);
        DB::transaction(function () use ($data, $calendar): void {
            if ($data['is_default'] ?? false) {
                BusinessCalendar::query()->whereKeyNot($calendar->id)->update(['is_default' => false]);
            }
            $calendar->fill($data)->save();
            if (isset($data['name'])) {
                $calendar->setTranslations('name', $data['name']);
            }
        });

        return response()->json(['data' => $this->calendar($calendar)]);
    }

    public function destroyCalendar(BusinessCalendar $calendar): JsonResponse
    {
        Gate::authorize('system.manage_calendars');
        $inUse = DB::table('forms')->where('business_calendar_id', $calendar->id)->exists() || DB::table('departments')->where('business_calendar_id', $calendar->id)->exists();
        abort_if($inUse, 422, __('reference.calendar_in_use'));
        Holiday::query()->where('business_calendar_id', $calendar->id)->delete();
        $calendar->delete();

        return response()->json(null, 204);
    }

    public function holidays(BusinessCalendar $calendar): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_calendars', 'system.manage_forms']), 403);

        return response()->json(['data' => Holiday::query()->where('business_calendar_id', $calendar->id)->orderBy('starts_on')->get()->map(fn (Holiday $h) => $this->holiday($h))->values()]);
    }

    public function storeHoliday(Request $request, BusinessCalendar $calendar): JsonResponse
    {
        Gate::authorize('system.manage_calendars');
        $data = $this->holidayRules($request);
        $h = Holiday::query()->create($data + ['business_calendar_id' => $calendar->id]);
        $h->setTranslations('name', $data['name']);
        $this->audit->record('holiday.created', 'config', null, 'business_calendar', $calendar->id, ['holiday' => $h->uuid]);

        return response()->json(['data' => $this->holiday($h)], 201);
    }

    public function updateHoliday(Request $request, BusinessCalendar $calendar, Holiday $holiday): JsonResponse
    {
        Gate::authorize('system.manage_calendars');
        abort_unless($holiday->business_calendar_id === $calendar->id, 404);
        $data = $this->holidayRules($request);
        $holiday->fill($data)->save();
        $holiday->setTranslations('name', $data['name']);
        $this->audit->record('holiday.updated', 'config', null, 'business_calendar', $calendar->id, ['holiday' => $holiday->uuid]);

        return response()->json(['data' => $this->holiday($holiday)]);
    }

    public function destroyHoliday(BusinessCalendar $calendar, Holiday $holiday): JsonResponse
    {
        Gate::authorize('system.manage_calendars');
        abort_unless($holiday->business_calendar_id === $calendar->id, 404);
        $holiday->delete();
        $this->audit->record('holiday.deleted', 'config', null, 'business_calendar', $calendar->id, ['holiday' => $holiday->uuid]);

        return response()->json(null, 204);
    }

    // ── Number sequences ─────────────────────────────────────────────────

    public function sequences(NumberGenerator $numbers): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_numbering', 'system.manage_forms']), 403);

        return response()->json(['data' => NumberSequence::query()->orderBy('key')->get()->map(fn (NumberSequence $s) => $this->sequence($s, $numbers))->values()]);
    }

    public function storeSequence(Request $request, NumberGenerator $numbers): JsonResponse
    {
        Gate::authorize('system.manage_numbering');
        $data = $this->sequenceRules($request, null);
        $seq = NumberSequence::query()->create($data + ['period_key' => '', 'current_value' => 0]);

        return response()->json(['data' => $this->sequence($seq, $numbers)], 201);
    }

    public function updateSequence(Request $request, NumberSequence $sequence, NumberGenerator $numbers): JsonResponse
    {
        Gate::authorize('system.manage_numbering');
        $sequence->fill($this->sequenceRules($request, $sequence))->save();

        return response()->json(['data' => $this->sequence($sequence, $numbers)]);
    }

    /** Controlled, audited manual adjustment of the current value (with a mandatory reason). */
    public function adjustSequence(Request $request, NumberSequence $sequence, NumberGenerator $numbers): JsonResponse
    {
        Gate::authorize('system.manage_numbering');
        $data = $request->validate([
            'current_value' => ['required', 'integer', 'min:0', 'max:9000000000000000000'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $old = $sequence->current_value;
        DB::transaction(function () use ($sequence, $data, $old): void {
            $sequence->forceFill(['current_value' => $data['current_value'], 'last_adjusted_at' => now()])->saveQuietly();
            $this->audit->record('number_sequence.adjusted', 'config', [['field_key' => 'current_value', 'old' => $old, 'new' => (int) $data['current_value']]], 'number_sequence', $sequence->id, ['reason' => $data['reason']]);
        });

        return response()->json(['data' => $this->sequence($sequence->refresh(), $numbers)]);
    }

    // ── Currencies and rates ─────────────────────────────────────────────

    public function currencies(): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_currencies', 'system.manage_forms']), 403);

        return response()->json(['data' => Currency::query()->orderByDesc('is_base')->orderBy('code')->get()->map(fn (Currency $c) => $this->currency($c))->values()]);
    }

    public function storeCurrency(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_currencies');
        $data = $this->currencyRules($request, null);
        $c = DB::transaction(function () use ($data): Currency {
            if ($data['is_base'] ?? false) {
                Currency::query()->update(['is_base' => false]);
            }
            $c = Currency::query()->create($data);
            $c->setTranslations('name', $data['name']);

            return $c;
        });

        return response()->json(['data' => $this->currency($c)], 201);
    }

    public function updateCurrency(Request $request, Currency $currency): JsonResponse
    {
        Gate::authorize('system.manage_currencies');
        $data = $this->currencyRules($request, $currency);
        DB::transaction(function () use ($data, $currency): void {
            if ($data['is_base'] ?? false) {
                Currency::query()->whereKeyNot($currency->id)->update(['is_base' => false]);
            }
            $currency->fill($data)->save();
            if (isset($data['name'])) {
                $currency->setTranslations('name', $data['name']);
            }
        });

        return response()->json(['data' => $this->currency($currency)]);
    }

    public function rates(Request $request): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_currencies', 'system.manage_forms']), 403);
        $data = $request->validate(['base' => ['sometimes', 'string', 'size:3'], 'quote' => ['sometimes', 'string', 'size:3']]);
        $codes = Currency::query()->pluck('code', 'id');
        $q = ExchangeRate::query()->orderByDesc('effective_at');
        if (isset($data['base'])) {
            $q->where('base_currency_id', Currency::query()->where('code', $data['base'])->value('id'));
        }
        if (isset($data['quote'])) {
            $q->where('quote_currency_id', Currency::query()->where('code', $data['quote'])->value('id'));
        }

        return response()->json(['data' => $q->limit(500)->get()->map(static fn (ExchangeRate $r) => [
            'id' => $r->id, 'base' => $codes[$r->base_currency_id] ?? null, 'quote' => $codes[$r->quote_currency_id] ?? null,
            'rate' => rtrim(rtrim((string) $r->rate, '0'), '.'), 'effective_at' => $r->effective_at->toIso8601ZuluString(), 'source' => $r->source,
        ])->values()]);
    }

    /** Rates are append-only so past records keep the rate of their date. */
    public function storeRate(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_currencies');
        $data = $request->validate([
            'base' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'quote' => ['required', 'string', 'size:3', 'different:base', Rule::exists('currencies', 'code')],
            'rate' => ['required', 'regex:/^\d{1,10}(\.\d{1,10})?$/', 'not_in:0'],
            'effective_at' => ['required', 'date'],
        ]);
        $rate = ExchangeRate::query()->create([
            'base_currency_id' => Currency::query()->where('code', $data['base'])->value('id'),
            'quote_currency_id' => Currency::query()->where('code', $data['quote'])->value('id'),
            'rate' => $data['rate'],
            'effective_at' => now()->parse($data['effective_at'])->utc(),
            'source' => 'manual',
        ]);

        return response()->json(['data' => ['id' => $rate->id]], 201);
    }

    // ── Units of measure ─────────────────────────────────────────────────

    public function units(): JsonResponse
    {
        abort_unless(Gate::any(['system.manage_reference_data', 'system.manage_forms']), 403);
        $codes = UnitOfMeasure::query()->pluck('code', 'id');

        return response()->json(['data' => UnitOfMeasure::query()->orderBy('dimension')->orderBy('code')->get()->map(static fn (UnitOfMeasure $u) => [
            'uuid' => $u->uuid, 'code' => $u->code, 'dimension' => $u->dimension, 'symbol' => $u->symbol,
            'base_unit' => $codes[$u->base_unit_id] ?? null, 'factor' => (string) $u->factor, 'offset' => (string) $u->offset,
            'precision' => $u->precision, 'name' => $u->translate('name'), 'names' => $u->translationsFor('name'),
        ])->values()]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        Gate::authorize('system.manage_reference_data');
        $data = $this->unitRules($request, null);
        $u = UnitOfMeasure::query()->create($data);
        $u->setTranslations('name', $data['name']);

        return response()->json(['data' => ['uuid' => $u->uuid]], 201);
    }

    public function updateUnit(Request $request, UnitOfMeasure $unit): JsonResponse
    {
        Gate::authorize('system.manage_reference_data');
        $data = $this->unitRules($request, $unit);
        $unit->fill($data)->save();
        if (isset($data['name'])) {
            $unit->setTranslations('name', $data['name']);
        }

        return response()->json(['data' => ['uuid' => $unit->uuid]]);
    }

    public function destroyUnit(UnitOfMeasure $unit): JsonResponse
    {
        Gate::authorize('system.manage_reference_data');
        abort_if(UnitOfMeasure::query()->where('base_unit_id', $unit->id)->exists(), 422, __('reference.unit_in_use'));
        $unit->delete();

        return response()->json(null, 204);
    }

    // ── Presentation and validation ──────────────────────────────────────

    /** @return array<string, mixed> */
    private function calendar(BusinessCalendar $c): array
    {
        return [
            'uuid' => $c->uuid, 'key' => $c->key, 'timezone' => $c->timezone, 'working_days' => $c->working_days,
            'working_hours' => $c->working_hours, 'country_code' => $c->country_code, 'is_default' => $c->is_default,
            'name' => $c->translate('name'), 'names' => $c->translationsFor('name'),
        ];
    }

    /** @return array<string, mixed> */
    private function holiday(Holiday $h): array
    {
        return [
            'uuid' => $h->uuid, 'starts_on' => substr((string) $h->starts_on, 0, 10), 'ends_on' => substr((string) $h->ends_on, 0, 10),
            'recurrence' => $h->recurrence, 'hijri_month' => $h->hijri_month, 'hijri_day' => $h->hijri_day,
            'name' => $h->translate('name'), 'names' => $h->translationsFor('name'),
        ];
    }

    /** @return array<string, mixed> */
    private function sequence(NumberSequence $s, NumberGenerator $numbers): array
    {
        return [
            'uuid' => $s->uuid, 'key' => $s->key, 'scope' => $s->scope, 'pattern' => $s->pattern, 'prefix' => $s->prefix,
            'padding' => $s->padding, 'step' => $s->step, 'reset_period' => $s->reset_period, 'calendar' => $s->calendar,
            'period_key' => $s->period_key, 'current_value' => $s->current_value, 'next_preview' => $numbers->preview($s),
            'form' => $s->form_id === null ? null : DB::table('forms')->where('id', $s->form_id)->value('uuid'),
        ];
    }

    /** @return array<string, mixed> */
    private function currency(Currency $c): array
    {
        return [
            'uuid' => $c->uuid, 'code' => $c->code, 'symbol' => $c->symbol, 'decimals' => $c->decimals, 'rounding' => $c->rounding,
            'symbol_position' => $c->symbol_position, 'is_base' => $c->is_base, 'is_enabled' => $c->is_enabled,
            'name' => $c->translate('name'), 'names' => $c->translationsFor('name'),
        ];
    }

    /** @return array<string, mixed> */
    private function names(Request $request, bool $required): array
    {
        $default = Locale::query()->where('is_default', true)->value('code') ?? 'en';
        $r = $required ? 'required' : 'sometimes';

        return ['name' => [$r, 'array'], 'name.'.$default => [$r, 'string', 'max:255'], 'name.*' => ['nullable', 'string', 'max:255']];
    }

    /** @return array<string, mixed> */
    private function calendarRules(Request $request, ?BusinessCalendar $c): array
    {
        $r = $c === null ? 'required' : 'sometimes';
        $data = $request->validate($this->names($request, $c === null) + [
            'key' => [$r, 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/', Rule::unique('business_calendars', 'key')->where('organization_id', app(TenantContext::class)->organizationId())->ignore($c?->id)],
            'timezone' => [$r, 'timezone:all'],
            'working_days' => [$r, 'array', 'max:7'],
            'working_days.*' => ['integer', 'between:0,6', 'distinct'],
            'working_hours' => [$r, 'array', 'max:50'],
            'working_hours.*.day' => ['required', 'integer', 'between:0,6'],
            'working_hours.*.start' => ['required', 'date_format:H:i'],
            'working_hours.*.end' => ['required', 'date_format:H:i', 'after:working_hours.*.start'],
            'country_code' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['working_days'])) {
            $data['working_days'] = array_values(array_map('intval', $data['working_days']));
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function holidayRules(Request $request): array
    {
        $data = $request->validate($this->names($request, true) + [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'recurrence' => ['required', Rule::in(['none', 'yearly_gregorian', 'yearly_hijri'])],
            'hijri_month' => ['required_if:recurrence,yearly_hijri', 'nullable', 'integer', 'between:1,12'],
            'hijri_day' => ['required_if:recurrence,yearly_hijri', 'nullable', 'integer', 'between:1,30'],
        ]);
        if ($data['recurrence'] !== 'yearly_hijri') {
            $data['hijri_month'] = $data['hijri_day'] = null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function sequenceRules(Request $request, ?NumberSequence $s): array
    {
        $r = $s === null ? 'required' : 'sometimes';
        $data = $request->validate([
            'key' => [$r, 'string', 'regex:/^[a-z][a-z0-9_]{0,47}$/', Rule::unique('number_sequences', 'key')->where('organization_id', app(TenantContext::class)->organizationId())->ignore($s?->id)],
            'scope' => [$r, Rule::in(['form', 'shared'])],
            'form' => ['sometimes', 'nullable', 'uuid', Rule::exists('forms', 'uuid')],
            'pattern' => [$r, 'string', 'max:255'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^[^{}]*$/'],
            'padding' => [$r, 'integer', 'between:1,18'],
            'step' => [$r, 'integer', 'between:1,1000'],
            'reset_period' => [$r, Rule::in(['never', 'daily', 'monthly', 'yearly'])],
            'calendar' => [$r, Rule::in(['gregorian', 'hijri'])],
        ]);
        if (isset($data['pattern']) && ! NumberGenerator::validPattern($data['pattern'])) {
            throw ValidationException::withMessages(['pattern' => __('reference.invalid_pattern')]);
        }
        if (array_key_exists('form', $data)) {
            $data['form_id'] = $data['form'] === null ? null : DB::table('forms')->where('uuid', $data['form'])->value('id');
            unset($data['form']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function currencyRules(Request $request, ?Currency $c): array
    {
        $r = $c === null ? 'required' : 'sometimes';

        return $request->validate($this->names($request, $c === null) + [
            'code' => [$c === null ? 'required' : 'prohibited', 'string', 'regex:/^[A-Z]{3}$/', Rule::unique('currencies', 'code')->where('organization_id', app(TenantContext::class)->organizationId())],
            'symbol' => [$r, 'string', 'max:8'],
            'decimals' => [$r, 'integer', 'between:0,6'],
            'rounding' => [$r, Rule::in(['half_up', 'half_even', 'down', 'up'])],
            'symbol_position' => [$r, Rule::in(['before', 'after'])],
            'is_base' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function unitRules(Request $request, ?UnitOfMeasure $u): array
    {
        $r = $u === null ? 'required' : 'sometimes';
        $data = $request->validate($this->names($request, $u === null) + [
            'code' => [$r, 'string', 'regex:/^[A-Za-z0-9_]{1,16}$/', Rule::unique('units_of_measure', 'code')->where('organization_id', app(TenantContext::class)->organizationId())->ignore($u?->id)],
            'dimension' => [$r, 'string', 'regex:/^[a-z_]{1,32}$/'],
            'symbol' => [$r, 'string', 'max:16'],
            'base_unit' => ['sometimes', 'nullable', 'string', Rule::exists('units_of_measure', 'code')],
            'factor' => [$r, 'regex:/^-?\d{1,15}(\.\d{1,15})?$/'],
            'offset' => [$r, 'regex:/^-?\d{1,15}(\.\d{1,15})?$/'],
            'precision' => [$r, 'integer', 'between:0,15'],
        ]);
        if (array_key_exists('base_unit', $data)) {
            $data['base_unit_id'] = $data['base_unit'] === null ? null : UnitOfMeasure::query()->where('code', $data['base_unit'])->value('id');
            unset($data['base_unit']);
        }

        return $data;
    }
}
