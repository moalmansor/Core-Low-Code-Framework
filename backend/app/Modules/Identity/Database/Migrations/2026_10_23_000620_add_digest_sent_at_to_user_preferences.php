<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * When the last notification digest was sent to the person (specification
 * §4.36, ADR-0063), so a digest is sent once per period whatever the
 * scheduler's timing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $t): void {
            Columns::dt($t, 'digest_sent_at', nullable: true);
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $t): void {
            $t->dropColumn('digest_sent_at');
        });
    }
};
