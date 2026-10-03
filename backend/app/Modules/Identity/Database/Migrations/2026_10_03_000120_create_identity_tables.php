<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Users, sessions, password history, and login attempts (architecture §10.4).
 * Also adds the deferred user FKs of `organizations` and `departments`.
 * Deferred to later phases (their targets do not exist yet):
 * sessions.external_user_id, sessions.trusted_device_id,
 * sessions.impersonation_session_id (Phase 5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::soft($t);
            $t->string('name', 255);
            $t->string('email', 255);
            $t->string('username', 128)->nullable();
            $t->string('password', 255)->nullable();
            Columns::dt($t, 'email_verified_at', nullable: true);
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            Columns::dt($t, 'two_factor_confirmed_at', nullable: true);
            Columns::fk($t, 'department_id', 'departments');
            Columns::fk($t, 'manager_id', 'users');
            $t->string('job_title', 255)->nullable();
            $t->string('phone', 32)->nullable();
            Columns::enum($t, 'status', ['pending', 'active', 'suspended', 'disabled']);
            Columns::enum($t, 'auth_source', ['local', 'ldap', 'oidc']);
            $t->string('external_subject', 255)->nullable();
            Columns::json($t, 'attributes', nullable: true);
            Columns::dt($t, 'password_changed_at', nullable: true);
            Columns::dt($t, 'last_login_at', nullable: true);
            $t->string('last_login_ip', 45)->nullable();
            $t->smallInteger('failed_login_count')->default(0);
            Columns::dt($t, 'locked_until', nullable: true);
            $t->string('remember_token', 100)->nullable();
            Columns::dt($t, 'anonymized_at', nullable: true);
            Columns::unique($t, ['email']);
            Columns::unique($t, ['username'], ['username']);
            Columns::unique($t, ['auth_source', 'external_subject'], ['external_subject']);
            Columns::index($t, ['organization_id', 'department_id']);
        });

        Schema::table('organizations', function (Blueprint $t): void {
            Columns::foreign($t, 'created_by', 'users');
            Columns::foreign($t, 'updated_by', 'users');
        });

        Schema::table('departments', function (Blueprint $t): void {
            Columns::foreign($t, 'created_by', 'users');
            Columns::foreign($t, 'updated_by', 'users');
            Columns::foreign($t, 'deleted_by', 'users');
            Columns::foreign($t, 'manager_user_id', 'users');
        });

        Schema::create('sessions', function (Blueprint $t): void {
            Columns::code($t, 'id', 128);
            $t->primary(['id'], 'pk_sessions');
            Columns::fk($t, 'user_id', 'users', 'cascade');
            Columns::enum($t, 'guard', ['web', 'external']);
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity');
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'absolute_expires_at');
            Columns::dt($t, 'two_factor_passed_at', nullable: true);
            Columns::index($t, ['last_activity']);
        });

        Schema::create('password_histories', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'user_id', 'users', 'cascade', nullable: false, index: false);
            $t->string('password_hash', 255);
            Columns::dt($t, 'created_at');
            Columns::index($t, ['user_id', 'created_at']);
        });

        Schema::create('login_attempts', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::hash($t, 'identifier_hash');
            Columns::fk($t, 'user_id', 'users');
            Columns::enum($t, 'guard', ['web', 'external', 'api']);
            $t->string('ip_address', 45);
            $t->text('user_agent')->nullable();
            $t->boolean('successful')->default(false);
            Columns::code($t, 'failure_reason', 32, nullable: true);
            Columns::dt($t, 'attempted_at');
            Columns::index($t, ['identifier_hash', 'attempted_at']);
            Columns::index($t, ['ip_address', 'attempted_at']);
            Columns::index($t, ['organization_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('sessions');
        Schema::table('departments', function (Blueprint $t): void {
            foreach (['created_by', 'updated_by', 'deleted_by', 'manager_user_id'] as $column) {
                $t->dropForeign('fk_departments_'.$column);
            }
        });
        Schema::table('organizations', function (Blueprint $t): void {
            $t->dropForeign('fk_organizations_created_by');
            $t->dropForeign('fk_organizations_updated_by');
        });
        Schema::dropIfExists('users');
    }
};
