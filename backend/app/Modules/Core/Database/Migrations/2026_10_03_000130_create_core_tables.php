<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Locales, translations, settings, egress allowlist, and the transactional
 * outbox (architecture §10.2, §10.3, §10.21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::timestamps($t);
            Columns::code($t, 'code', 10);
            $t->string('native_name', 64);
            Columns::enum($t, 'direction', ['ltr', 'rtl']);
            Columns::enum($t, 'calendar', ['gregorian', 'hijri', 'both']);
            Columns::enum($t, 'digits', ['western', 'arabic_indic']);
            $t->string('date_format', 32);
            Columns::enum($t, 'time_format', ['12h', '24h']);
            Columns::json($t, 'number_format');
            $t->smallInteger('first_day_of_week');
            Columns::fk($t, 'fallback_locale_id', 'locales');
            $t->boolean('is_enabled')->default(true);
            $t->boolean('is_default')->default(false);
            $t->integer('sort_order')->default(0);
            Columns::unique($t, ['code']);
        });

        Schema::create('translations', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::code($t, 'object_type', 64);
            $t->unsignedBigInteger('object_id');
            Columns::code($t, 'field', 191);
            Columns::code($t, 'locale', 10);
            $t->text('value');
            Columns::fk($t, 'updated_by', 'users');
            Columns::dt($t, 'updated_at');
            Columns::unique($t, ['object_type', 'object_id', 'field', 'locale']);
            Columns::index($t, ['organization_id', 'locale', 'object_type']);
            Columns::index($t, ['locale']);
            Columns::foreign($t, 'locale', 'locales', references: 'code');
        });

        Schema::create('settings', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::code($t, 'group', 64);
            Columns::code($t, 'key', 128);
            Columns::json($t, 'value', nullable: true);
            $t->text('encrypted_value')->nullable();
            $t->boolean('is_encrypted')->default(false);
            Columns::fk($t, 'updated_by', 'users');
            Columns::unique($t, ['organization_id', 'group', 'key']);
        });

        Schema::create('egress_allowlist', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::by($t);
            $t->string('host_pattern', 253);
            Columns::json($t, 'ports');
            $t->boolean('allow_http')->default(false);
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'host_pattern']);
        });

        Schema::create('outbox_events', function (Blueprint $t): void {
            Columns::pk($t);
            $t->unsignedBigInteger('organization_id');
            Columns::code($t, 'event_type', 128);
            Columns::json($t, 'payload');
            Columns::code($t, 'correlation_id', 36);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'available_at');
            Columns::dt($t, 'dispatched_at', nullable: true);
            $t->smallInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            Columns::index($t, ['dispatched_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('egress_allowlist');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('translations');
        Schema::dropIfExists('locales');
    }
};
