<?php

declare(strict_types=1);

namespace App\Modules\Access;

/**
 * The seeded system permission catalog (specification §2 and §4.11,
 * architecture §21.1). Labels ship in the two default locales; further locales
 * are added through the translation manager.
 */
final class PermissionCatalog
{
    /**
     * key => [category, dangerous, label en, label ar]
     *
     * @var array<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    public const SYSTEM = [
        'system.manage_settings' => ['administration', true, 'Manage settings', 'إدارة الإعدادات'],
        'system.manage_translations' => ['administration', false, 'Manage translations', 'إدارة الترجمات'],
        'system.enable_maintenance_mode' => ['administration', true, 'Enable maintenance mode', 'تفعيل وضع الصيانة'],
        'system.manage_branding' => ['administration', false, 'Manage branding', 'إدارة الهوية البصرية'],
        'system.manage_users' => ['access', false, 'Manage users', 'إدارة المستخدمين'],
        'system.manage_permissions' => ['access', true, 'Manage permissions', 'إدارة الصلاحيات'],
        'system.manage_access_policies' => ['access', true, 'Manage access policies', 'إدارة سياسات الوصول'],
        'system.impersonate_users' => ['access', true, 'Impersonate users', 'انتحال صفة المستخدمين'],
        'system.view_audit_log' => ['compliance', false, 'View audit log', 'عرض سجل التدقيق'],
        'system.manage_retention' => ['compliance', true, 'Manage retention', 'إدارة الاحتفاظ بالبيانات'],
        'system.manage_personal_data_requests' => ['compliance', true, 'Manage personal data requests', 'إدارة طلبات البيانات الشخصية'],
        'system.apply_legal_hold' => ['compliance', true, 'Apply legal hold', 'تطبيق الحجز القانوني'],
        'system.view_justifications' => ['compliance', false, 'View justifications', 'عرض المبررات'],
        'system.manage_justification_rules' => ['compliance', false, 'Manage justification rules', 'إدارة قواعد التبرير'],
        'system.view_errors' => ['operations', false, 'View errors', 'عرض الأخطاء'],
        'system.manage_operations' => ['operations', false, 'Manage operations', 'إدارة العمليات'],
        'system.manage_code' => ['development', true, 'Manage code', 'إدارة الشيفرة البرمجية'],
        'system.approve_code' => ['development', true, 'Approve code', 'اعتماد الشيفرة البرمجية'],
        'system.manage_forms' => ['building', false, 'Manage forms', 'إدارة النماذج'],
        'system.manage_applications' => ['building', false, 'Manage applications', 'إدارة التطبيقات'],
        'system.manage_pages_menus' => ['building', false, 'Manage pages and menus', 'إدارة الصفحات والقوائم'],
        'system.manage_blueprints' => ['building', false, 'Manage blueprints', 'إدارة المخططات الجاهزة'],
        'system.manage_reports' => ['building', false, 'Manage reports and dashboards', 'إدارة التقارير ولوحات المعلومات'],
        'system.manage_notifications' => ['building', false, 'Manage notifications and e-mail templates', 'إدارة الإشعارات وقوالب البريد'],
        'system.manage_document_templates' => ['building', false, 'Manage document templates', 'إدارة قوالب المستندات'],
        'system.manage_download_profiles' => ['building', false, 'Manage download profiles', 'إدارة ملفات التنزيل'],
        'system.create_personal_download_profiles' => ['building', false, 'Create personal download profiles', 'إنشاء ملفات تنزيل شخصية'],
        'system.manage_automations' => ['automation', false, 'Manage automations', 'إدارة الأتمتة'],
        'system.run_automations' => ['automation', false, 'Run automations manually', 'تشغيل الأتمتة يدويًا'],
        'system.manage_integrations' => ['integration', true, 'Manage integrations', 'إدارة التكاملات'],
        'system.manage_external_access' => ['integration', true, 'Manage external access', 'إدارة الوصول الخارجي'],
        'system.manage_packages' => ['integration', true, 'Manage configuration packages', 'إدارة حزم الإعدادات'],
        'system.manage_reference_data' => ['reference', false, 'Manage reference data', 'إدارة البيانات المرجعية'],
        'system.manage_numbering' => ['reference', false, 'Manage numbering', 'إدارة الترقيم'],
        'system.manage_calendars' => ['reference', false, 'Manage calendars', 'إدارة التقويمات'],
        'system.manage_currencies' => ['reference', false, 'Manage currencies', 'إدارة العملات'],
        'system.assign_records' => ['work', false, 'Assign records', 'إسناد السجلات'],
        'system.reassign_records' => ['work', false, 'Reassign records', 'إعادة إسناد السجلات'],
        'system.manage_delegation' => ['work', false, 'Manage delegation', 'إدارة التفويض'],
        'system.delegate_own_work' => ['work', false, 'Delegate own work', 'تفويض العمل الخاص'],
        'system.merge_records' => ['data', true, 'Merge records', 'دمج السجلات'],
        'system.bulk_update' => ['data', true, 'Bulk update', 'التحديث الجماعي'],
        'system.access_recycle_bin' => ['data', false, 'Access recycle bin', 'الوصول إلى سلة المحذوفات'],
        'system.repair_data' => ['data', true, 'Repair data', 'إصلاح البيانات'],
        'system.manage_feature_flags' => ['experience', false, 'Manage feature flags', 'إدارة مفاتيح الميزات'],
        'system.manage_help_content' => ['experience', false, 'Manage help content', 'إدارة محتوى المساعدة'],
        'system.publish_announcements' => ['experience', false, 'Publish announcements', 'نشر الإعلانات'],
        'system.cross_org_reporting' => ['administration', true, 'Cross-organization reporting', 'التقارير عبر المؤسسات'],
    ];

    /**
     * Seeded role grants (architecture §21.1). Ordinary rows, visible and
     * editable in the matrix; nothing bypasses the resolver.
     *
     * @return array<string, list<string>> role key => permission keys
     */
    public static function seededGrants(): array
    {
        $all = array_keys(self::SYSTEM);

        return [
            'super_admin' => $all,
            'admin' => array_values(array_diff($all, ['system.manage_code', 'system.approve_code', 'system.impersonate_users', 'system.cross_org_reporting'])),
            'developer' => ['system.manage_code', 'system.view_errors'],
            'user' => ['system.delegate_own_work'],
        ];
    }

    /**
     * Core roles (specification §2).
     *
     * @return array<string, array{en: string, ar: string, admin: bool, sort: int}>
     */
    public static function coreRoles(): array
    {
        return [
            'super_admin' => ['en' => 'Super Admin', 'ar' => 'المدير الأعلى', 'admin' => true, 'sort' => 1],
            'admin' => ['en' => 'Admin', 'ar' => 'مدير', 'admin' => true, 'sort' => 2],
            'developer' => ['en' => 'Developer', 'ar' => 'مطوّر', 'admin' => true, 'sort' => 3],
            'user' => ['en' => 'User', 'ar' => 'مستخدم', 'admin' => false, 'sort' => 4],
        ];
    }
}
