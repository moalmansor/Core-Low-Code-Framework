<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Personal preferences (architecture §10.24): language, time zone, calendar,
 * formats, theme mode. Created in Phase 1 because the language and direction
 * switch of the application shell reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::timestamps($t);
            Columns::fk($t, 'user_id', 'users', onDelete: 'cascade', nullable: false, index: false);
            Columns::unique($t, ['user_id']);
            Columns::code($t, 'locale', 10, nullable: true);
            $t->string('timezone', 64)->nullable();
            Columns::enum($t, 'calendar', ['gregorian', 'hijri', 'both'], nullable: true);
            $t->string('date_format', 32)->nullable();
            Columns::json($t, 'number_format', nullable: true);
            Columns::enum($t, 'digits', ['western', 'arabic_indic'], nullable: true);
            Columns::enum($t, 'theme_mode', ['light', 'dark', 'system']);
            Columns::enum($t, 'density', ['compact', 'normal', 'comfortable'], nullable: true);
            Columns::json($t, 'notification_channels', nullable: true);
            Columns::enum($t, 'digest_frequency', ['none', 'daily', 'weekly']);
            Columns::json($t, 'landing_page', nullable: true);
            Columns::json($t, 'pinned_records', nullable: true);
            Columns::json($t, 'shortcuts', nullable: true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
