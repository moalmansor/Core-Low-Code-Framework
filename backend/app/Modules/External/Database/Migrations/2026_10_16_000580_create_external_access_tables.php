<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * External forms, external users, tokenized links and signature requests
 * (architecture §10.19, specification §4.32). Adds the columns deferred to
 * Phase 5 (ADR-0021): external_user_id on sessions, files, justifications,
 * status_history and submission_journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_users', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::dt($t, 'deleted_at', nullable: true);
            Columns::fk($t, 'deleted_by', 'users');
            $t->string('email', 255);
            $t->string('name', 255);
            $t->string('password', 255);
            Columns::fk($t, 'role_id', 'roles', nullable: false);
            Columns::enum($t, 'status', ['pending_verification', 'pending_approval', 'approved', 'rejected', 'suspended']);
            Columns::dt($t, 'email_verified_at', nullable: true);
            $t->text('two_factor_secret')->nullable();
            Columns::dt($t, 'two_factor_confirmed_at', nullable: true);
            Columns::fk($t, 'approved_by', 'users');
            Columns::dt($t, 'approved_at', nullable: true);
            Columns::dt($t, 'last_login_at', nullable: true);
            $t->smallInteger('failed_login_count')->default(0);
            Columns::dt($t, 'locked_until', nullable: true);
            Columns::dt($t, 'anonymized_at', nullable: true);
            Columns::index($t, ['organization_id', 'status']);
            Columns::index($t, ['organization_id', 'deleted_at']);
            Columns::unique($t, ['organization_id', 'email']);
        });

        Schema::create('access_tokens', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::hash($t, 'token_hash');
            Columns::enum($t, 'purpose', ['record_action', 'signature', 'external_resume', 'email_verification']);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::enum($t, 'action', ['view', 'approve', 'reject', 'complete_section', 'sign']);
            Columns::code($t, 'section_key', 48, nullable: true);
            $t->string('recipient_email', 255)->nullable();
            $t->smallInteger('max_uses');
            $t->smallInteger('uses')->default(0);
            Columns::dt($t, 'expires_at');
            Columns::dt($t, 'revoked_at', nullable: true);
            Columns::fk($t, 'revoked_by', 'users');
            Columns::dt($t, 'last_used_at', nullable: true);
            $t->string('last_used_ip', 45)->nullable();
            Columns::index($t, ['form_id', 'record_id']);
            Columns::unique($t, ['token_hash']);
        });

        Schema::create('external_forms', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', nullable: false);
            Columns::code($t, 'slug', 64);
            Columns::fk($t, 'theme_id', 'themes');
            Columns::fk($t, 'initial_status_id', 'statuses');
            Columns::json($t, 'exposed_fields');
            $t->boolean('captcha')->default(false);
            $t->integer('rate_limit_per_ip');
            Columns::dt($t, 'opens_at', nullable: true);
            Columns::dt($t, 'closes_at', nullable: true);
            Columns::json($t, 'daily_window', nullable: true);
            $t->integer('submission_cap')->nullable();
            $t->integer('submissions_count')->default(0);
            Columns::enum($t, 'verification', ['none', 'email', 'sms']);
            $t->string('password_hash', 255)->nullable();
            Columns::fk($t, 'run_as_user_id', 'users', nullable: false);
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['slug']);
        });

        Schema::create('submission_throttles', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'external_form_id', 'external_forms', onDelete: 'cascade', nullable: false, index: false);
            Columns::enum($t, 'key_type', ['ip', 'email', 'global']);
            Columns::hash($t, 'key_hash');
            Columns::dt($t, 'window_start');
            $t->integer('count');
            Columns::unique($t, ['external_form_id', 'key_type', 'key_hash', 'window_start']);
        });

        Schema::create('signature_requests', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::enum($t, 'signer_type', ['user', 'external_user', 'email']);
            Columns::fk($t, 'signer_user_id', 'users');
            Columns::fk($t, 'signer_external_user_id', 'external_users');
            $t->string('signer_email', 255)->nullable();
            Columns::fk($t, 'access_token_id', 'access_tokens');
            Columns::fk($t, 'document_template_id', 'document_templates');
            Columns::enum($t, 'status', ['pending', 'viewed', 'signed', 'declined', 'expired', 'cancelled']);
            Columns::fk($t, 'signature_file_id', 'files');
            Columns::fk($t, 'signed_file_id', 'files');
            Columns::hash($t, 'signed_document_hash', nullable: true);
            Columns::dt($t, 'signed_at', nullable: true);
            $t->string('signer_ip', 45)->nullable();
            $t->text('signer_user_agent')->nullable();
            $t->text('decline_reason')->nullable();
            Columns::dt($t, 'expires_at');
            Columns::index($t, ['form_id', 'record_id']);
            Columns::index($t, ['organization_id', 'status']);
        });

        Schema::table('sessions', function (Blueprint $t): void {
            Columns::fk($t, 'external_user_id', 'external_users');
        });

        Schema::table('files', function (Blueprint $t): void {
            Columns::fk($t, 'external_user_id', 'external_users');
        });

        Schema::table('justifications', function (Blueprint $t): void {
            Columns::fk($t, 'external_user_id', 'external_users');
        });

        Schema::table('status_history', function (Blueprint $t): void {
            Columns::fk($t, 'external_user_id', 'external_users');
        });

        Schema::table('submission_journal', function (Blueprint $t): void {
            Columns::fk($t, 'external_user_id', 'external_users');
        });
    }

    public function down(): void
    {
        Schema::table('submission_journal', function (Blueprint $t): void {
            $t->dropForeign('fk_submission_journal_external_user_id');
            $t->dropIndex('ix_submission_journal_external_user_id');
            $t->dropColumn('external_user_id');
        });

        Schema::table('status_history', function (Blueprint $t): void {
            $t->dropForeign('fk_status_history_external_user_id');
            $t->dropIndex('ix_status_history_external_user_id');
            $t->dropColumn('external_user_id');
        });

        Schema::table('justifications', function (Blueprint $t): void {
            $t->dropForeign('fk_justifications_external_user_id');
            $t->dropIndex('ix_justifications_external_user_id');
            $t->dropColumn('external_user_id');
        });

        Schema::table('files', function (Blueprint $t): void {
            $t->dropForeign('fk_files_external_user_id');
            $t->dropIndex('ix_files_external_user_id');
            $t->dropColumn('external_user_id');
        });

        Schema::table('sessions', function (Blueprint $t): void {
            $t->dropForeign('fk_sessions_external_user_id');
            $t->dropIndex('ix_sessions_external_user_id');
            $t->dropColumn('external_user_id');
        });

        Schema::dropIfExists('signature_requests');

        Schema::dropIfExists('submission_throttles');

        Schema::dropIfExists('external_forms');

        Schema::dropIfExists('access_tokens');

        Schema::dropIfExists('external_users');
    }
};
