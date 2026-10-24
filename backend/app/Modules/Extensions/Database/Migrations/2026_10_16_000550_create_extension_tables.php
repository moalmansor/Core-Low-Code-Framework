<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Developer extensions and their versions (architecture §10.22,
 * specification §4.19). extensions and extension_versions point at each
 * other: the version columns are added once both tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::code($t, 'key', 64);
            Columns::enum($t, 'type', ['server_hook', 'api_endpoint', 'client_validator', 'client_field', 'form_script']);
            Columns::enum($t, 'hook_point', ['onLoad', 'beforeValidate', 'afterValidate', 'beforeSave', 'afterSave', 'beforeDelete', 'afterDelete', 'onStatusChange', 'onAction'], nullable: true);
            Columns::fk($t, 'form_id', 'forms');
            Columns::enum($t, 'status', ['draft', 'testing', 'pending_approval', 'approved', 'deployed', 'disabled', 'rejected']);
            $t->integer('timeout_ms');
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('extension_versions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'extension_id', 'extensions', onDelete: 'cascade', nullable: false, index: false);
            $t->integer('version');
            $t->string('source_path', 1024);
            Columns::hash($t, 'checksum');
            Columns::json($t, 'manifest');
            Columns::enum($t, 'test_status', ['not_run', 'passed', 'failed']);
            Columns::json($t, 'test_report', nullable: true);
            Columns::fk($t, 'submitted_by', 'users', nullable: false);
            Columns::fk($t, 'approved_by', 'users');
            Columns::dt($t, 'approved_at', nullable: true);
            Columns::dt($t, 'deployed_at', nullable: true);
            $t->text('rejected_reason')->nullable();
            Columns::unique($t, ['extension_id', 'version']);
        });

        Schema::table('extensions', function (Blueprint $t): void {
            Columns::fk($t, 'current_version_id', 'extension_versions');
            Columns::fk($t, 'deployed_version_id', 'extension_versions');
        });
    }

    public function down(): void
    {
        Schema::table('extensions', function (Blueprint $t): void {
            $t->dropForeign('fk_extensions_deployed_version_id');
            $t->dropForeign('fk_extensions_current_version_id');
            $t->dropIndex('ix_extensions_current_version_id');
            $t->dropColumn('current_version_id');
            $t->dropIndex('ix_extensions_deployed_version_id');
            $t->dropColumn('deployed_version_id');
        });

        Schema::dropIfExists('extension_versions');

        Schema::dropIfExists('extensions');
    }
};
