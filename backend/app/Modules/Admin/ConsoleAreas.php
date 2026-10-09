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
        'workflows_views' => ['building', ['system.manage_forms'], '/admin/forms'],
        'reference_data' => ['building', ['system.manage_reference_data', 'system.manage_calendars', 'system.manage_numbering', 'system.manage_currencies'], '/admin/reference'],
        'assignment_queues' => ['people', ['system.manage_forms', 'system.manage_delegation'], '/admin/work'],
        'appearance_branding' => ['configuration', ['system.manage_branding'], '/admin/settings/branding'],
        'translations' => ['configuration', ['system.manage_translations'], '/admin/translations'],
        'system_settings' => ['configuration', ['system.manage_settings'], '/admin/settings'],
        'audit_log' => ['compliance', ['system.view_audit_log'], '/admin/audit'],
        'reason_codes' => ['compliance', ['system.manage_justification_rules'], '/admin/reason-codes'],
        'error_monitoring' => ['operations', ['system.view_errors'], '/admin/errors'],
        'schema' => ['operations', ['system.manage_forms'], '/admin/schema'],
    ];
}
