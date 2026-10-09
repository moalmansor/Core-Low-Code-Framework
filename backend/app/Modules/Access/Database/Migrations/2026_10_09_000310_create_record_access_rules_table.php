<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Record-level access rules (architecture §10.4, §16.6), and the status
 * dimension of field access rules deferred from Phase 2 (ADR-0021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_access_rules', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::enum($t, 'subject_type', ['everyone', 'role', 'department', 'user']);
            $t->unsignedBigInteger('subject_id')->nullable();
            Columns::enum($t, 'operation', ['view', 'edit', 'delete', 'all']);
            Columns::enum($t, 'scope', ['none', 'own', 'own_department', 'department_tree', 'assigned', 'all', 'custom']);
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::enum($t, 'effect', ['allow', 'deny', 'hard_deny']);
            $t->integer('priority')->default(0);
            Columns::index($t, ['form_id', 'subject_type', 'subject_id', 'operation']);
        });

        Schema::table('field_access_rules', function (Blueprint $t): void {
            Columns::fk($t, 'status_id', 'statuses');
            Columns::index($t, ['form_id', 'status_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::table('field_access_rules', function (Blueprint $t): void {
            $t->dropForeign('fk_field_access_rules_status_id');
            $t->dropIndex('ix_field_access_rules_form_id_status_id_mode');
            $t->dropIndex('ix_field_access_rules_status_id');
            $t->dropColumn('status_id');
        });
        Schema::dropIfExists('record_access_rules');
    }
};
