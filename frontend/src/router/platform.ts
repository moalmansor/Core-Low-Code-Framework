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
]
