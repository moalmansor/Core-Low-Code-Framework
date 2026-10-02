<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tenancy root (architecture §9.5, §10.2). The FKs from created_by/updated_by to
 * users are added by the identity migration once `users` exists (circular
 * dependency). `theme_id` is added in Phase 5 together with `themes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::timestamps($t);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            Columns::index($t, ['created_by']);
            Columns::index($t, ['updated_by']);
            Columns::fk($t, 'parent_id', 'organizations');
            Columns::code($t, 'key', 64);
            $t->boolean('is_platform')->default(false);
            Columns::enum($t, 'status', ['active', 'suspended', 'archived']);
            Columns::code($t, 'default_locale', 10);
            $t->string('timezone', 64);
            Columns::json($t, 'settings_overrides', nullable: true);
            Columns::unique($t, ['key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
