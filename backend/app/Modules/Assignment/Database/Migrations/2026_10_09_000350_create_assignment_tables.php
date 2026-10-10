<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assignment, queues, claims and delegation (architecture §10.12, §19.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_rules', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'transition_id', 'transitions');
            Columns::enum($t, 'strategy', ['user', 'role', 'department', 'field_user', 'creator_manager', 'round_robin', 'least_loaded']);
            Columns::enum($t, 'target_type', ['user', 'role', 'department'], nullable: true);
            $t->unsignedBigInteger('target_id')->nullable();
            Columns::fk($t, 'field_id', 'fields');
            Columns::fk($t, 'condition_id', 'conditions');
            $t->integer('due_in_minutes')->nullable();
            $t->boolean('use_working_time')->default(false);
            $t->smallInteger('priority')->default(0);
            Columns::fk($t, 'round_robin_cursor_user_id', 'users');
            $t->integer('sort_order')->default(0);
            Columns::index($t, ['form_id', 'transition_id', 'sort_order']);
        });

        Schema::create('assignments', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::enum($t, 'assignee_type', ['user', 'role', 'department']);
            $t->unsignedBigInteger('assignee_id');
            Columns::fk($t, 'assignment_rule_id', 'assignment_rules');
            Columns::fk($t, 'transition_id', 'transitions');
            Columns::fk($t, 'approval_request_id', 'approval_requests');
            Columns::enum($t, 'status', ['active', 'completed', 'reassigned', 'cancelled']);
            $t->smallInteger('priority')->default(0);
            Columns::dt($t, 'due_at', nullable: true);
            Columns::fk($t, 'assigned_by', 'users');
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::dt($t, 'completed_at', nullable: true);
            Columns::index($t, ['assignee_type', 'assignee_id', 'status', 'due_at']);
            Columns::index($t, ['form_id', 'record_id', 'status']);
        });

        Schema::create('queues', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'type', ['role', 'department']);
            Columns::fk($t, 'role_id', 'roles');
            Columns::fk($t, 'department_id', 'departments');
            $t->integer('claim_timeout_minutes')->nullable();
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('queue_forms', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'queue_id', 'queues', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false);
            Columns::json($t, 'columns');
            $t->integer('sort_order')->default(0);
            Columns::unique($t, ['queue_id', 'form_id']);
        });

        Schema::create('queue_claims', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'queue_id', 'queues');
            Columns::fk($t, 'assignment_id', 'assignments', nullable: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false);
            $t->unsignedBigInteger('record_id');
            Columns::fk($t, 'claimed_by', 'users', nullable: false, index: false);
            Columns::dt($t, 'claimed_at');
            Columns::dt($t, 'released_at', nullable: true);
            Columns::enum($t, 'release_reason', ['released', 'completed', 'timeout', 'reassigned', 'admin'], nullable: true);
            Columns::code($t, 'active_key', 48, nullable: true);
            Columns::unique($t, ['active_key'], ['active_key']);
            Columns::index($t, ['claimed_by', 'released_at']);
        });

        Schema::create('delegations', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'delegator_user_id', 'users', nullable: false, index: false);
            Columns::fk($t, 'delegate_user_id', 'users', nullable: false, index: false);
            Columns::enum($t, 'type', ['delegation', 'out_of_office']);
            Columns::dt($t, 'starts_at');
            Columns::dt($t, 'ends_at');
            $t->text('reason');
            Columns::json($t, 'form_ids', nullable: true);
            Columns::enum($t, 'status', ['scheduled', 'active', 'expired', 'revoked']);
            Columns::dt($t, 'revoked_at', nullable: true);
            Columns::fk($t, 'revoked_by', 'users');
            Columns::index($t, ['delegator_user_id', 'status', 'starts_at']);
            Columns::index($t, ['delegate_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('queue_claims');
        Schema::dropIfExists('queue_forms');
        Schema::dropIfExists('queues');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('assignment_rules');
    }
};
