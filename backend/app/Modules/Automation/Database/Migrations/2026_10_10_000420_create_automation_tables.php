<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Scheduler and automations (architecture §10.18, §19.9).
 * Deferred (ADR-0021): automation_triggers.inbound_endpoint_id (Phase 5,
 * inbound endpoints arrive with integrations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::enum($t, 'kind', ['system', 'automation', 'download', 'sync', 'retention', 'report', 'reconciliation']);
            Columns::code($t, 'owner_type', 32, nullable: true);
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('name', 255);
            Columns::code($t, 'cron_expression', 64);
            $t->string('timezone', 64);
            $t->boolean('is_enabled')->default(true);
            Columns::dt($t, 'next_run_at');
            Columns::dt($t, 'last_run_at', nullable: true);
            Columns::enum($t, 'last_status', ['succeeded', 'failed', 'running', 'skipped'], nullable: true);
            $t->bigInteger('last_duration_ms')->nullable();
            $t->text('last_error')->nullable();
            Columns::dt($t, 'claimed_until', nullable: true);
            $t->string('claimed_by', 128)->nullable();
            Columns::index($t, ['is_enabled', 'next_run_at']);
            Columns::index($t, ['owner_type', 'owner_id']);
        });

        Schema::create('automations', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::fk($t, 'form_id', 'forms', index: false);
            Columns::code($t, 'key', 48);
            $t->boolean('is_enabled')->default(true);
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::enum($t, 'run_as', ['system', 'triggering_user', 'specific_user']);
            Columns::fk($t, 'run_as_user_id', 'users');
            $t->smallInteger('concurrency_limit');
            Columns::json($t, 'retry_policy');
            $t->integer('max_records_per_run');
            $t->integer('confirm_above')->nullable();
            $t->smallInteger('max_chain_depth')->default(3);
            Columns::index($t, ['form_id', 'is_enabled']);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('automation_triggers', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'automation_id', 'automations', onDelete: 'cascade', nullable: false);
            Columns::enum($t, 'type', ['record_created', 'record_updated', 'record_deleted', 'field_changed', 'status_changed', 'condition_true', 'schedule', 'date_reached', 'inbound_webhook', 'watched_folder', 'manual']);
            Columns::fk($t, 'form_id', 'forms');
            Columns::fk($t, 'field_id', 'fields');
            Columns::fk($t, 'from_status_id', 'statuses');
            Columns::fk($t, 'to_status_id', 'statuses');
            Columns::code($t, 'cron_expression', 64, nullable: true);
            $t->string('timezone', 64)->nullable();
            Columns::fk($t, 'date_field_id', 'fields');
            $t->integer('offset_minutes')->nullable();
            Columns::code($t, 'watched_disk', 32, nullable: true);
            $t->string('watched_path', 1024)->nullable();
            Columns::json($t, 'config', nullable: true);
            Columns::fk($t, 'scheduled_task_id', 'scheduled_tasks');
            Columns::dt($t, 'last_fired_at', nullable: true);
            Columns::index($t, ['type', 'form_id']);
        });

        Schema::create('automation_steps', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'automation_id', 'automations', onDelete: 'cascade', nullable: false, index: false);
            $t->integer('sort_order')->default(0);
            Columns::enum($t, 'type', ['update_fields', 'change_status', 'assign', 'create_linked_record', 'send_email', 'send_notification', 'generate_document', 'run_download', 'call_webhook', 'wait_delay', 'wait_condition']);
            Columns::json($t, 'config');
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::enum($t, 'on_failure', ['stop', 'continue', 'retry']);
            Columns::index($t, ['automation_id', 'sort_order']);
        });

        Schema::create('automation_runs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'automation_id', 'automations', nullable: false, index: false);
            Columns::code($t, 'trigger_type', 32);
            Columns::json($t, 'trigger_ref');
            Columns::fk($t, 'parent_run_id', 'automation_runs');
            $t->smallInteger('chain_depth')->default(0);
            $t->boolean('is_test')->default(false);
            Columns::enum($t, 'status', ['queued', 'awaiting_confirmation', 'running', 'waiting', 'succeeded', 'failed', 'partially_failed', 'cancelled', 'skipped_loop']);
            $t->integer('records_affected')->default(0);
            Columns::json($t, 'steps_log');
            $t->integer('current_step')->nullable();
            Columns::dt($t, 'resume_at', nullable: true);
            $t->smallInteger('attempts')->default(0);
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'finished_at', nullable: true);
            $t->bigInteger('duration_ms')->nullable();
            $t->text('error')->nullable();
            Columns::fk($t, 'triggered_by', 'users');
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['automation_id', 'created_at']);
            Columns::index($t, ['status', 'resume_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');

        Schema::dropIfExists('automation_steps');

        Schema::dropIfExists('automation_triggers');

        Schema::dropIfExists('automations');

        Schema::dropIfExists('scheduled_tasks');
    }
};
