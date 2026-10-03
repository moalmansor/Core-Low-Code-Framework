<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Uploaded and generated files (architecture §10.6). Phase 1 uses it for
 * branding assets (logo, favicon). Deferred: form_id, record_id, field_id
 * (Phase 2) and external_user_id (Phase 5), added with their target tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::timestamps($t);
            Columns::code($t, 'disk', 32);
            $t->string('path', 1024);
            $t->string('original_name', 255);
            $t->string('mime_type', 127);
            Columns::code($t, 'extension', 16);
            $t->bigInteger('size_bytes');
            Columns::hash($t, 'sha256');
            Columns::enum($t, 'scan_status', ['pending', 'clean', 'infected', 'skipped', 'error']);
            Columns::dt($t, 'scanned_at', nullable: true);
            $t->integer('width')->nullable();
            $t->integer('height')->nullable();
            $t->boolean('is_encrypted')->default(false);
            $t->boolean('is_temporary')->default(false);
            Columns::code($t, 'owner_type', 32, nullable: true);
            $t->unsignedBigInteger('owner_id')->nullable();
            Columns::fk($t, 'uploaded_by', 'users');
            Columns::dt($t, 'deleted_at', nullable: true);
            Columns::index($t, ['owner_type', 'owner_id']);
            Columns::index($t, ['organization_id', 'is_temporary', 'created_at']);
            Columns::index($t, ['sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
