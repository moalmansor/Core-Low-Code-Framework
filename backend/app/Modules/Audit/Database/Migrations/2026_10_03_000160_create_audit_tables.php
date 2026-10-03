<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Append-only, hash-chained audit log (architecture §10.21, ADR-0011). The
 * primary key includes occurred_at so that monthly partitioning (Phase 6) can be
 * applied without rebuilding keys. No FKs: partitioned tables cannot carry them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t): void {
            $t->id();
            Columns::dt($t, 'occurred_at');
            $t->primary(['id', 'occurred_at'], 'pk_audit_logs');
            $t->unsignedBigInteger('organization_id');
            $t->smallInteger('chain_id');
            $t->bigInteger('chain_seq');
            Columns::code($t, 'event', 48);
            Columns::enum($t, 'category', ['data', 'workflow', 'auth', 'access', 'config', 'operations', 'export', 'schema', 'security']);
            Columns::code($t, 'object_type', 48, nullable: true);
            $t->unsignedBigInteger('object_id')->nullable();
            $t->unsignedBigInteger('form_id')->nullable();
            $t->unsignedBigInteger('record_id')->nullable();
            Columns::json($t, 'changes', nullable: true);
            $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->unsignedBigInteger('subject_user_id')->nullable();
            $t->unsignedBigInteger('on_behalf_of_user_id')->nullable();
            $t->unsignedBigInteger('external_user_id')->nullable();
            $t->unsignedBigInteger('impersonation_session_id')->nullable();
            $t->unsignedBigInteger('justification_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent', 512)->nullable();
            Columns::code($t, 'correlation_id', 36, nullable: true);
            Columns::json($t, 'meta', nullable: true);
            Columns::hash($t, 'prev_hash');
            Columns::hash($t, 'hash');
            Columns::unique($t, ['chain_id', 'chain_seq', 'occurred_at']);
            Columns::index($t, ['form_id', 'record_id', 'occurred_at']);
            Columns::index($t, ['actor_user_id', 'occurred_at']);
            Columns::index($t, ['object_type', 'object_id']);
            Columns::index($t, ['organization_id', 'event', 'occurred_at']);
            Columns::index($t, ['correlation_id']);
        });

        Schema::create('audit_chain_heads', function (Blueprint $t): void {
            $t->smallInteger('chain_id');
            $t->primary(['chain_id'], 'pk_audit_chain_heads');
            $t->bigInteger('last_seq')->default(0);
            Columns::hash($t, 'last_hash');
            $t->bigInteger('last_verified_seq')->default(0);
            Columns::dt($t, 'last_verified_at', nullable: true);
            Columns::dt($t, 'updated_at');
        });

        // Sixteen chains, each starting from the all-zero genesis hash.
        $now = now()->format('Y-m-d H:i:s.u');
        $genesis = str_repeat('0', 64);
        DB::table('audit_chain_heads')->insert(array_map(
            static fn (int $chain): array => ['chain_id' => $chain, 'last_seq' => 0, 'last_hash' => $genesis, 'last_verified_seq' => 0, 'updated_at' => $now],
            range(0, 15),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain_heads');
        Schema::dropIfExists('audit_logs');
    }
};
