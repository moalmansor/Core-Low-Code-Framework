<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * External data sources and sync jobs (architecture §10.20, specification
 * §4.33).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_data_sources', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'type', ['rest', 'db_view']);
            Columns::json($t, 'rest_config', nullable: true);
            Columns::code($t, 'db_connection', 64, nullable: true);
            Columns::code($t, 'db_view', 128, nullable: true);
            Columns::enum($t, 'auth_type', ['none', 'api_key', 'bearer', 'basic', 'oauth2_client_credentials']);
            $t->text('credentials')->nullable();
            $t->text('headers')->nullable();
            Columns::json($t, 'response_mapping');
            Columns::enum($t, 'cache_policy', ['none', 'ttl', 'stale_while_revalidate']);
            $t->integer('cache_ttl_seconds')->nullable();
            $t->boolean('is_active')->default(true);
            Columns::dt($t, 'last_tested_at', nullable: true);
            Columns::code($t, 'last_test_status', 32, nullable: true);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('sync_jobs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'external_data_source_id', 'external_data_sources', nullable: false);
            Columns::fk($t, 'form_id', 'forms', nullable: false);
            Columns::enum($t, 'direction', ['import', 'export']);
            Columns::json($t, 'field_mapping');
            Columns::json($t, 'match_keys');
            Columns::enum($t, 'conflict_rule', ['source_wins', 'target_wins', 'newest_wins', 'skip']);
            Columns::fk($t, 'run_as_user_id', 'users', nullable: false);
            Columns::fk($t, 'scheduled_task_id', 'scheduled_tasks');
            $t->boolean('is_active')->default(true);
        });

        Schema::create('sync_runs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'sync_job_id', 'sync_jobs', nullable: false, index: false);
            $t->boolean('dry_run')->default(false);
            Columns::enum($t, 'status', ['queued', 'running', 'succeeded', 'failed', 'partially_failed']);
            $t->integer('created_count')->default(0);
            $t->integer('updated_count')->default(0);
            $t->integer('skipped_count')->default(0);
            $t->integer('error_count')->default(0);
            Columns::fk($t, 'log_file_id', 'files');
            Columns::dt($t, 'started_at', nullable: true);
            Columns::dt($t, 'finished_at', nullable: true);
            $t->text('error')->nullable();
            Columns::fk($t, 'triggered_by', 'users');
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['sync_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');

        Schema::dropIfExists('sync_jobs');

        Schema::dropIfExists('external_data_sources');
    }
};
