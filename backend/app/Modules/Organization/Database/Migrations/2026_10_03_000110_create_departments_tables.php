<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Departments and their closure table (architecture §10.4). FKs to `users`
 * (created_by, updated_by, deleted_by, manager_user_id) are added by the identity
 * migration; `business_calendar_id` is added in Phase 2 with `business_calendars`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::org($t, index: false);
            Columns::timestamps($t);
            foreach (['created_by', 'updated_by'] as $column) {
                $t->unsignedBigInteger($column)->nullable();
                Columns::index($t, [$column]);
            }
            Columns::dt($t, 'deleted_at', nullable: true);
            $t->unsignedBigInteger('deleted_by')->nullable();
            Columns::index($t, ['deleted_by']);
            Columns::index($t, ['organization_id', 'deleted_at']);
            Columns::fk($t, 'parent_id', 'departments');
            Columns::code($t, 'code', 64);
            $t->unsignedBigInteger('manager_user_id')->nullable();
            Columns::index($t, ['manager_user_id']);
            $t->smallInteger('depth')->default(0);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'code']);
        });

        Schema::create('department_closure', function (Blueprint $t): void {
            $t->unsignedBigInteger('ancestor_id');
            $t->unsignedBigInteger('descendant_id');
            $t->smallInteger('depth')->default(0);
            $t->primary(['ancestor_id', 'descendant_id'], 'pk_department_closure');
            Columns::index($t, ['descendant_id', 'depth']);
            Columns::foreign($t, 'ancestor_id', 'departments', 'cascade');
            Columns::foreign($t, 'descendant_id', 'departments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_closure');
        Schema::dropIfExists('departments');
    }
};
