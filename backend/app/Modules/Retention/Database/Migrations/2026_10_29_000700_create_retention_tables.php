<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use App\Modules\Retention\Partitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Retention, archiving, legal holds, personal data and storage quotas
 * (architecture §10.25, §19.16, specification §4.26), and the monthly
 * partitioning of audit_logs and error_logs (§19.16), with the months ahead
 * kept in place by the partitions:maintain task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::by($t);
            Columns::enum($t, 'data_class', ['records', 'audit_logs', 'error_logs', 'email_logs', 'notification_logs', 'submission_journal', 'download_files', 'attachments', 'recycle_bin']);
            Columns::fk($t, 'application_id', 'applications');
            Columns::fk($t, 'form_id', 'forms');
            $t->integer('retention_days');
            Columns::enum($t, 'date_basis', ['created_at', 'updated_at', 'final_status_at']);
            Columns::enum($t, 'end_action', ['archive', 'export_then_delete', 'delete']);
            Columns::code($t, 'archive_disk', 32, nullable: true);
            Columns::fk($t, 'scheduled_task_id', 'scheduled_tasks');
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['organization_id', 'data_class']);
        });

        Schema::create('retention_runs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::timestamps($t);
            Columns::fk($t, 'retention_policy_id', 'retention_policies', nullable: false, index: false);
            Columns::enum($t, 'status', ['running', 'succeeded', 'failed', 'partially_failed']);
            $t->bigInteger('scanned_count')->default(0);
            $t->bigInteger('archived_count')->default(0);
            $t->bigInteger('deleted_count')->default(0);
            $t->bigInteger('skipped_legal_hold');
            Columns::fk($t, 'export_file_id', 'files');
            Columns::dt($t, 'started_at');
            Columns::dt($t, 'finished_at', nullable: true);
            $t->text('error')->nullable();
            Columns::fk($t, 'triggered_by', 'users');
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['retention_policy_id', 'started_at']);
        });

        Schema::create('archived_records', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::code($t, 'source_table', 60);
            Columns::fk($t, 'form_id', 'forms', index: false);
            $t->unsignedBigInteger('record_id');
            Columns::fk($t, 'retention_run_id', 'retention_runs', nullable: false);
            Columns::code($t, 'disk', 32);
            $t->string('path', 1024);
            Columns::hash($t, 'payload_hash');
            Columns::dt($t, 'archived_at');
            Columns::dt($t, 'restorable_until', nullable: true);
            Columns::dt($t, 'restored_at', nullable: true);
            Columns::fk($t, 'restored_by', 'users');
            Columns::index($t, ['form_id', 'record_id']);
            Columns::index($t, ['source_table', 'archived_at']);
        });

        Schema::create('legal_holds', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::timestamps($t);
            Columns::by($t);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::fk($t, 'condition_id', 'conditions');
            $t->text('reason');
            Columns::dt($t, 'placed_at');
            Columns::dt($t, 'lifted_at', nullable: true);
            Columns::fk($t, 'lifted_by', 'users');
            $t->text('lift_reason')->nullable();
            Columns::index($t, ['form_id', 'record_id', 'lifted_at']);
        });

        Schema::create('personal_data_requests', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::by($t);
            Columns::enum($t, 'type', ['locate', 'export', 'delete', 'anonymize']);
            $t->text('subject');
            Columns::hash($t, 'subject_hash');
            Columns::enum($t, 'status', ['received', 'locating', 'awaiting_review', 'executing', 'completed', 'rejected', 'failed']);
            Columns::json($t, 'findings', nullable: true);
            Columns::fk($t, 'result_file_id', 'files');
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::dt($t, 'completed_at', nullable: true);
            Columns::fk($t, 'completed_by', 'users');
            Columns::index($t, ['organization_id', 'status']);
            Columns::index($t, ['subject_hash']);
        });

        Schema::create('subject_keys', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::timestamps($t);
            Columns::fk($t, 'form_id', 'forms', index: false);
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::fk($t, 'user_id', 'users', index: false);
            $t->text('wrapped_key')->nullable();
            Columns::dt($t, 'destroyed_at', nullable: true);
            Columns::fk($t, 'destroyed_by_request_id', 'personal_data_requests');
            Columns::unique($t, ['form_id', 'record_id'], nullable: ['form_id', 'record_id']);
            Columns::unique($t, ['user_id'], nullable: ['user_id']);
        });

        Schema::create('storage_quotas', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::timestamps($t);
            Columns::by($t);
            Columns::enum($t, 'scope_type', ['organization', 'application', 'form']);
            $t->unsignedBigInteger('scope_id');
            $t->bigInteger('warn_bytes');
            $t->bigInteger('limit_bytes');
            $t->bigInteger('used_bytes')->default(0);
            Columns::dt($t, 'measured_at', nullable: true);
            Columns::enum($t, 'state', ['ok', 'warning', 'exceeded']);
            Columns::unique($t, ['scope_type', 'scope_id']);
        });

        // Monthly partitions of the two logs that grow without bound: this month and the next twelve.
        app(Partitions::class)->partition('audit_logs');
        app(Partitions::class)->partition('error_logs');
    }

    public function down(): void
    {
        foreach (['storage_quotas', 'subject_keys', 'personal_data_requests', 'legal_holds', 'archived_records', 'retention_runs', 'retention_policies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
