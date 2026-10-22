<?php

declare(strict_types=1);

namespace App\Modules\Admin;

/**
 * The Admin Console's areas (specification §4.1). Each area is shown only to
 * holders of its permission, and only once the phase that builds it has
 * shipped: later phases add their areas here.
 */
final class ConsoleAreas
{
    /**
     * key => [section, permission keys (any one grants visibility), client route]
     *
     * @var array<string, array{0: string, 1: list<string>, 2: string}>
     */
    public const BUILT = [
        'system_health' => ['overview', ['system.view_errors', 'system.manage_operations'], '/admin/health'],
        'users' => ['people', ['system.manage_users'], '/admin/users'],
        'departments' => ['people', ['system.manage_users'], '/admin/departments'],
        'roles_permissions' => ['people', ['system.manage_permissions'], '/admin/roles'],
        'applications' => ['building', ['system.manage_applications'], '/admin/applications'],
        'forms' => ['building', ['system.manage_forms'], '/admin/forms'],
        'blueprints' => ['building', ['system.manage_blueprints'], '/admin/blueprints'],
        'notifications' => ['building', ['system.manage_notifications'], '/admin/notifications'],
        'documents' => ['building', ['system.manage_document_templates'], '/admin/documents'],
        'automations' => ['building', ['system.manage_automations'], '/admin/automations'],
        'reference_data' => ['building', ['system.manage_reference_data', 'system.manage_calendars', 'system.manage_numbering', 'system.manage_currencies'], '/admin/reference'],
        'assignment_queues' => ['people', ['system.manage_forms', 'system.manage_delegation'], '/admin/work'],
        'reports' => ['building', ['system.manage_reports'], '/admin/reports'],
        'home_screens' => ['building', ['system.manage_pages_menus'], '/admin/home-screens'],
        'global_search' => ['building', ['system.manage_pages_menus'], '/admin/search'],
        'announcements' => ['building', ['system.publish_announcements'], '/admin/announcements'],
        'extensions' => ['building', ['system.manage_code', 'system.approve_code'], '/admin/extensions'],
        'integrations' => ['configuration', ['system.manage_integrations'], '/admin/integrations'],
        'notification_channels' => ['configuration', ['system.manage_integrations'], '/admin/notification-channels'],
        'packages' => ['configuration', ['system.manage_packages'], '/admin/packages'],
        'appearance_branding' => ['configuration', ['system.manage_branding'], '/admin/settings/branding'],
        'themes' => ['configuration', ['system.manage_branding'], '/admin/themes'],
        'translations' => ['configuration', ['system.manage_translations'], '/admin/translations'],
        'system_settings' => ['configuration', ['system.manage_settings'], '/admin/settings'],
        'audit_log' => ['compliance', ['system.view_audit_log'], '/admin/audit'],
        'reason_codes' => ['compliance', ['system.manage_justification_rules'], '/admin/reason-codes'],
        'operations_center' => ['operations', ['system.manage_operations'], '/admin/operations'],
        'error_monitoring' => ['operations', ['system.view_errors'], '/admin/errors'],
        'schema' => ['operations', ['system.manage_forms'], '/admin/schema'],
        'scheduled_tasks' => ['operations', ['system.manage_automations', 'system.manage_operations'], '/admin/scheduled-tasks'],
    ];
}
