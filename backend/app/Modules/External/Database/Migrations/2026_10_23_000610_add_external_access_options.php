<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * External access as built (specification §4.32, ADR-0058):
 * - an external form is open to anyone, or only to signed-in external
 *   users holding one of its external roles;
 * - a tokenized approve or reject link performs one chosen workflow
 *   transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_forms', function (Blueprint $t): void {
            Columns::enum($t, 'audience', ['public', 'accounts'])->default('public');
            Columns::json($t, 'role_ids', nullable: true);
        });

        Schema::table('access_tokens', function (Blueprint $t): void {
            Columns::fk($t, 'transition_id', 'transitions');
        });
    }

    public function down(): void
    {
        Schema::table('access_tokens', function (Blueprint $t): void {
            $t->dropForeign('fk_access_tokens_transition_id');
            $t->dropIndex('ix_access_tokens_transition_id');
            $t->dropColumn('transition_id');
        });

        $checks = Columns::driver() === 'sqlsrv' ? ['ck_external_forms_audience', 'ck_external_forms_role_ids_json'] : ['ck_external_forms_audience'];
        foreach ($checks as $check) {
            DB::statement(Columns::driver() === 'mysql' ? "ALTER TABLE external_forms DROP CHECK {$check}" : "ALTER TABLE external_forms DROP CONSTRAINT {$check}");
        }
        Schema::table('external_forms', function (Blueprint $t): void {
            $t->dropColumn(['audience', 'role_ids']);
        });
    }
};
