<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Schema management: migration plans and steps, snapshots, reconciliation
 * reports and publish locks (architecture §10.7, §12). Closes the
 * form_versions → migration_plans / schema_snapshots keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_plans', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::fk($t, 'from_version_id', 'form_versions');
            $t->integer('to_version_number');
            Columns::enum($t, 'purpose', ['publish', 'rollback', 'status_mapping', 'repair', 'restore_snapshot']);
            Columns::enum($t, 'status', ['pending', 'locked', 'running', 'applied', 'failed', 'reversing', 'reversed', 'inconsistent']);
            $t->integer('steps_total')->default(0);
            $t->integer('steps_applied')->default(0);
            $t->unsignedBigInteger('snapshot_id')->nullable();
            Columns::index($t, ['snapshot_id']);
            Columns::json($t, 'impact');
            Columns::code($t, 'lock_token', 64, nullable: true);
            Columns::fk($t, 'confirmed_by', 'users');
            Columns::dt($t, 'confirmed_at', nullable: true);
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'finished_at', nullable: true);
            $t->text('error')->nullable();
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['form_id', 'status']);
            Columns::index($t, ['organization_id', 'status']);
        });

        Schema::create('migration_steps', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::timestamps($t);
            Columns::fk($t, 'migration_plan_id', 'migration_plans', 'cascade', nullable: false, index: false);
            $t->integer('sequence');
            Columns::enum($t, 'operation', ['create_table', 'drop_table_archive', 'add_column', 'rename_column', 'alter_column', 'archive_column', 'restore_column', 'add_index', 'drop_index', 'add_foreign_key', 'drop_foreign_key', 'create_pivot', 'copy_data', 'backfill', 'validate_data', 'map_status', 'rename_table']);
            Columns::code($t, 'table_name', 60);
            Columns::json($t, 'forward');
            Columns::json($t, 'reverse');
            $t->longText('sql_preview')->nullable();
            $t->boolean('is_destructive')->default(false);
            $t->boolean('is_online')->default(false);
            $t->bigInteger('estimated_ms')->nullable();
            Columns::enum($t, 'status', ['pending', 'applied', 'failed', 'reversed', 'reverse_failed', 'skipped']);
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'applied_at', nullable: true);
            Columns::dt($t, 'reversed_at', nullable: true);
            $t->bigInteger('duration_ms')->nullable();
            $t->text('error')->nullable();
            Columns::unique($t, ['migration_plan_id', 'sequence']);
        });

        Schema::create('schema_snapshots', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::fk($t, 'migration_plan_id', 'migration_plans');
            Columns::enum($t, 'kind', ['metadata', 'physical_schema', 'data_backup']);
            Columns::json($t, 'tables');
            Columns::code($t, 'disk', 32);
            $t->string('path', 1024);
            $t->bigInteger('size_bytes');
            Columns::hash($t, 'checksum');
            Columns::dt($t, 'expires_at');
            Columns::dt($t, 'restored_at', nullable: true);
            Columns::index($t, ['form_id', 'created_at']);
            Columns::index($t, ['expires_at']);
        });

        Schema::table('migration_plans', function (Blueprint $t): void {
            Columns::foreign($t, 'snapshot_id', 'schema_snapshots');
        });
        Schema::table('form_versions', function (Blueprint $t): void {
            Columns::foreign($t, 'migration_plan_id', 'migration_plans');
            Columns::foreign($t, 'snapshot_id', 'schema_snapshots');
        });

        Schema::create('schema_reconciliation_reports', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::org($t, index: false);
            Columns::timestamps($t);
            Columns::fk($t, 'form_id', 'forms', index: false);
            Columns::enum($t, 'trigger', ['on_demand', 'scheduled', 'post_publish', 'post_failure']);
            Columns::enum($t, 'engine', ['mysql', 'sqlsrv']);
            Columns::enum($t, 'status', ['running', 'clean', 'drift', 'error']);
            $t->integer('difference_count')->default(0);
            Columns::json($t, 'differences');
            Columns::fk($t, 'triggered_by', 'users');
            Columns::dt($t, 'started_at');
            Columns::dt($t, 'finished_at', nullable: true);
            Columns::index($t, ['organization_id', 'created_at']);
            Columns::index($t, ['form_id', 'created_at']);
        });

        Schema::create('publish_locks', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::org($t);
            Columns::timestamps($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::hash($t, 'lock_group');
            Columns::enum($t, 'status', ['held', 'waiting', 'released', 'expired']);
            Columns::fk($t, 'migration_plan_id', 'migration_plans');
            Columns::fk($t, 'owner_user_id', 'users', nullable: false);
            Columns::fk($t, 'blocked_by_lock_id', 'publish_locks');
            Columns::dt($t, 'acquired_at', nullable: true);
            Columns::dt($t, 'heartbeat_at', nullable: true);
            Columns::dt($t, 'expires_at');
            Columns::code($t, 'held_key', 32, nullable: true);
            Columns::unique($t, ['held_key'], nullable: ['held_key']);
            Columns::index($t, ['form_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $t): void {
            $t->dropForeign('fk_form_versions_migration_plan_id');
            $t->dropForeign('fk_form_versions_snapshot_id');
        });
        Schema::table('migration_plans', function (Blueprint $t): void {
            $t->dropForeign('fk_migration_plans_snapshot_id');
        });
        foreach (['publish_locks', 'schema_reconciliation_reports', 'schema_snapshots', 'migration_steps', 'migration_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
