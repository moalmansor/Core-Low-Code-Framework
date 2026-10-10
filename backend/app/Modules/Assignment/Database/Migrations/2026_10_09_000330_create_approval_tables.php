<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Multi-party approvals (architecture §10.12, §19.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', nullable: false, index: false);
            $t->unsignedBigInteger('record_id');
            Columns::fk($t, 'transition_id', 'transitions', nullable: false);
            Columns::enum($t, 'mode', ['all', 'any_n', 'quorum']);
            $t->smallInteger('required_count')->nullable();
            $t->decimal('quorum_weight', 9, 2)->nullable();
            Columns::enum($t, 'rejection_behavior', ['immediate', 'wait_all']);
            Columns::fk($t, 'rejection_status_id', 'statuses');
            Columns::enum($t, 'status', ['pending', 'approved', 'rejected', 'cancelled', 'expired']);
            Columns::fk($t, 'requested_by', 'users', nullable: false);
            $t->bigInteger('record_row_version');
            Columns::dt($t, 'due_at', nullable: true);
            Columns::dt($t, 'completed_at', nullable: true);
            Columns::index($t, ['form_id', 'record_id', 'status']);
        });

        Schema::create('approval_decisions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'approval_request_id', 'approval_requests', 'cascade', nullable: false, index: false);
            Columns::enum($t, 'approver_type', ['user', 'role', 'department']);
            $t->unsignedBigInteger('approver_id');
            $t->decimal('weight', 9, 2);
            Columns::enum($t, 'decision', ['pending', 'approved', 'rejected']);
            Columns::fk($t, 'decided_by_user_id', 'users');
            Columns::fk($t, 'on_behalf_of_user_id', 'users');
            $t->text('comment')->nullable();
            Columns::dt($t, 'decided_at', nullable: true);
            Columns::dt($t, 'reminded_at', nullable: true);
            Columns::unique($t, ['approval_request_id', 'approver_type', 'approver_id']);
            Columns::index($t, ['approver_type', 'approver_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
        Schema::dropIfExists('approval_requests');
    }
};
