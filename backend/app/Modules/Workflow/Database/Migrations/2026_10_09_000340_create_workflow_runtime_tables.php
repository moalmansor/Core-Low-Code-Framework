<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Status history, status mappings and SLA (architecture §10.8, §13.5, §19.2).
 * Deferred (ADR-0021): status_history.external_user_id (Phase 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_history', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::fk($t, 'from_status_id', 'statuses');
            Columns::fk($t, 'to_status_id', 'statuses', nullable: false, index: false);
            Columns::fk($t, 'transition_id', 'transitions');
            Columns::enum($t, 'source', ['user', 'automation', 'sla_escalation', 'status_mapping', 'bulk', 'api', 'external']);
            $t->text('comment')->nullable();
            Columns::json($t, 'attachment_file_ids', nullable: true);
            Columns::fk($t, 'acted_by', 'users');
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::fk($t, 'approval_request_id', 'approval_requests');
            $t->bigInteger('seconds_in_previous')->nullable();
            $t->bigInteger('working_seconds_in_previous')->nullable();
            Columns::code($t, 'correlation_id', 36);
            Columns::dt($t, 'acted_at');
            Columns::index($t, ['form_id', 'record_id', 'acted_at']);
            Columns::index($t, ['to_status_id', 'acted_at']);
        });

        Schema::create('status_mappings', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::fk($t, 'form_version_id', 'form_versions');
            Columns::enum($t, 'change_type', ['rename', 'add', 'remove', 'merge']);
            Columns::code($t, 'from_status_key', 48);
            Columns::fk($t, 'to_status_id', 'statuses');
            $t->integer('records_affected')->default(0);
            Columns::fk($t, 'migration_plan_id', 'migration_plans');
            Columns::dt($t, 'applied_at', nullable: true);
            Columns::index($t, ['form_id', 'form_version_id']);
        });

        Schema::create('sla_rules', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'status_id', 'statuses', nullable: false);
            $t->integer('duration_minutes');
            $t->boolean('use_working_time')->default(false);
            Columns::fk($t, 'business_calendar_id', 'business_calendars');
            $t->integer('warn_before_minutes')->nullable();
            Columns::json($t, 'escalations');
            Columns::fk($t, 'condition_id', 'conditions');
            $t->boolean('is_active')->default(true);
            Columns::dt($t, 'archived_at', nullable: true);
            Columns::index($t, ['form_id', 'status_id']);
        });

        Schema::create('sla_timers', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::fk($t, 'sla_rule_id', 'sla_rules', nullable: false);
            Columns::fk($t, 'status_history_id', 'status_history', nullable: false);
            Columns::dt($t, 'started_at');
            Columns::dt($t, 'due_at');
            Columns::dt($t, 'warned_at', nullable: true);
            Columns::dt($t, 'breached_at', nullable: true);
            $t->smallInteger('escalation_level')->default(0);
            Columns::dt($t, 'next_check_at');
            Columns::enum($t, 'state', ['running', 'warned', 'breached', 'completed', 'cancelled']);
            Columns::dt($t, 'completed_at', nullable: true);
            Columns::index($t, ['state', 'next_check_at']);
            Columns::index($t, ['form_id', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_timers');
        Schema::dropIfExists('sla_rules');
        Schema::dropIfExists('status_mappings');
        Schema::dropIfExists('status_history');
    }
};
