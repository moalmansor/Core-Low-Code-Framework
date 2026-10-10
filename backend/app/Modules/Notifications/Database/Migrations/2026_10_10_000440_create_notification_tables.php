<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Email and in-app notifications (architecture §10.13, §19.14).
 * notification_deliveries (the non-email channel log) arrives with
 * notification channels in Phase 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::fk($t, 'form_id', 'forms');
            Columns::code($t, 'key', 48);
            Columns::json($t, 'design');
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('notification_rules', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', onDelete: 'cascade', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'trigger', ['record_created', 'record_updated', 'status_changed', 'field_changed', 'condition_met', 'scheduled_reminder', 'sla_warning', 'sla_escalation', 'approval_requested', 'assigned']);
            Columns::fk($t, 'from_status_id', 'statuses');
            Columns::fk($t, 'to_status_id', 'statuses');
            Columns::fk($t, 'field_id', 'fields');
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::json($t, 'schedule', nullable: true);
            Columns::fk($t, 'email_template_id', 'email_templates');
            Columns::json($t, 'channels');
            Columns::json($t, 'recipients');
            Columns::json($t, 'attachments', nullable: true);
            $t->integer('delay_minutes')->nullable();
            $t->boolean('respect_user_preferences')->default(true);
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['form_id', 'trigger', 'is_active']);
        });

        Schema::create('email_queue', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'notification_rule_id', 'notification_rules');
            Columns::fk($t, 'email_template_id', 'email_templates');
            Columns::fk($t, 'form_id', 'forms', index: false);
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::enum($t, 'source', ['rule', 'action', 'automation', 'system', 'test', 'download', 'alert']);
            Columns::code($t, 'locale', 10);
            Columns::json($t, 'to');
            Columns::json($t, 'cc', nullable: true);
            Columns::json($t, 'bcc', nullable: true);
            $t->string('subject', 998);
            $t->longText('body_html');
            Columns::json($t, 'attachment_file_ids', nullable: true);
            Columns::enum($t, 'status', ['pending', 'sending', 'sent', 'failed', 'cancelled']);
            $t->smallInteger('attempts')->default(0);
            $t->smallInteger('max_attempts');
            Columns::dt($t, 'next_attempt_at', nullable: true);
            $t->text('last_error')->nullable();
            $t->string('message_id', 255)->nullable();
            Columns::dt($t, 'sent_at', nullable: true);
            Columns::fk($t, 'cancelled_by', 'users');
            Columns::fk($t, 'resent_from_id', 'email_queue');
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            Columns::code($t, 'correlation_id', 36);
            Columns::index($t, ['organization_id', 'status', 'created_at']);
            Columns::index($t, ['form_id', 'record_id']);
            Columns::index($t, ['status', 'next_attempt_at']);
        });

        Schema::create('in_app_notifications', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::fk($t, 'user_id', 'users', onDelete: 'cascade', nullable: false, index: false);
            Columns::code($t, 'type', 48);
            $t->string('title', 255);
            $t->text('body')->nullable();
            $t->string('link', 2048)->nullable();
            Columns::json($t, 'data', nullable: true);
            Columns::fk($t, 'notification_rule_id', 'notification_rules');
            Columns::fk($t, 'form_id', 'forms');
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            Columns::dt($t, 'read_at', nullable: true);
            Columns::dt($t, 'created_at');
            Columns::index($t, ['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');

        Schema::dropIfExists('email_queue');

        Schema::dropIfExists('notification_rules');

        Schema::dropIfExists('email_templates');
    }
};
