<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Edit justification (architecture §10.11, §19.3).
 * Deferred (ADR-0021): justification_rules.action_id (Phase 4, actions);
 * justifications.bulk_operation_id and import_job_id (Phase 4) and
 * external_user_id (Phase 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justification_reason_codes', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'set_key', 48);
            Columns::code($t, 'code', 48);
            $t->boolean('requires_note')->default(false);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'set_key', 'code']);
        });

        Schema::create('justification_rules', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::enum($t, 'scope', ['form', 'group', 'field', 'status', 'action', 'transition', 'delete', 'restore', 'import', 'bulk', 'reassign', 'merge']);
            Columns::fk($t, 'group_id', 'field_groups');
            Columns::fk($t, 'field_id', 'fields');
            Columns::fk($t, 'status_id', 'statuses');
            Columns::fk($t, 'transition_id', 'transitions');
            Columns::enum($t, 'subject_type', ['everyone', 'role', 'department', 'user']);
            $t->unsignedBigInteger('subject_id')->nullable();
            Columns::enum($t, 'level', ['not_required', 'optional', 'mandatory']);
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::enum($t, 'level_when_condition', ['not_required', 'optional', 'mandatory'], nullable: true);
            $t->smallInteger('min_length')->nullable();
            $t->smallInteger('max_length')->nullable();
            Columns::enum($t, 'reason_code_mode', ['none', 'optional', 'required']);
            Columns::enum($t, 'reason_code_source', ['codes', 'collection']);
            Columns::code($t, 'reason_code_set', 48, nullable: true);
            Columns::fk($t, 'reason_code_collection_id', 'forms');
            Columns::enum($t, 'attachments_mode', ['none', 'optional', 'required']);
            $t->smallInteger('max_attachments')->nullable();
            Columns::json($t, 'attachment_rules', nullable: true);
            $t->boolean('show_change_summary')->default(false);
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['form_id', 'scope']);
            Columns::index($t, ['form_id', 'field_id']);
            Columns::index($t, ['form_id', 'transition_id']);
        });

        Schema::create('justifications', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::fk($t, 'form_id', 'forms', index: false);
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::enum($t, 'context', ['edit', 'delete', 'restore', 'transition', 'action', 'bulk', 'import', 'reassign', 'merge', 'personal_data', 'manual_sequence_adjust']);
            Columns::json($t, 'rule_ids');
            $t->text('reason_text')->nullable();
            Columns::fk($t, 'reason_code_id', 'justification_reason_codes');
            $t->unsignedBigInteger('reason_code_record_id')->nullable();
            $t->string('reason_code_label_snapshot', 255)->nullable();
            $t->text('note')->nullable();
            Columns::json($t, 'changed_fields');
            $t->integer('affected_count')->default(0);
            Columns::fk($t, 'user_id', 'users', index: false);
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            Columns::code($t, 'locale', 10);
            Columns::hash($t, 'content_hash');
            Columns::dt($t, 'created_at');
            Columns::index($t, ['form_id', 'record_id', 'created_at']);
            Columns::index($t, ['user_id', 'created_at']);
        });

        Schema::create('justification_attachments', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'justification_id', 'justifications', nullable: false, index: false);
            Columns::fk($t, 'file_id', 'files', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::unique($t, ['justification_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justification_attachments');
        Schema::dropIfExists('justifications');
        Schema::dropIfExists('justification_rules');
        Schema::dropIfExists('justification_reason_codes');
    }
};
