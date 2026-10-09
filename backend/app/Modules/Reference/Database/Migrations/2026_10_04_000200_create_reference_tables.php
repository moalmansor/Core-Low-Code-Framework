<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Business calendars, holidays, currencies, exchange rates and units of measure
 * (architecture §10.15). Adds the deferred departments.business_calendar_id
 * (ADR-0021). Number sequences follow in a later migration because they
 * reference forms and fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_calendars', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'key', 48);
            $t->string('timezone', 64);
            Columns::json($t, 'working_days');
            Columns::json($t, 'working_hours');
            Columns::code($t, 'country_code', 2, nullable: true);
            $t->boolean('is_default')->default(false);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('holidays', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::timestamps($t);
            Columns::fk($t, 'business_calendar_id', 'business_calendars', 'cascade', nullable: false, index: false);
            $t->date('starts_on');
            $t->date('ends_on');
            Columns::enum($t, 'recurrence', ['none', 'yearly_gregorian', 'yearly_hijri']);
            $t->smallInteger('hijri_month')->nullable();
            $t->smallInteger('hijri_day')->nullable();
            Columns::index($t, ['business_calendar_id', 'starts_on']);
        });

        Schema::create('currencies', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'code', 3);
            $t->string('symbol', 8);
            $t->smallInteger('decimals');
            Columns::enum($t, 'rounding', ['half_up', 'half_even', 'down', 'up']);
            Columns::enum($t, 'symbol_position', ['before', 'after']);
            $t->boolean('is_base')->default(false);
            $t->boolean('is_enabled')->default(true);
            Columns::unique($t, ['organization_id', 'code']);
        });

        Schema::create('exchange_rates', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::org($t);
            Columns::timestamps($t);
            Columns::by($t);
            Columns::fk($t, 'base_currency_id', 'currencies', nullable: false, index: false);
            Columns::fk($t, 'quote_currency_id', 'currencies', nullable: false);
            $t->decimal('rate', 20, 10);
            Columns::dt($t, 'effective_at');
            Columns::enum($t, 'source', ['manual', 'scheduled']);
            Columns::unique($t, ['base_currency_id', 'quote_currency_id', 'effective_at']);
        });

        Schema::create('units_of_measure', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'code', 16);
            Columns::code($t, 'dimension', 32);
            $t->string('symbol', 16);
            Columns::fk($t, 'base_unit_id', 'units_of_measure');
            $t->decimal('factor', 30, 15);
            $t->decimal('offset', 30, 15);
            $t->smallInteger('precision');
            Columns::unique($t, ['organization_id', 'code']);
        });

        Schema::table('departments', function (Blueprint $t): void {
            Columns::fk($t, 'business_calendar_id', 'business_calendars');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $t): void {
            $t->dropForeign('fk_departments_business_calendar_id');
            $t->dropIndex('ix_departments_business_calendar_id');
            $t->dropColumn('business_calendar_id');
        });
        Schema::dropIfExists('units_of_measure');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('business_calendars');
    }
};
