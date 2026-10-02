<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Error monitoring (architecture §10.21, §19.15). `error_logs` is keyed by
 * (id, occurred_at) for Phase 6 partitioning and carries logical references only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $severities = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        Schema::create('error_groups', function (Blueprint $t) use ($severities): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::hash($t, 'fingerprint');
            $t->string('exception_class', 255);
            $t->text('message_sample');
            Columns::code($t, 'module', 48, nullable: true);
            Columns::enum($t, 'severity', $severities);
            Columns::dt($t, 'first_seen_at');
            Columns::dt($t, 'last_seen_at');
            $t->bigInteger('occurrences')->default(0);
            Columns::enum($t, 'status', ['new', 'in_progress', 'resolved', 'ignored']);
            Columns::fk($t, 'assignee_user_id', 'users');
            $t->text('notes')->nullable();
            Columns::dt($t, 'resolved_at', nullable: true);
            Columns::fk($t, 'resolved_by', 'users');
            Columns::dt($t, 'last_alerted_at', nullable: true);
            Columns::unique($t, ['organization_id', 'fingerprint']);
            Columns::index($t, ['status', 'last_seen_at']);
        });

        Schema::create('error_logs', function (Blueprint $t) use ($severities): void {
            $t->id();
            Columns::dt($t, 'occurred_at');
            $t->primary(['id', 'occurred_at'], 'pk_error_logs');
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->unsignedBigInteger('error_group_id');
            Columns::code($t, 'reference_code', 16);
            Columns::enum($t, 'severity', $severities);
            Columns::code($t, 'module', 48, nullable: true);
            $t->string('exception_class', 255);
            $t->text('message');
            $t->string('file', 1024)->nullable();
            $t->integer('line')->nullable();
            $t->longText('trace')->nullable();
            Columns::json($t, 'request', nullable: true);
            $t->unsignedBigInteger('user_id')->nullable();
            Columns::json($t, 'role_keys', nullable: true);
            $t->unsignedBigInteger('form_id')->nullable();
            $t->unsignedBigInteger('record_id')->nullable();
            $t->string('hook', 255)->nullable();
            $t->string('job', 255)->nullable();
            Columns::code($t, 'environment', 32);
            Columns::code($t, 'release', 64, nullable: true);
            Columns::code($t, 'correlation_id', 36, nullable: true);
            Columns::index($t, ['error_group_id', 'occurred_at']);
            Columns::index($t, ['organization_id', 'occurred_at']);
            Columns::index($t, ['reference_code']);
            Columns::index($t, ['correlation_id']);
            Columns::index($t, ['form_id', 'occurred_at']);
            Columns::index($t, ['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('error_groups');
    }
};
