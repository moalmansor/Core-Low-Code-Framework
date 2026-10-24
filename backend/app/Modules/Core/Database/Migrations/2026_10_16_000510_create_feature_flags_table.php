<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Feature flags (architecture §10.24, specification §4.36).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::code($t, 'key', 64);
            Columns::fk($t, 'application_id', 'applications');
            Columns::enum($t, 'target_type', ['form', 'action', 'page', 'capability']);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->boolean('is_enabled')->default(true);
            Columns::json($t, 'audience');
            Columns::unique($t, ['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
