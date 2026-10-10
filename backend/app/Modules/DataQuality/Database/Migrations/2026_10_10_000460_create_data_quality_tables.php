<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Data quality (architecture §10.16, §19.10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_rules', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', onDelete: 'cascade', nullable: false, index: false);
            Columns::json($t, 'match_fields');
            Columns::enum($t, 'match_mode', ['all', 'any']);
            Columns::enum($t, 'action', ['warn', 'block']);
            Columns::enum($t, 'applies_on', ['create', 'update', 'both']);
            $t->boolean('is_active')->default(true);
            Columns::dt($t, 'last_sweep_at', nullable: true);
            Columns::fk($t, 'last_sweep_operation_id', 'bulk_operations');
            Columns::index($t, ['form_id', 'is_active']);
        });

        Schema::create('merge_history', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('survivor_record_id');
            Columns::json($t, 'merged_record_ids');
            Columns::json($t, 'field_choices');
            Columns::json($t, 'merged_snapshots');
            Columns::json($t, 'relations_repointed');
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::fk($t, 'merged_by', 'users', nullable: false);
            Columns::dt($t, 'merged_at');
            Columns::index($t, ['form_id', 'survivor_record_id']);
        });

        Schema::create('recycle_bin', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            $t->string('title_snapshot', 255);
            Columns::fk($t, 'deleted_by', 'users');
            Columns::dt($t, 'deleted_at');
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::dt($t, 'purge_after');
            Columns::dt($t, 'restored_at', nullable: true);
            Columns::fk($t, 'restored_by', 'users');
            Columns::dt($t, 'purged_at', nullable: true);
            Columns::index($t, ['form_id', 'record_id']);
            Columns::index($t, ['organization_id', 'purge_after', 'purged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recycle_bin');

        Schema::dropIfExists('merge_history');

        Schema::dropIfExists('duplicate_rules');
    }
};
