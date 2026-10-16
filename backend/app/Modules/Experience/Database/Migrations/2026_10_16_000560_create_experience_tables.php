<?php

declare(strict_types=1);

use App\Infrastructure\Database\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Themes, pages, home screens, announcements, help, tours and search
 * configuration (architecture §10.23, specification §4.27–4.29, §4.37).
 * Adds the columns deferred to Phase 5 (ADR-0021): applications.theme_id,
 * applications.home_screen_id, organizations.theme_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::code($t, 'key', 48);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_preset')->default(false);
            Columns::json($t, 'tokens');
            $t->text('custom_css')->nullable();
            $t->text('custom_css_compiled')->nullable();
            Columns::json($t, 'contrast_report', nullable: true);
            $t->integer('version');
            Columns::unique($t, ['organization_id', 'key']);
        });

        Schema::create('theme_assets', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'theme_id', 'themes', onDelete: 'cascade', nullable: false, index: false);
            Columns::enum($t, 'kind', ['logo', 'logo_dark', 'favicon', 'login_background', 'email_header', 'email_footer', 'font_arabic', 'font_latin']);
            Columns::code($t, 'locale', 10, nullable: true);
            Columns::fk($t, 'file_id', 'files', nullable: false);
            Columns::index($t, ['theme_id', 'kind', 'locale']);
        });

        Schema::create('home_screens', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::enum($t, 'target_type', ['default', 'role', 'department', 'user']);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->integer('priority')->default(0);
            Columns::json($t, 'layout');
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['organization_id', 'application_id', 'target_type', 'target_id']);
        });

        Schema::create('pages', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::dt($t, 'deleted_at', nullable: true);
            Columns::fk($t, 'deleted_by', 'users');
            Columns::fk($t, 'application_id', 'applications', nullable: false, index: false);
            Columns::code($t, 'key', 48);
            Columns::enum($t, 'type', ['content', 'link', 'dashboard', 'report', 'form_host', 'knowledge', 'changelog']);
            $t->string('url', 2048)->nullable();
            Columns::fk($t, 'dashboard_id', 'dashboards');
            Columns::fk($t, 'report_id', 'reports');
            Columns::fk($t, 'form_id', 'forms');
            Columns::json($t, 'layout', nullable: true);
            $t->boolean('is_published')->default(false);
            Columns::dt($t, 'published_at', nullable: true);
            Columns::index($t, ['organization_id', 'deleted_at']);
            Columns::unique($t, ['application_id', 'key']);
        });

        Schema::create('page_widgets', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'page_id', 'pages', onDelete: 'cascade', index: false);
            Columns::fk($t, 'home_screen_id', 'home_screens', index: false);
            Columns::enum($t, 'type', ['my_work', 'my_records', 'chart', 'kpi', 'shortcuts', 'announcements', 'recent_activity', 'pinned_links', 'embedded_table', 'report', 'html']);
            Columns::json($t, 'config');
            Columns::json($t, 'layout');
            Columns::fk($t, 'visibility_condition_id', 'conditions');
            $t->integer('sort_order')->default(0);
            Columns::index($t, ['page_id', 'sort_order']);
            Columns::index($t, ['home_screen_id', 'sort_order']);
        });

        Schema::create('announcements', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false, index: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'application_id', 'applications');
            Columns::enum($t, 'severity', ['info', 'success', 'warning', 'danger']);
            Columns::enum($t, 'placement', ['banner', 'home', 'bell']);
            Columns::json($t, 'audience_role_ids', nullable: true);
            Columns::dt($t, 'starts_at');
            Columns::dt($t, 'ends_at', nullable: true);
            $t->boolean('is_dismissible')->default(true);
            $t->boolean('is_published')->default(false);
            Columns::fk($t, 'published_by', 'users');
            Columns::index($t, ['organization_id', 'is_published', 'starts_at']);
        });

        Schema::create('announcement_dismissals', function (Blueprint $t): void {
            Columns::fk($t, 'announcement_id', 'announcements', onDelete: 'cascade', nullable: false, index: false);
            Columns::fk($t, 'user_id', 'users', nullable: false);
            Columns::dt($t, 'dismissed_at');
        });

        Schema::create('help_content', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::enum($t, 'target_type', ['form', 'field', 'group', 'page', 'application', 'admin_area']);
            $t->unsignedBigInteger('target_id')->nullable();
            Columns::enum($t, 'display', ['tooltip', 'side_panel', 'help_page']);
            $t->boolean('is_published')->default(false);
            $t->integer('sort_order')->default(0);
            Columns::index($t, ['target_type', 'target_id']);
        });

        Schema::create('tours', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::uuid($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms');
            Columns::code($t, 'route_key', 128, nullable: true);
            Columns::json($t, 'audience_role_ids', nullable: true);
            Columns::enum($t, 'trigger', ['first_use', 'manual']);
            Columns::json($t, 'steps');
            $t->integer('version');
            $t->boolean('is_active')->default(true);
            Columns::index($t, ['route_key']);
        });

        Schema::create('tour_progress', function (Blueprint $t): void {
            Columns::fk($t, 'tour_id', 'tours', onDelete: 'cascade', nullable: false, index: false);
            Columns::fk($t, 'user_id', 'users', nullable: false);
            $t->integer('version');
            Columns::dt($t, 'completed_at', nullable: true);
            Columns::dt($t, 'dismissed_at', nullable: true);
        });

        Schema::create('search_configs', function (Blueprint $t): void {
            Columns::pk($t);
            Columns::fk($t, 'organization_id', 'organizations', nullable: false);
            Columns::dt($t, 'created_at');
            Columns::dt($t, 'updated_at');
            Columns::fk($t, 'created_by', 'users');
            Columns::fk($t, 'updated_by', 'users');
            Columns::fk($t, 'form_id', 'forms', onDelete: 'cascade', nullable: false, index: false);
            $t->boolean('is_enabled')->default(true);
            Columns::json($t, 'fields');
            Columns::json($t, 'result_template');
            Columns::json($t, 'role_scope_ids', nullable: true);
            Columns::unique($t, ['form_id']);
        });

        Schema::table('applications', function (Blueprint $t): void {
            Columns::fk($t, 'theme_id', 'themes');
            Columns::fk($t, 'home_screen_id', 'home_screens');
        });

        Schema::table('organizations', function (Blueprint $t): void {
            Columns::fk($t, 'theme_id', 'themes');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $t): void {
            $t->dropForeign('fk_organizations_theme_id');
            $t->dropIndex('ix_organizations_theme_id');
            $t->dropColumn('theme_id');
        });

        Schema::table('applications', function (Blueprint $t): void {
            $t->dropForeign('fk_applications_home_screen_id');
            $t->dropForeign('fk_applications_theme_id');
            $t->dropIndex('ix_applications_theme_id');
            $t->dropColumn('theme_id');
            $t->dropIndex('ix_applications_home_screen_id');
            $t->dropColumn('home_screen_id');
        });

        Schema::dropIfExists('search_configs');

        Schema::dropIfExists('tour_progress');

        Schema::dropIfExists('tours');

        Schema::dropIfExists('help_content');

        Schema::dropIfExists('announcement_dismissals');

        Schema::dropIfExists('announcements');

        Schema::dropIfExists('page_widgets');

        Schema::dropIfExists('pages');

        Schema::dropIfExists('home_screens');

        Schema::dropIfExists('theme_assets');

        Schema::dropIfExists('themes');
    }
};
