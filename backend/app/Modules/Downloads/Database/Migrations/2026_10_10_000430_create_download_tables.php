<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Custom downloads (architecture §10.10, §19.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_profiles', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::dt($t, 'deleted_at', nullable: true);
            Columns::fk($t, 'deleted_by', 'users');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            $t->boolean('is_personal')->default(false);
            Columns::fk($t, 'owner_user_id', 'users');
            Columns::json($t, 'formats');
            Columns::enum($t, 'sheet_mode', ['single', 'per_related_form']);
            $t->string('file_name_pattern', 255);
            Columns::json($t, 'xlsx_options');
            Columns::json($t, 'csv_options');
            Columns::json($t, 'pdf_options');
            $t->boolean('apply_user_filters')->default(false);
            $t->boolean('allow_selected_records')->default(false);
            Columns::json($t, 'available_in');
            Columns::json($t, 'parameters');
            $t->integer('max_rows')->default(0);
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['organization_id', 'deleted_at']);
            Columns::unique($t, ['form_id', 'key']);
        });

        Schema::create('download_profile_columns', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'download_profile_id', 'download_profiles', onDelete: 'cascade', nullable: false, index: false);
            $t->integer('sort_order')->default(0);
            Columns::enum($t, 'kind', ['field', 'calculated', 'static', 'system', 'justification']);
            Columns::json($t, 'path', nullable: true);
            Columns::hash($t, 'path_hash', nullable: true);
            Columns::enum($t, 'system_column', ['record_id', 'status', 'created_by', 'created_at', 'updated_by', 'updated_at', 'last_transition_at'], nullable: true);
            Columns::json($t, 'expression', nullable: true);
            $t->string('static_value', 1024)->nullable();
            Columns::enum($t, 'to_many_mode', ['flatten', 'aggregate', 'separate_sheet'], nullable: true);
            Columns::enum($t, 'aggregate_fn', ['count', 'sum', 'avg', 'min', 'max', 'first', 'last', 'join'], nullable: true);
            $t->string('join_separator', 16)->nullable();
            Columns::code($t, 'sheet_key', 48, nullable: true);
            $t->smallInteger('width')->nullable();
            Columns::json($t, 'format', nullable: true);
            Columns::index($t, ['download_profile_id', 'sort_order']);
        });

        Schema::create('download_profile_filters', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'download_profile_id', 'download_profiles', onDelete: 'cascade', nullable: false);
            Columns::json($t, 'path');
            Columns::code($t, 'operator', 32);
            Columns::json($t, 'value', nullable: true);
            Columns::json($t, 'value_expression', nullable: true);
            Columns::code($t, 'parameter_key', 48, nullable: true);
            $t->integer('sort_order')->default(0);
        });

        Schema::create('download_schedules', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'download_profile_id', 'download_profiles', onDelete: 'cascade', nullable: false);
            Columns::enum($t, 'frequency', ['daily', 'weekly', 'monthly', 'cron']);
            Columns::code($t, 'cron_expression', 64, nullable: true);
            $t->string('timezone', 64);
            Columns::enum($t, 'format', ['xlsx', 'csv', 'pdf']);
            Columns::json($t, 'parameters', nullable: true);
            Columns::json($t, 'recipients');
            Columns::enum($t, 'run_as_policy', ['per_recipient']);
            Columns::fk($t, 'scheduled_task_id', 'scheduled_tasks');
            $t->boolean('is_active')->default(true);
        });

        Schema::create('download_jobs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'download_profile_id', 'download_profiles', nullable: false, index: false);
            Columns::fk($t, 'download_schedule_id', 'download_schedules');
            Columns::fk($t, 'user_id', 'users', nullable: false, index: false);
            Columns::enum($t, 'format', ['xlsx', 'csv', 'pdf']);
            Columns::json($t, 'parameters', nullable: true);
            Columns::json($t, 'filters_snapshot', nullable: true);
            Columns::json($t, 'selected_ids', nullable: true);
            Columns::json($t, 'effective_columns');
            Columns::enum($t, 'status', ['queued', 'running', 'completed', 'failed', 'expired', 'cancelled']);
            $t->smallInteger('progress')->default(0);
            $t->integer('row_count')->nullable();
            Columns::fk($t, 'file_id', 'files');
            Columns::dt($t, 'expires_at', nullable: true);
            $t->smallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'finished_at', nullable: true);
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['user_id', 'created_at']);
            Columns::index($t, ['organization_id', 'status']);
            Columns::index($t, ['download_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_jobs');

        Schema::dropIfExists('download_schedules');

        Schema::dropIfExists('download_profile_filters');

        Schema::dropIfExists('download_profile_columns');

        Schema::dropIfExists('download_profiles');
    }
};
