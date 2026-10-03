<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Submission journal and record comments (architecture §10.6), and the
 * deferred files.form_id / record_id / field_id (ADR-0021). Deferred to later
 * phases: submission_journal.external_user_id (Phase 5) and
 * submission_journal.import_job_id (Phase 4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_journal', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::org($t, index: false);
            Columns::timestamps($t);
            Columns::code($t, 'idempotency_key', 64);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            Columns::fk($t, 'form_version_id', 'form_versions', nullable: false);
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::enum($t, 'operation', ['create', 'update', 'delete', 'restore', 'transition', 'action']);
            Columns::enum($t, 'source', ['ui', 'api', 'import', 'automation', 'action', 'external', 'inbound_webhook', 'sync']);
            Columns::fk($t, 'user_id', 'users');
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            $t->longText('payload');
            $t->bigInteger('expected_row_version')->nullable();
            Columns::enum($t, 'status', ['received', 'processing', 'processed', 'failed', 'retrying', 'discarded']);
            $t->smallInteger('attempts')->default(0);
            $t->text('error_message')->nullable();
            $t->longText('error_trace')->nullable();
            $t->unsignedBigInteger('error_log_id')->nullable();
            Columns::code($t, 'correlation_id', 36);
            $t->longText('edited_payload')->nullable();
            Columns::fk($t, 'edited_by', 'users');
            $t->text('discard_reason')->nullable();
            Columns::fk($t, 'discarded_by', 'users');
            Columns::dt($t, 'processed_at', nullable: true);
            Columns::unique($t, ['idempotency_key']);
            Columns::index($t, ['organization_id', 'status', 'created_at']);
            Columns::index($t, ['form_id', 'record_id']);
            Columns::index($t, ['correlation_id']);
        });

        Schema::create('record_comments', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::org($t, index: false);
            Columns::timestamps($t);
            Columns::soft($t);
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::fk($t, 'parent_id', 'record_comments');
            $t->text('body');
            Columns::fk($t, 'author_user_id', 'users', nullable: false);
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            Columns::index($t, ['form_id', 'record_id', 'created_at']);
        });

        Schema::table('files', function (Blueprint $t): void {
            Columns::fk($t, 'form_id', 'forms', index: false);
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::fk($t, 'field_id', 'fields');
            Columns::index($t, ['form_id', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $t): void {
            $t->dropForeign('fk_files_form_id');
            $t->dropForeign('fk_files_field_id');
            $t->dropIndex('ix_files_form_id_record_id');
            $t->dropIndex('ix_files_field_id');
            $t->dropColumn(['form_id', 'record_id', 'field_id']);
        });
        Schema::dropIfExists('record_comments');
        Schema::dropIfExists('submission_journal');
    }
};
