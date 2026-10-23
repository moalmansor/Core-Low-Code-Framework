import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useSession } from '@/stores/session'
import { builderRoutes } from './builder'
import { buildingRoutes } from './building'
import { platformRoutes } from './platform'
import { runtimeRoutes } from './runtime'

declare module 'vue-router' {
  interface RouteMeta {
    guest?: boolean
    public?: boolean
    /** Any one of these permissions grants access (checked again server-side). */
    anyOf?: string[]
    title?: string
    /** The download profile screens work on the user's own personal profiles. */
    personalDownloads?: boolean
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/setup', name: 'setup', component: () => import('@/views/setup/SetupWizard.vue'), meta: { public: true, title: 'setup.title' } },
  {
    path: '/',
    component: () => import('@/layouts/AppShell.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { title: 'shell.home' } },
      // An application's own home screen (specification §4.29); the server decides what is shown.
      { path: 'a/:application', name: 'apps.home', component: () => import('@/views/HomeView.vue'), meta: { title: 'shell.home' } },
      { path: 'profile', name: 'profile', component: () => import('@/views/profile/ProfileView.vue'), meta: { title: 'profile.title' } },
      { path: 'my-work', name: 'my_work', component: () => import('@/views/work/MyWorkView.vue'), meta: { title: 'my_work.title' } },
      { path: 'admin', name: 'admin', component: () => import('@/views/admin/AdminConsole.vue'), meta: { title: 'admin.title' } },
      {
        path: 'admin/health',
        name: 'admin.health',
        component: () => import('@/views/admin/SystemHealth.vue'),
        meta: { anyOf: ['system.view_errors', 'system.manage_operations'], title: 'admin.area.system_health' },
      },
      {
        path: 'admin/operations/:tab?',
        name: 'admin.operations',
        component: () => import('@/views/operations/OperationsCenter.vue'),
        meta: { anyOf: ['system.manage_operations'], title: 'admin.area.operations_center' },
      },
      { path: 'admin/users', name: 'admin.users', component: () => import('@/views/admin/UsersView.vue'), meta: { anyOf: ['system.manage_users'], title: 'admin.area.users' } },
      { path: 'admin/departments', name: 'admin.departments', component: () => import('@/views/admin/DepartmentsView.vue'), meta: { anyOf: ['system.manage_users'], title: 'admin.area.departments' } },
      {
        path: 'admin/access-policies',
        name: 'admin.access_policies',
        component: () => import('@/views/access/AccessPoliciesView.vue'),
        meta: { anyOf: ['system.manage_access_policies'], title: 'admin.area.access_policies' },
      },
      { path: 'admin/roles', name: 'admin.roles', component: () => import('@/views/admin/RolesView.vue'), meta: { anyOf: ['system.manage_permissions'], title: 'admin.area.roles_permissions' } },
      {
        path: 'admin/settings/:group?',
        name: 'admin.settings',
        component: () => import('@/views/admin/SettingsView.vue'),
        meta: { anyOf: ['system.manage_settings', 'system.manage_branding', 'system.manage_translations'], title: 'admin.area.system_settings' },
      },
      {
        path: 'admin/translations',
        name: 'admin.translations',
        component: () => import('@/views/admin/TranslationsView.vue'),
        meta: { anyOf: ['system.manage_translations'], title: 'admin.area.translations' },
      },
      {
        path: 'admin/reason-codes',
        name: 'admin.reason_codes',
        component: () => import('@/views/admin/ReasonCodesView.vue'),
        meta: { anyOf: ['system.manage_justification_rules'], title: 'admin.area.reason_codes' },
      },
      {
        path: 'admin/work',
        name: 'admin.work',
        component: () => import('@/views/admin/WorkAdminView.vue'),
        meta: { anyOf: ['system.manage_forms', 'system.manage_delegation'], title: 'admin.area.assignment_queues' },
      },
      {
        path: 'admin/external/:tab(forms|accounts)?',
        name: 'admin.external',
        component: () => import('@/views/external/ExternalAdmin.vue'),
        meta: { anyOf: ['system.manage_external_access'], title: 'admin.area.external_access' },
      },
      {
        path: 'admin/external/forms/:form',
        name: 'admin.external.form',
        component: () => import('@/views/external/ExternalFormEditor.vue'),
        meta: { anyOf: ['system.manage_external_access'], title: 'admin.area.external_access' },
      },
      {
        path: 'admin/notification-channels',
        name: 'admin.notification_channels',
        component: () => import('@/views/admin/notifications/ChannelsView.vue'),
        meta: { anyOf: ['system.manage_integrations'], title: 'admin.area.notification_channels' },
      },
      {
        path: 'admin/notification-channels/:channel',
        name: 'admin.notification_channels.channel',
        component: () => import('@/views/admin/notifications/ChannelEditor.vue'),
        meta: { anyOf: ['system.manage_integrations'], title: 'admin.area.notification_channels' },
      },
      {
        path: 'admin/notifications',
        name: 'admin.notifications',
        component: () => import('@/views/admin/notifications/TemplatesView.vue'),
        meta: { anyOf: ['system.manage_notifications'], title: 'admin.area.notifications' },
      },
      {
        path: 'admin/notifications/:template',
        name: 'admin.notifications.template',
        component: () => import('@/views/admin/notifications/TemplateEditor.vue'),
        meta: { anyOf: ['system.manage_notifications'], title: 'admin.area.notifications' },
      },
      {
        path: 'admin/documents',
        name: 'admin.documents',
        component: () => import('@/views/admin/documents/DocumentsView.vue'),
        meta: { anyOf: ['system.manage_document_templates'], title: 'admin.area.documents' },
      },
      {
        path: 'admin/documents/:template',
        name: 'admin.documents.template',
        component: () => import('@/views/admin/documents/DocumentEditor.vue'),
        meta: { anyOf: ['system.manage_document_templates'], title: 'admin.area.documents' },
      },
      { path: 'admin/audit', name: 'admin.audit', component: () => import('@/views/admin/AuditLogView.vue'), meta: { anyOf: ['system.view_audit_log'], title: 'admin.area.audit_log' } },
      { path: 'admin/errors', name: 'admin.errors', component: () => import('@/views/admin/ErrorsView.vue'), meta: { anyOf: ['system.view_errors'], title: 'admin.area.error_monitoring' } },
      ...buildingRoutes,
      ...platformRoutes,
      ...builderRoutes,
      ...runtimeRoutes,
    ],
  },
  // After the shell: both parents have the path '/', and an exact '/' must open the shell's home.
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: 'login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { guest: true, title: 'auth.sign_in' } },
      { path: 'login/two-factor', name: 'two-factor', component: () => import('@/views/auth/TwoFactorChallenge.vue'), meta: { guest: true, title: 'auth.two_factor_title' } },
      { path: 'forgot-password', name: 'forgot', component: () => import('@/views/auth/ForgotPassword.vue'), meta: { guest: true, title: 'auth.forgot_title' } },
      // Shown while the system is offline for maintenance (a 503 with code "maintenance").
      { path: 'maintenance', name: 'maintenance', component: () => import('@/views/experience/MaintenanceView.vue'), meta: { public: true, title: 'xp.maintenance_title' } },
      { path: 'reset-password/:token', name: 'reset', component: () => import('@/views/auth/ResetPassword.vue'), meta: { guest: true, title: 'auth.reset_title' } },
    ],
  },
  // External pages (specification §4.32, ADR-0058): no internal sign-in; the server decides what each one shows.
  {
    path: '/x',
    component: () => import('@/layouts/ExternalLayout.vue'),
    children: [
      { path: 'f/:slug', name: 'x.form', component: () => import('@/views/external/public/FormPage.vue'), meta: { public: true, title: 'xp_form.title' } },
      { path: 'portal', name: 'x.portal', component: () => import('@/views/external/public/PortalPage.vue'), meta: { public: true, title: 'xp_portal.portal' } },
      { path: 'portal/records/:slug/:record', name: 'x.record', component: () => import('@/views/external/public/PortalRecord.vue'), meta: { public: true, title: 'xp_portal.record' } },
      { path: 't/:token', name: 'x.link', component: () => import('@/views/external/public/LinkPage.vue'), meta: { public: true, title: 'xp_link.title' } },
      { path: 's/:token', name: 'x.sign', component: () => import('@/views/external/public/SignPage.vue'), meta: { public: true, title: 'xp_sign.title' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFound.vue'), meta: { public: true } },
]

export const router = createRouter({ history: createWebHistory(), routes })

// Pages whose document carries a different Content-Security-Policy (the extension code editor, ADR-0051)
// are always loaded as a document of their own, and so are the pages after them.
const EDITOR_PAGES = /^\/admin\/extensions(\/|$)/
const bootedOnEditor = EDITOR_PAGES.test(window.location.pathname)

router.beforeEach(async (to, from) => {
  if (from.matched.length > 0 && EDITOR_PAGES.test(to.path) !== bootedOnEditor) {
    window.location.assign(to.fullPath)
    return false
  }
  const session = useSession()
  if (!session.boot) await session.loadBootstrap()
  if (!session.boot!.setup_completed) return to.name === 'setup' ? true : { name: 'setup' }
  if (to.name === 'setup') return { name: 'login' }
  if (to.meta.public) return true
  if (session.me === null) await session.loadMe()
  if (to.meta.guest) return session.me ? { name: 'home' } : true
  if (!session.me) return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  // Two-factor enrollment or an expired password must be resolved first.
  if (session.gate !== 'none' && to.name !== 'profile') return { name: 'profile', query: { tab: session.gate === 'two_factor' ? 'security' : 'password' } }
  if (to.meta.anyOf && !to.meta.anyOf.some((p) => session.can(p))) return { name: 'home' }
  return true
})
