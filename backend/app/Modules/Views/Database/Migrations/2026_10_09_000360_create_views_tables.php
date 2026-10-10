<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Views, saved views, View Mode panels, reference previews and print layouts
 * (architecture §10.9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('views', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            $t->boolean('is_default')->default(false);
            $t->integer('priority')->default(0);
            $t->smallInteger('page_size');
            Columns::json($t, 'default_sort');
            $t->boolean('show_totals')->default(false);
            $t->boolean('allow_column_chooser')->default(true);
            $t->boolean('allow_global_search')->default(true);
            Columns::json($t, 'row_options');
            $t->boolean('include_in_queues')->default(false);
            Columns::unique($t, ['form_id', 'key']);
        });

        Schema::create('view_columns', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'view_id', 'views', 'cascade', nullable: false, index: false);
            Columns::json($t, 'path');
            Columns::hash($t, 'path_hash');
            $t->integer('sort_order')->default(0);
            $t->smallInteger('width')->nullable();
            Columns::enum($t, 'pinned', ['none', 'start', 'end']);
            $t->boolean('is_visible')->default(true);
            $t->boolean('is_sortable')->default(true);
            Columns::json($t, 'format', nullable: true);
            Columns::enum($t, 'aggregate', ['none', 'count', 'sum', 'avg', 'min', 'max']);
            Columns::unique($t, ['view_id', 'path_hash']);
            Columns::index($t, ['view_id', 'sort_order']);
        });

        Schema::create('filters', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'view_id', 'views', 'cascade', nullable: false, index: false);
            Columns::json($t, 'path');
            Columns::hash($t, 'path_hash');
            Columns::code($t, 'filter_type', 32);
            Columns::json($t, 'operators');
            $t->boolean('is_quick')->default(false);
            Columns::json($t, 'default_value', nullable: true);
            $t->integer('sort_order')->default(0);
            Columns::unique($t, ['view_id', 'path_hash']);
        });

        Schema::create('saved_views', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'view_id', 'views', nullable: false);
            Columns::fk($t, 'owner_user_id', 'users', nullable: false, index: false);
            $t->string('name', 255);
            Columns::json($t, 'state');
            $t->boolean('is_shared')->default(false);
            $t->boolean('is_default')->default(false);
            Columns::index($t, ['owner_user_id', 'form_id']);
            Columns::index($t, ['form_id', 'is_shared']);
        });

        Schema::create('saved_view_shares', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'saved_view_id', 'saved_views', 'cascade', nullable: false);
            Columns::enum($t, 'subject_type', ['role', 'department', 'user', 'everyone']);
            $t->unsignedBigInteger('subject_id')->nullable();
            Columns::dt($t, 'created_at');
            Columns::index($t, ['subject_type', 'subject_id']);
        });

        Schema::create('view_panels', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'parent_panel_id', 'view_panels');
            Columns::enum($t, 'type', ['tabs', 'tab', 'section', 'related_table', 'derived_fields', 'summary_widget', 'status_timeline', 'comments', 'attachments', 'form_body', 'html']);
            Columns::json($t, 'relation_path', nullable: true);
            Columns::json($t, 'config');
            Columns::fk($t, 'visibility_condition_id', 'conditions');
            $t->integer('sort_order')->default(0);
            Columns::index($t, ['form_id', 'parent_panel_id', 'sort_order']);
        });

        Schema::create('reference_previews', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'target_form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'field_id', 'fields');
            Columns::json($t, 'display_paths');
            Columns::json($t, 'layout');
            Columns::json($t, 'autofill_map', nullable: true);
            $t->boolean('drawer_enabled')->default(true);
            Columns::index($t, ['target_form_id', 'field_id']);
        });

        Schema::create('print_layouts', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'paper', ['a4', 'a3', 'letter', 'legal']);
            Columns::enum($t, 'orientation', ['portrait', 'landscape']);
            Columns::json($t, 'layout');
            $t->boolean('show_logo')->default(true);
            $t->boolean('is_default')->default(false);
            Columns::unique($t, ['form_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_layouts');
        Schema::dropIfExists('reference_previews');
        Schema::dropIfExists('view_panels');
        Schema::dropIfExists('saved_view_shares');
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('filters');
        Schema::dropIfExists('view_columns');
        Schema::dropIfExists('views');
    }
};
