<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sparse form/group/field access overrides (architecture §10.4, §16).
 * Deferred (ADR-0021): status_id (Phase 3, with statuses). Until then every
 * rule applies to any status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_access_rules', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::enum($t, 'target_type', ['form', 'group', 'field']);
            Columns::fk($t, 'group_id', 'field_groups');
            Columns::fk($t, 'field_id', 'fields');
            Columns::enum($t, 'subject_type', ['everyone', 'role', 'department', 'user']);
            $t->unsignedBigInteger('subject_id')->nullable();
            Columns::enum($t, 'mode', ['create', 'edit', 'view', 'print'], nullable: true);
            Columns::enum($t, 'access', ['hidden', 'read_only', 'editable', 'required']);
            Columns::enum($t, 'effect', ['allow', 'deny', 'hard_deny']);
            Columns::hash($t, 'rule_hash');
            Columns::unique($t, ['rule_hash']);
            Columns::index($t, ['form_id', 'target_type', 'group_id', 'field_id']);
            Columns::index($t, ['form_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_access_rules');
    }
};
