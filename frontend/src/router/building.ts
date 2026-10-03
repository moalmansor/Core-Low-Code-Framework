import type { RouteRecordRaw } from 'vue-router'

/**
 * Routes of the building area, mounted inside the application shell. Each
 * route names the permissions the server enforces on its endpoints; the guard
 * only hides screens, authorization stays server-side.
 */
export const buildingRoutes: RouteRecordRaw[] = [
  {
    path: 'admin/applications',
    name: 'admin.applications',
    component: () => import('@/views/admin/building/ApplicationsView.vue'),
    meta: { anyOf: ['system.manage_applications'], title: 'admin.area.applications' },
  },
  {
    path: 'admin/applications/:application/menu',
    name: 'admin.applications.menu',
    component: () => import('@/views/admin/building/MenuEditor.vue'),
    meta: { anyOf: ['system.manage_pages_menus'], title: 'building.menu.page_title' },
  },
  {
    path: 'admin/forms',
    name: 'admin.forms',
    component: () => import('@/views/admin/building/FormsView.vue'),
    meta: { anyOf: ['system.manage_forms'], title: 'admin.area.forms' },
  },
  {
    path: 'admin/forms/:form/access',
    name: 'admin.forms.access',
    component: () => import('@/views/admin/building/FormAccessView.vue'),
    meta: { anyOf: ['system.manage_permissions'], title: 'building.access.page_title' },
  },
  {
    path: 'admin/schema',
    name: 'admin.schema',
    component: () => import('@/views/admin/building/SchemaExplorer.vue'),
    meta: { anyOf: ['system.manage_forms'], title: 'admin.area.schema' },
  },
  {
    path: 'admin/schema/plans/:plan?',
    name: 'admin.schema.plans',
    component: () => import('@/views/admin/building/MigrationPlans.vue'),
    meta: { anyOf: ['system.manage_forms'], title: 'building.plans.title' },
  },
  {
    path: 'admin/blueprints',
    name: 'admin.blueprints',
    component: () => import('@/views/admin/building/BlueprintsView.vue'),
    meta: { anyOf: ['system.manage_blueprints'], title: 'admin.area.blueprints' },
  },
  {
    path: 'admin/blueprints/:blueprint',
    name: 'admin.blueprints.detail',
    component: () => import('@/views/admin/building/BlueprintDetail.vue'),
    meta: { anyOf: ['system.manage_blueprints'], title: 'admin.area.blueprints' },
  },
  {
    path: 'admin/reference/:tab?',
    name: 'admin.reference',
    component: () => import('@/views/admin/building/ReferenceDataView.vue'),
    meta: { anyOf: ['system.manage_reference_data', 'system.manage_calendars', 'system.manage_numbering', 'system.manage_currencies'], title: 'admin.area.reference_data' },
  },
]
