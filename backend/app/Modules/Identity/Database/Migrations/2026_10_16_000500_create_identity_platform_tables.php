<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Access policies, impersonation and device trust (architecture §10.4, §19.13,
 * specification §4.36). Adds the columns deferred to Phase 5 (ADR-0021):
 * roles.access_policy_id, sessions.trusted_device_id,
 * sessions.impersonation_session_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_policies', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::code($t, 'key', 48);
            Columns::json($t, 'ip_allowlist', nullable: true);
            Columns::json($t, 'time_windows', nullable: true);
            $t->smallInteger('max_concurrent_sessions')->nullable();
            $t->integer('session_idle_minutes');
            $t->integer('session_absolute_minutes');
            $t->boolean('require_trusted_device')->default(false);
            $t->boolean('require_2fa')->default(false);
            $t->boolean('is_admin_policy')->default(false);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('impersonation_sessions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'impersonator_user_id', 'users', nullable: false, index: false);
            Columns::fk($t, 'impersonated_user_id', 'users', nullable: false);
            $t->text('reason');
            Columns::dt($t, 'started_at');
            Columns::dt($t, 'expires_at');
            Columns::dt($t, 'ended_at', nullable: true);
            Columns::enum($t, 'end_reason', ['manual', 'expired', 'logout', 'revoked'], nullable: true);
            $t->string('ip_address', 45);
            $t->text('user_agent')->nullable();
            Columns::index($t, ['impersonator_user_id', 'started_at']);
        });

        Schema::create('trusted_devices', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'user_id', 'users', onDelete: 'cascade', nullable: false, index: false);
            Columns::hash($t, 'device_hash');
            $t->string('label', 255)->nullable();
            Columns::dt($t, 'last_used_at', nullable: true);
            Columns::dt($t, 'expires_at');
            Columns::dt($t, 'revoked_at', nullable: true);
            Columns::unique($t, ['user_id', 'device_hash']);
        });

        Schema::table('roles', function (Blueprint $t): void {
            Columns::fk($t, 'access_policy_id', 'access_policies');
        });

        Schema::table('sessions', function (Blueprint $t): void {
            Columns::fk($t, 'trusted_device_id', 'trusted_devices');
            Columns::fk($t, 'impersonation_session_id', 'impersonation_sessions');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $t): void {
            $t->dropForeign('fk_sessions_impersonation_session_id');
            $t->dropForeign('fk_sessions_trusted_device_id');
            $t->dropIndex('ix_sessions_trusted_device_id');
            $t->dropColumn('trusted_device_id');
            $t->dropIndex('ix_sessions_impersonation_session_id');
            $t->dropColumn('impersonation_session_id');
        });

        Schema::table('roles', function (Blueprint $t): void {
            $t->dropForeign('fk_roles_access_policy_id');
            $t->dropIndex('ix_roles_access_policy_id');
            $t->dropColumn('access_policy_id');
        });

        Schema::dropIfExists('trusted_devices');

        Schema::dropIfExists('impersonation_sessions');

        Schema::dropIfExists('access_policies');
    }
};
