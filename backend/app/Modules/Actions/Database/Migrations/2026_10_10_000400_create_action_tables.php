<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Actions and bulk operations (architecture §10.9, §10.16, §19.5).
 * Adds the columns deferred to Phase 4 (ADR-0021): justification_rules.action_id,
 * justifications.bulk_operation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operations', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::enum($t, 'type', ['update', 'reassign', 'status_change', 'delete', 'restore', 'duplicate_sweep', 'validation_sweep', 'orphan_scan', 'repair']);
            Columns::json($t, 'criteria');
            Columns::json($t, 'payload', nullable: true);
            $t->boolean('dry_run')->default(false);
            $t->integer('preview_count')->nullable();
            $t->integer('max_count')->default(0);
            $t->boolean('confirmed_above_max')->default(false);
            Columns::enum($t, 'status', ['previewing', 'awaiting_confirmation', 'queued', 'running', 'completed', 'completed_with_errors', 'failed', 'cancelled']);
            $t->smallInteger('progress')->default(0);
            $t->integer('affected_count')->default(0);
            $t->integer('failed_count')->default(0);
            Columns::json($t, 'result', nullable: true);
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::fk($t, 'created_by', 'users', nullable: false);
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'finished_at', nullable: true);
            $t->text('error')->nullable();
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['form_id', 'created_at']);
            Columns::index($t, ['organization_id', 'status']);
        });

        Schema::create('actions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', onDelete: 'cascade', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'kind', ['builtin', 'custom']);
            Columns::enum($t, 'builtin', ['export', 'import', 'print', 'duplicate', 'bulk_delete', 'bulk_status', 'bulk_update', 'download'], nullable: true);
            Columns::json($t, 'placements');
            Columns::fk($t, 'condition_id', 'conditions');
            $t->boolean('requires_confirmation')->default(false);
            Columns::enum($t, 'run_mode', ['sync', 'queued', 'auto']);
            $t->integer('queue_threshold')->nullable();
            $t->integer('max_records')->nullable();
            Columns::enum($t, 'justification_level', ['not_required', 'optional', 'mandatory']);
            $t->string('icon', 64)->nullable();
            $t->string('color', 16)->nullable();
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['form_id', 'key']);
        });

        Schema::create('action_steps', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'action_id', 'actions', onDelete: 'cascade', nullable: false, index: false);
            $t->integer('sort_order')->default(0);
            Columns::enum($t, 'type', ['update_fields', 'change_status', 'send_email', 'send_notification', 'call_webhook', 'generate_document', 'create_linked_record', 'assign', 'run_download']);
            Columns::json($t, 'config');
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::enum($t, 'on_failure', ['stop', 'continue']);
            Columns::index($t, ['action_id', 'sort_order']);
        });

        Schema::table('justification_rules', function (Blueprint $t): void {
            Columns::fk($t, 'action_id', 'actions');
        });

        Schema::table('justifications', function (Blueprint $t): void {
            Columns::fk($t, 'bulk_operation_id', 'bulk_operations');
        });
    }

    public function down(): void
    {
        Schema::table('justifications', function (Blueprint $t): void {
            $t->dropForeign('fk_justifications_bulk_operation_id');
            $t->dropIndex('ix_justifications_bulk_operation_id');
            $t->dropColumn('bulk_operation_id');
        });

        Schema::table('justification_rules', function (Blueprint $t): void {
            $t->dropForeign('fk_justification_rules_action_id');
            $t->dropIndex('ix_justification_rules_action_id');
            $t->dropColumn('action_id');
        });

        Schema::dropIfExists('action_steps');

        Schema::dropIfExists('actions');

        Schema::dropIfExists('bulk_operations');
    }
};
