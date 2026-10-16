<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Reports and dashboards (architecture §10.14, specification §4.18).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::fk($t, 'base_form_id', 'forms', nullable: false);
            Columns::code($t, 'key', 48);
            Columns::json($t, 'definition');
            Columns::enum($t, 'visualization', ['table', 'pivot', 'bar', 'line', 'pie', 'area', 'kpi']);
            $t->boolean('is_personal')->default(false);
            Columns::fk($t, 'owner_user_id', 'users');
            $t->integer('cache_ttl_seconds')->nullable();
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('dashboards', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::code($t, 'key', 48);
            Columns::json($t, 'layout');
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('dashboard_widgets', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'dashboard_id', 'dashboards', onDelete: 'cascade', nullable: false, index: false);
            Columns::code($t, 'type', 48);
            Columns::fk($t, 'report_id', 'reports');
            Columns::json($t, 'config');
            Columns::json($t, 'layout');
            Columns::fk($t, 'visibility_condition_id', 'conditions');
            $t->integer('sort_order')->default(0);
            Columns::index($t, ['dashboard_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_widgets');

        Schema::dropIfExists('dashboards');

        Schema::dropIfExists('reports');
    }
};
