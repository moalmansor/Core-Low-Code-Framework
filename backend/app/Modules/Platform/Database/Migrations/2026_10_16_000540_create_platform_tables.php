<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * REST API tokens, webhooks, inbound endpoints, configuration packages and
 * drift reports (architecture §10.22, specification §4.19–4.22). Adds the
 * column deferred to Phase 5 (ADR-0021): automation_triggers.inbound_endpoint_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::code($t, 'tokenable_type', 64);
            $t->unsignedBigInteger('tokenable_id');
            $t->string('name', 255);
            Columns::hash($t, 'token');
            Columns::json($t, 'abilities');
            Columns::json($t, 'ip_allowlist', nullable: true);
            $t->integer('rate_limit_per_minute')->nullable();
            Columns::dt($t, 'last_used_at', nullable: true);
            Columns::dt($t, 'expires_at', nullable: true);
            Columns::dt($t, 'revoked_at', nullable: true);
            Columns::fk($t, 'created_by', 'users');
            Columns::index($t, ['tokenable_type', 'tokenable_id']);
            Columns::unique($t, ['token']);
        });

        Schema::create('webhooks', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms');
            Columns::code($t, 'key', 48);
            $t->string('url', 2048);
            Columns::enum($t, 'method', ['POST', 'PUT', 'PATCH', 'DELETE', 'GET']);
            $t->text('headers')->nullable();
            Columns::json($t, 'body_template', nullable: true);
            Columns::json($t, 'events');
            $t->text('secret');
            $t->smallInteger('timeout_seconds');
            $t->smallInteger('max_retries');
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('inbound_endpoints', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', nullable: false);
            Columns::enum($t, 'kind', ['api', 'webhook']);
            Columns::code($t, 'slug', 64);
            Columns::enum($t, 'operation', ['create', 'update', 'upsert']);
            Columns::fk($t, 'match_field_id', 'fields');
            Columns::json($t, 'payload_mapping');
            $t->text('signature_secret')->nullable();
            $t->string('signature_header', 64)->nullable();
            Columns::enum($t, 'signature_algorithm', ['hmac_sha256', 'hmac_sha512'], nullable: true);
            Columns::fk($t, 'run_as_user_id', 'users', nullable: false);
            Columns::fk($t, 'initial_status_id', 'statuses');
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'slug']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::enum($t, 'direction', ['outgoing', 'incoming']);
            Columns::fk($t, 'webhook_id', 'webhooks', index: false);
            Columns::fk($t, 'inbound_endpoint_id', 'inbound_endpoints', index: false);
            Columns::fk($t, 'action_step_id', 'action_steps');
            Columns::fk($t, 'automation_run_id', 'automation_runs');
            $t->string('url', 2048);
            Columns::code($t, 'method', 8);
            $t->string('resolved_ip', 45)->nullable();
            Columns::json($t, 'request_headers', nullable: true);
            $t->text('request_body')->nullable();
            $t->smallInteger('response_status')->nullable();
            Columns::json($t, 'response_headers', nullable: true);
            $t->text('response_body')->nullable();
            $t->integer('duration_ms')->nullable();
            $t->smallInteger('attempt')->default(0);
            Columns::enum($t, 'status', ['pending', 'succeeded', 'failed', 'retrying', 'blocked', 'signature_invalid']);
            $t->text('error')->nullable();
            Columns::dt($t, 'next_attempt_at', nullable: true);
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['organization_id', 'status', 'created_at']);
            Columns::index($t, ['webhook_id', 'created_at']);
            Columns::index($t, ['inbound_endpoint_id', 'created_at']);
        });

        Schema::create('environment_drift_reports', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            $t->string('source_label', 128);
            $t->string('target_label', 128);
            Columns::hash($t, 'source_manifest_hash');
            Columns::json($t, 'metadata_differences');
            Columns::json($t, 'schema_differences');
            $t->boolean('is_ambiguous')->default(false);
            Columns::json($t, 'conflicting_objects', nullable: true);
            Columns::index($t, ['organization_id', 'created_at']);
        });

        Schema::create('config_packages', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::enum($t, 'direction', ['export', 'import']);
            $t->string('name', 255);
            Columns::json($t, 'manifest');
            Columns::fk($t, 'file_id', 'files');
            Columns::hash($t, 'checksum');
            $t->string('source_environment', 128)->nullable();
            Columns::enum($t, 'status', ['draft', 'validated', 'conflicts', 'applying', 'applied', 'failed', 'rolled_back']);
            Columns::json($t, 'conflict_report', nullable: true);
            Columns::json($t, 'resolution', nullable: true);
            Columns::fk($t, 'drift_report_id', 'environment_drift_reports');
            Columns::dt($t, 'applied_at', nullable: true);
            Columns::fk($t, 'applied_by', 'users');
            Columns::index($t, ['organization_id', 'status']);
            Columns::index($t, ['organization_id', 'created_at']);
        });

        Schema::table('automation_triggers', function (Blueprint $t): void {
            Columns::fk($t, 'inbound_endpoint_id', 'inbound_endpoints');
        });
    }

    public function down(): void
    {
        Schema::table('automation_triggers', function (Blueprint $t): void {
            $t->dropForeign('fk_automation_triggers_inbound_endpoint_id');
            $t->dropIndex('ix_automation_triggers_inbound_endpoint_id');
            $t->dropColumn('inbound_endpoint_id');
        });

        Schema::dropIfExists('config_packages');

        Schema::dropIfExists('environment_drift_reports');

        Schema::dropIfExists('webhook_deliveries');

        Schema::dropIfExists('inbound_endpoints');

        Schema::dropIfExists('webhooks');

        Schema::dropIfExists('api_tokens');
    }
};
