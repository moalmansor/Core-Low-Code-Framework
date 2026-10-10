<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Document templates (architecture §10.14, §19.14).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms');
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'type', ['docx', 'html']);
            Columns::fk($t, 'file_id', 'files');
            Columns::json($t, 'locale_files', nullable: true);
            Columns::json($t, 'output_formats');
            Columns::json($t, 'paper');
            Columns::json($t, 'placeholders_detected');
            $t->boolean('is_active')->default(true);
            Columns::unique($t, ['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
