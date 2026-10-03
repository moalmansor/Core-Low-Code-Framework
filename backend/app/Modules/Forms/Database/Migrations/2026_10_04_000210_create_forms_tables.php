<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Applications, forms and their structure: versions, groups, fields, options,
 * conditions, relations, the reusable field library and menus (architecture
 * §10.5). Foreign keys that close a cycle (forms ↔ form_versions, groups ↔
 * relations ↔ fields) are added after both tables exist; keys to Schema,
 * Reference numbering and Blueprints tables are added by those migrations.
 * Deferred (ADR-0021): applications.theme_id and applications.home_screen_id
 * (Phase 5). Adds the deferred roles.application_id and
 * permission_assignments.condition_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::soft($t);
            Columns::code($t, 'key', 64);
            $t->string('icon', 64)->nullable();
            $t->string('color', 16)->nullable();
            Columns::enum($t, 'status', ['active', 'archived', 'retired']);
            Columns::enum($t, 'data_sharing_default', ['shared', 'isolated']);
            $t->boolean('maintenance_mode')->default(false);
            Columns::dt($t, 'maintenance_until', nullable: true);
            Columns::json($t, 'settings', nullable: true);
            $t->integer('sort_order')->default(0);
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('forms', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::soft($t);
            Columns::fk($t, 'application_id', 'applications', nullable: false, index: false);
            Columns::enum($t, 'kind', ['form', 'collection']);
            Columns::code($t, 'key', 48);
            Columns::code($t, 'table_name', 60);
            Columns::enum($t, 'binding_mode', ['managed', 'bound']);
            Columns::enum($t, 'state', ['draft', 'published', 'unpublished', 'archived', 'schema_inconsistent']);
            self::pending($t, 'current_version_id');
            $t->integer('draft_version_number');
            Columns::dt($t, 'draft_updated_at', nullable: true);
            Columns::fk($t, 'draft_updated_by', 'users');
            $t->string('icon', 64)->nullable();
            Columns::enum($t, 'data_sharing', ['shared', 'isolated']);
            $t->boolean('workflow_enabled')->default(false);
            self::pending($t, 'numbering_sequence_id');
            Columns::fk($t, 'business_calendar_id', 'business_calendars');
            Columns::json($t, 'title_template', nullable: true);
            Columns::json($t, 'settings');
            self::pending($t, 'blueprint_instance_id');
            $t->bigInteger('record_count_cache')->default(0);
            Columns::unique($t, ['organization_id', 'key']);
            Columns::unique($t, ['organization_id', 'table_name']);
            Columns::index($t, ['application_id', 'state']);
        });

        Schema::create('form_versions', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            $t->integer('version_number');
            Columns::enum($t, 'state', ['published', 'superseded', 'rolled_back']);
            Columns::json($t, 'definition');
            Columns::hash($t, 'definition_hash');
            Columns::hash($t, 'schema_hash');
            Columns::enum($t, 'change_class', ['metadata_only', 'additive_schema', 'destructive']);
            Columns::json($t, 'diff_from_previous', nullable: true);
            Columns::json($t, 'impact_report', nullable: true);
            self::pending($t, 'migration_plan_id');
            self::pending($t, 'snapshot_id');
            Columns::fk($t, 'rollback_of_version_id', 'form_versions');
            Columns::dt($t, 'published_at');
            Columns::fk($t, 'published_by', 'users', nullable: false);
            $t->text('change_note')->nullable();
            Columns::unique($t, ['form_id', 'version_number']);
            Columns::index($t, ['form_id', 'state']);
        });

        Schema::table('forms', function (Blueprint $t): void {
            Columns::foreign($t, 'current_version_id', 'form_versions');
        });

        Schema::create('field_templates', function (Blueprint $t): void {
            Columns::meta($t, orgIndex: false);
            Columns::fk($t, 'application_id', 'applications');
            Columns::enum($t, 'kind', ['field', 'group']);
            Columns::code($t, 'category', 64, nullable: true);
            Columns::json($t, 'definition');
            $t->integer('usage_count')->default(0);
            Columns::index($t, ['organization_id', 'kind', 'category']);
        });

        Schema::create('field_groups', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'parent_group_id', 'field_groups');
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'type', ['section', 'fieldset', 'card', 'tabs', 'tab', 'wizard', 'step', 'row', 'column', 'panel', 'accordion', 'repeater', 'subform']);
            $t->integer('sort_order')->default(0);
            Columns::json($t, 'layout');
            $t->boolean('collapsible')->default(false);
            Columns::enum($t, 'default_state', ['open', 'closed']);
            Columns::json($t, 'validation', nullable: true);
            Columns::json($t, 'repeater', nullable: true);
            Columns::json($t, 'wizard', nullable: true);
            Columns::code($t, 'child_table_name', 60, nullable: true);
            Columns::fk($t, 'subform_form_id', 'forms');
            self::pending($t, 'relation_id');
            Columns::enum($t, 'justification_level', ['inherit', 'not_required', 'optional', 'mandatory']);
            Columns::dt($t, 'archived_at', nullable: true);
            Columns::unique($t, ['form_id', 'key']);
            Columns::index($t, ['form_id', 'parent_group_id', 'sort_order']);
        });

        Schema::create('relations', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::code($t, 'key', 48);
            Columns::fk($t, 'source_form_id', 'forms', nullable: false, index: false);
            Columns::fk($t, 'target_form_id', 'forms', nullable: false);
            Columns::enum($t, 'type', ['one_to_one', 'one_to_many', 'many_to_one', 'many_to_many']);
            Columns::enum($t, 'kind', ['reference', 'child_table', 'subform']);
            Columns::code($t, 'fk_table', 60);
            Columns::code($t, 'fk_column', 60, nullable: true);
            Columns::code($t, 'pivot_table', 60, nullable: true);
            self::pending($t, 'display_field_id');
            self::pending($t, 'value_field_id');
            Columns::enum($t, 'on_delete', ['restrict', 'cascade', 'set_null']);
            Columns::code($t, 'inverse_key', 48, nullable: true);
            $t->boolean('is_cross_application')->default(false);
            Columns::unique($t, ['source_form_id', 'key']);
        });

        Schema::create('fields', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'group_id', 'field_groups');
            Columns::code($t, 'key', 48);
            Columns::code($t, 'type', 48);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_stored')->default(true);
            Columns::code($t, 'column_name', 60, nullable: true);
            Columns::code($t, 'db_type', 32, nullable: true);
            $t->integer('length')->nullable();
            $t->smallInteger('precision')->nullable();
            $t->smallInteger('scale')->nullable();
            $t->boolean('is_nullable')->default(true);
            Columns::json($t, 'db_default', nullable: true);
            Columns::enum($t, 'index_type', ['none', 'index', 'unique']);
            Columns::json($t, 'unique_scope', nullable: true);
            $t->boolean('is_encrypted')->default(false);
            $t->boolean('blind_index')->default(false);
            $t->boolean('is_sensitive')->default(false);
            $t->boolean('is_personal_data')->default(false);
            $t->boolean('track_changes')->default(true);
            Columns::fk($t, 'relation_id', 'relations');
            Columns::json($t, 'options_source', nullable: true);
            Columns::json($t, 'validation');
            Columns::json($t, 'behavior');
            Columns::json($t, 'ui');
            Columns::json($t, 'table_settings');
            Columns::json($t, 'export_settings');
            Columns::json($t, 'events', nullable: true);
            Columns::json($t, 'hook_binding', nullable: true);
            Columns::enum($t, 'justification_level', ['inherit', 'not_required', 'optional', 'mandatory']);
            Columns::fk($t, 'template_id', 'field_templates');
            Columns::dt($t, 'archived_at', nullable: true);
            Columns::code($t, 'archived_column_name', 60, nullable: true);
            Columns::unique($t, ['form_id', 'key']);
            Columns::index($t, ['form_id', 'group_id', 'sort_order']);
            Columns::index($t, ['form_id', 'column_name']);
        });

        Schema::table('field_groups', function (Blueprint $t): void {
            Columns::foreign($t, 'relation_id', 'relations');
        });
        Schema::table('relations', function (Blueprint $t): void {
            Columns::foreign($t, 'display_field_id', 'fields');
            Columns::foreign($t, 'value_field_id', 'fields');
        });

        Schema::create('collections', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::timestamps($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', nullable: false, index: false);
            Columns::enum($t, 'collection_type', ['key_value', 'table']);
            $t->boolean('is_shared_reference')->default(false);
            Columns::fk($t, 'owner_application_id', 'applications');
            Columns::fk($t, 'value_field_id', 'fields');
            Columns::fk($t, 'label_field_id', 'fields');
            Columns::fk($t, 'parent_field_id', 'fields');
            Columns::unique($t, ['form_id']);
        });

        Schema::create('conditions', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'form_id', 'forms', 'cascade', index: false);
            Columns::enum($t, 'owner_type', ['field', 'group', 'option', 'action', 'action_step', 'transition', 'notification_rule', 'justification_rule', 'automation', 'automation_step', 'view', 'view_panel', 'menu_item', 'page_widget', 'permission_assignment', 'record_access_rule', 'sla_rule', 'assignment_rule', 'legal_hold', 'form']);
            $t->unsignedBigInteger('owner_id');
            $t->string('name', 255)->nullable();
            Columns::json($t, 'ast');
            Columns::json($t, 'effects');
            Columns::json($t, 'else_effects', nullable: true);
            Columns::enum($t, 'evaluate_on', ['always', 'change']);
            Columns::enum($t, 'runtime', ['client_and_server', 'server_only']);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['owner_type', 'owner_id']);
            Columns::index($t, ['form_id', 'is_active']);
        });

        Schema::create('field_options', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::org($t);
            Columns::timestamps($t);
            Columns::fk($t, 'field_id', 'fields', 'cascade', nullable: false, index: false);
            $t->string('value', 255);
            Columns::code($t, 'group_key', 48, nullable: true);
            $t->string('parent_value', 255)->nullable();
            $t->string('color', 16)->nullable();
            $t->string('icon', 64)->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            Columns::fk($t, 'condition_id', 'conditions');
            Columns::unique($t, ['field_id', 'value']);
            Columns::index($t, ['field_id', 'sort_order']);
        });

        Schema::create('menu_items', function (Blueprint $t): void {
            Columns::meta($t);
            Columns::fk($t, 'application_id', 'applications', 'cascade', nullable: false, index: false);
            Columns::fk($t, 'parent_id', 'menu_items');
            Columns::enum($t, 'type', ['form', 'collection', 'page', 'report', 'dashboard', 'my_work', 'link', 'separator', 'header']);
            Columns::code($t, 'target_type', 32, nullable: true);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->string('url', 2048)->nullable();
            $t->boolean('open_in_new_tab')->default(false);
            $t->string('icon', 64)->nullable();
            Columns::json($t, 'badge', nullable: true);
            Columns::fk($t, 'visibility_condition_id', 'conditions');
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['application_id', 'parent_id', 'sort_order']);
            Columns::index($t, ['target_type', 'target_id']);
        });

        Schema::table('roles', function (Blueprint $t): void {
            Columns::fk($t, 'application_id', 'applications');
        });
        Schema::table('permission_assignments', function (Blueprint $t): void {
            Columns::fk($t, 'condition_id', 'conditions');
        });
    }

    public function down(): void
    {
        Schema::table('permission_assignments', function (Blueprint $t): void {
            $t->dropForeign('fk_permission_assignments_condition_id');
            $t->dropIndex('ix_permission_assignments_condition_id');
            $t->dropColumn('condition_id');
        });
        Schema::table('roles', function (Blueprint $t): void {
            $t->dropForeign('fk_roles_application_id');
            $t->dropIndex('ix_roles_application_id');
            $t->dropColumn('application_id');
        });
        Schema::table('relations', function (Blueprint $t): void {
            $t->dropForeign('fk_relations_display_field_id');
            $t->dropForeign('fk_relations_value_field_id');
        });
        Schema::table('field_groups', function (Blueprint $t): void {
            $t->dropForeign('fk_field_groups_relation_id');
        });
        Schema::table('forms', function (Blueprint $t): void {
            $t->dropForeign('fk_forms_current_version_id');
        });
        foreach (['menu_items', 'field_options', 'conditions', 'collections', 'fields', 'relations', 'field_groups', 'field_templates', 'form_versions', 'forms', 'applications'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** A nullable foreign-key column whose constraint is added once its target table exists. */
    private static function pending(Blueprint $t, string $column): void
    {
        $t->unsignedBigInteger($column)->nullable();
        Columns::index($t, [$column]);
    }
};
