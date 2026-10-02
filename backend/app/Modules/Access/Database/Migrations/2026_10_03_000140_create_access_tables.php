<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Roles, role membership, the permission catalog, and permission grants
 * (architecture §10.4, §16). Deferred to later phases: roles.application_id
 * (Phase 2), roles.access_policy_id (Phase 5), permission_assignments.condition_id
 * (Phase 2) — each added with its target table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::code($t, 'key', 64);
            $t->boolean('is_system')->default(false);
            Columns::enum($t, 'audience', ['internal', 'external']);
            $t->boolean('requires_2fa')->default(false);
            $t->boolean('is_admin_role')->default(false);
            $t->integer('sort_order')->default(0);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('user_roles', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'user_id', 'users', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'role_id', 'roles', nullable: false);
            Columns::dt($t, 'valid_from', nullable: true);
            Columns::dt($t, 'valid_until', nullable: true);
            Columns::fk($t, 'assigned_by', 'users');
            Columns::dt($t, 'created_at');
            Columns::unique($t, ['user_id', 'role_id']);
        });

        Schema::create('permissions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::org($t);
            Columns::timestamps($t);
            Columns::code($t, 'key', 191);
            Columns::enum($t, 'scope_type', ['system', 'application', 'form', 'action', 'download_profile', 'menu_item', 'transition', 'page', 'report', 'dashboard', 'view', 'field']);
            $t->unsignedBigInteger('scope_id')->nullable();
            Columns::code($t, 'ability', 64);
            Columns::code($t, 'category', 64);
            $t->boolean('is_system')->default(false);
            $t->boolean('is_dangerous')->default(false);
            Columns::unique($t, ['key']);
            Columns::index($t, ['scope_type', 'scope_id']);
        });

        Schema::create('permission_assignments', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::org($t);
            Columns::timestamps($t);
            Columns::fk($t, 'permission_id', 'permissions', 'cascade', nullable: false, index: false);
            Columns::enum($t, 'subject_type', ['role', 'user', 'department']);
            $t->unsignedBigInteger('subject_id');
            Columns::enum($t, 'effect', ['allow', 'deny', 'hard_deny']);
            $t->boolean('include_descendants')->default(false);
            Columns::dt($t, 'valid_until', nullable: true);
            Columns::fk($t, 'granted_by', 'users');
            Columns::unique($t, ['permission_id', 'subject_type', 'subject_id']);
            Columns::index($t, ['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_assignments');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
