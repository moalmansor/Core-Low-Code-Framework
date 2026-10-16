<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Background import and export (architecture §10.9, §19.5). Adds the columns
 * deferred to Phase 4 (ADR-0021): submission_journal.import_job_id,
 * justifications.import_job_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mappings', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', onDelete: 'cascade', nullable: false);
            $t->string('name', 255);
            Columns::json($t, 'mapping');
            Columns::enum($t, 'mode', ['insert', 'update', 'upsert']);
            Columns::fk($t, 'key_field_id', 'fields');
        });

        Schema::create('import_jobs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::fk($t, 'user_id', 'users', nullable: false);
            Columns::fk($t, 'file_id', 'files', nullable: false);
            Columns::fk($t, 'import_mapping_id', 'import_mappings');
            Columns::json($t, 'mapping');
            Columns::enum($t, 'mode', ['insert', 'update', 'upsert']);
            Columns::fk($t, 'key_field_id', 'fields');
            $t->boolean('dry_run')->default(false);
            Columns::enum($t, 'status', ['queued', 'validating', 'running', 'completed', 'completed_with_errors', 'failed', 'cancelled']);
            $t->integer('total_rows')->default(0);
            $t->integer('processed_rows')->default(0);
            $t->integer('created_count')->default(0);
            $t->integer('updated_count')->default(0);
            $t->integer('error_count')->default(0);
            $t->integer('last_committed_batch')->default(0);
            Columns::fk($t, 'error_report_file_id', 'files');
            Columns::fk($t, 'justification_id', 'justifications');
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'finished_at', nullable: true);
            $t->text('error')->nullable();
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['form_id', 'created_at']);
            Columns::index($t, ['organization_id', 'status']);
        });

        Schema::create('export_jobs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', nullable: false);
            Columns::fk($t, 'view_id', 'views');
            Columns::fk($t, 'user_id', 'users', nullable: false, index: false);
            Columns::enum($t, 'format', ['xlsx', 'csv', 'pdf']);
            Columns::json($t, 'columns');
            Columns::json($t, 'filters');
            Columns::json($t, 'selected_ids', nullable: true);
            Columns::enum($t, 'status', ['queued', 'running', 'completed', 'failed', 'expired', 'cancelled']);
            $t->integer('row_count')->nullable();
            $t->smallInteger('progress')->default(0);
            Columns::fk($t, 'file_id', 'files');
            Columns::dt($t, 'expires_at', nullable: true);
            $t->text('error')->nullable();
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['user_id', 'created_at']);
            Columns::index($t, ['organization_id', 'status']);
        });

        Schema::table('submission_journal', function (Blueprint $t): void {
            Columns::fk($t, 'import_job_id', 'import_jobs');
        });

        Schema::table('justifications', function (Blueprint $t): void {
            Columns::fk($t, 'import_job_id', 'import_jobs');
        });
    }

    public function down(): void
    {
        Schema::table('justifications', function (Blueprint $t): void {
            $t->dropForeign('fk_justifications_import_job_id');
            $t->dropIndex('ix_justifications_import_job_id');
            $t->dropColumn('import_job_id');
        });

        Schema::table('submission_journal', function (Blueprint $t): void {
            $t->dropForeign('fk_submission_journal_import_job_id');
            $t->dropIndex('ix_submission_journal_import_job_id');
            $t->dropColumn('import_job_id');
        });

        Schema::dropIfExists('export_jobs');

        Schema::dropIfExists('import_jobs');

        Schema::dropIfExists('import_mappings');
    }
};
