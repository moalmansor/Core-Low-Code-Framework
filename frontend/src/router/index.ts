import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useSession } from '@/stores/session'

declare module 'vue-router' {
  interface RouteMeta {
    guest?: boolean
    public?: boolean
    /** Any one of these permissions grants access (checked again server-side). */
    anyOf?: string[]
    title?: string
  }
}

const routes: RouteRecordRaw[] = [
  { path: '/setup', name: 'setup', component: () => import('@/views/setup/SetupWizard.vue'), meta: { public: true, title: 'setup.title' } },
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      { path: 'login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { guest: true, title: 'auth.sign_in' } },
      { path: 'login/two-factor', name: 'two-factor', component: () => import('@/views/auth/TwoFactorChallenge.vue'), meta: { guest: true, title: 'auth.two_factor_title' } },
      { path: 'forgot-password', name: 'forgot', component: () => import('@/views/auth/ForgotPassword.vue'), meta: { guest: true, title: 'auth.forgot_title' } },
      { path: 'reset-password/:token', name: 'reset', component: () => import('@/views/auth/ResetPassword.vue'), meta: { guest: true, title: 'auth.reset_title' } },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/AppShell.vue'),
    children: [
      { path: '', name: 'home', component: () => import('@/views/HomeView.vue'), meta: { title: 'shell.home' } },
      { path: 'profile', name: 'profile', component: () => import('@/views/profile/ProfileView.vue'), meta: { title: 'profile.title' } },
      { path: 'admin', name: 'admin', component: () => import('@/views/admin/AdminConsole.vue'), meta: { title: 'admin.title' } },
      {
        path: 'admin/health',
        name: 'admin.health',
        component: () => import('@/views/admin/SystemHealth.vue'),
        meta: { anyOf: ['system.view_errors', 'system.manage_operations'], title: 'admin.area.system_health' },
      },
      { path: 'admin/users', name: 'admin.users', component: () => import('@/views/admin/UsersView.vue'), meta: { anyOf: ['system.manage_users'], title: 'admin.area.users' } },
      { path: 'admin/departments', name: 'admin.departments', component: () => import('@/views/admin/DepartmentsView.vue'), meta: { anyOf: ['system.manage_users'], title: 'admin.area.departments' } },
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
      { path: 'admin/audit', name: 'admin.audit', component: () => import('@/views/admin/AuditLogView.vue'), meta: { anyOf: ['system.view_audit_log'], title: 'admin.area.audit_log' } },
      { path: 'admin/errors', name: 'admin.errors', component: () => import('@/views/admin/ErrorsView.vue'), meta: { anyOf: ['system.view_errors'], title: 'admin.area.error_monitoring' } },
    ],
  },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFound.vue'), meta: { public: true } },
]

export const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach(async (to) => {
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
