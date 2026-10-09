<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Blueprints, their versions and instances (architecture §10.17,
 * specification §4.33). Closes forms.blueprint_instance_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::soft($t);
            Columns::enum($t, 'kind', ['form', 'collection', 'workflow', 'view', 'action', 'notification', 'dashboard', 'application']);
            Columns::code($t, 'category', 64, nullable: true);
            Columns::json($t, 'tags', nullable: true);
            $t->boolean('is_library')->default(false);
            Columns::fk($t, 'preview_file_id', 'files');
            $t->unsignedBigInteger('current_version_id')->nullable();
            Columns::index($t, ['current_version_id']);
            Columns::code($t, 'source_type', 32, nullable: true);
            $t->unsignedBigInteger('source_id')->nullable();
            Columns::index($t, ['organization_id', 'kind', 'category']);
        });

        Schema::create('blueprint_versions', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::timestamps($t);
            Columns::by($t);
            Columns::fk($t, 'blueprint_id', 'blueprints', 'cascade', nullable: false, index: false);
            $t->integer('version');
            Columns::json($t, 'content');
            Columns::hash($t, 'content_hash');
            Columns::enum($t, 'include_mode', ['structure', 'structure_permissions', 'everything']);
            $t->text('changelog')->nullable();
            Columns::unique($t, ['blueprint_id', 'version']);
        });

        Schema::table('blueprints', function (Blueprint $t): void {
            Columns::foreign($t, 'current_version_id', 'blueprint_versions');
        });

        Schema::create('blueprint_instances', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'blueprint_id', 'blueprints', nullable: false);
            Columns::fk($t, 'blueprint_version_id', 'blueprint_versions', nullable: false);
            Columns::code($t, 'object_type', 32);
            $t->unsignedBigInteger('object_id');
            Columns::enum($t, 'include_mode', ['structure', 'structure_permissions', 'everything']);
            Columns::fk($t, 'last_propagated_version_id', 'blueprint_versions');
            $t->boolean('is_detached')->default(false);
            Columns::unique($t, ['object_type', 'object_id']);
        });

        Schema::table('forms', function (Blueprint $t): void {
            Columns::foreign($t, 'blueprint_instance_id', 'blueprint_instances');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $t): void {
            $t->dropForeign('fk_forms_blueprint_instance_id');
        });
        Schema::table('blueprints', function (Blueprint $t): void {
            $t->dropForeign('fk_blueprints_current_version_id');
        });
        Schema::dropIfExists('blueprint_instances');
        Schema::dropIfExists('blueprint_versions');
        Schema::dropIfExists('blueprints');
    }
};
