import type { RouteRecordRaw } from 'vue-router'

/**
 * Routes of the platform area (Phase 5), mounted inside the application
 * shell. Each names the permissions the server enforces on its endpoints;
 * the guard only hides screens.
 */
export const platformRoutes: RouteRecordRaw[] = [
  {
    path: 'admin/extensions',
    name: 'admin.extensions',
    component: () => import('@/views/extensions/ExtensionList.vue'),
    meta: { anyOf: ['system.manage_code', 'system.approve_code'], title: 'admin.area.extensions' },
  },
  {
    path: 'admin/extensions/:extension',
    name: 'admin.extensions.edit',
    component: () => import('@/views/extensions/ExtensionEditor.vue'),
    meta: { anyOf: ['system.manage_code', 'system.approve_code'], title: 'admin.area.extensions' },
  },
  {
    path: 'admin/integrations/webhooks/:webhook',
    name: 'admin.integrations.webhook',
    component: () => import('@/views/integrations/WebhookEditor.vue'),
    meta: { anyOf: ['system.manage_integrations'], title: 'admin.area.integrations' },
  },
  {
    path: 'admin/integrations/inbound/:endpoint',
    name: 'admin.integrations.inbound',
    component: () => import('@/views/integrations/InboundEditor.vue'),
    meta: { anyOf: ['system.manage_integrations'], title: 'admin.area.integrations' },
  },
  {
    path: 'admin/integrations/:tab?',
    name: 'admin.integrations',
    component: () => import('@/views/integrations/IntegrationsView.vue'),
    meta: { anyOf: ['system.manage_integrations'], title: 'admin.area.integrations' },
  },
  {
    path: 'admin/packages/reports/:report',
    name: 'admin.packages.report',
    component: () => import('@/views/packages/DriftReportView.vue'),
    meta: { anyOf: ['system.manage_packages'], title: 'admin.area.packages' },
  },
  {
    path: 'admin/packages/p/:package',
    name: 'admin.packages.package',
    component: () => import('@/views/packages/PackageDetail.vue'),
    meta: { anyOf: ['system.manage_packages'], title: 'admin.area.packages' },
  },
  {
    path: 'admin/packages/:tab?',
    name: 'admin.packages',
    component: () => import('@/views/packages/PackagesView.vue'),
    meta: { anyOf: ['system.manage_packages'], title: 'admin.area.packages' },
  },
  {
    path: 'admin/reports/r/:report',
    name: 'admin.reports.report',
    component: () => import('@/views/reports/ReportEditor.vue'),
    meta: { anyOf: ['system.manage_reports'], title: 'admin.area.reports' },
  },
  {
    path: 'admin/reports/d/:dashboard',
    name: 'admin.reports.dashboard',
    component: () => import('@/views/reports/DashboardEditor.vue'),
    meta: { anyOf: ['system.manage_reports'], title: 'admin.area.reports' },
  },
  {
    path: 'admin/reports/:tab?',
    name: 'admin.reports',
    component: () => import('@/views/reports/ReportsAdmin.vue'),
    meta: { anyOf: ['system.manage_reports'], title: 'admin.area.reports' },
  },
  // Viewing: the server decides per report and dashboard.
  { path: 'insights', name: 'insights', component: () => import('@/views/reports/InsightsView.vue'), meta: { title: 'rep.insights' } },
  { path: 'reports/:report', name: 'reports.view', component: () => import('@/views/reports/ReportView.vue'), meta: { title: 'rep.report' } },
  // Pages: the server decides per page and per what it shows.
  { path: 'pages/:page', name: 'pages.view', component: () => import('@/views/experience/PageView.vue'), meta: { title: 'xp.page' } },
  { path: 'dashboards/:dashboard', name: 'dashboards.view', component: () => import('@/views/reports/DashboardView.vue'), meta: { title: 'rep.dashboard' } },
]
