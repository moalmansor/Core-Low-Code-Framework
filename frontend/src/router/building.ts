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
    path: 'admin/applications/:application/pages',
    name: 'admin.applications.pages',
    component: () => import('@/views/experience/PagesAdmin.vue'),
    meta: { anyOf: ['system.manage_pages_menus'], title: 'xp.pages' },
  },
  {
    path: 'admin/applications/:application/pages/:page',
    name: 'admin.applications.page',
    component: () => import('@/views/experience/PageEditor.vue'),
    meta: { anyOf: ['system.manage_pages_menus'], title: 'xp.page' },
  },
  {
    path: 'admin/themes',
    name: 'admin.themes',
    component: () => import('@/views/experience/ThemesAdmin.vue'),
    meta: { anyOf: ['system.manage_branding'], title: 'admin.area.themes' },
  },
  {
    path: 'admin/themes/:theme',
    name: 'admin.themes.theme',
    component: () => import('@/views/experience/ThemeEditor.vue'),
    meta: { anyOf: ['system.manage_branding'], title: 'admin.area.themes' },
  },
  {
    path: 'admin/help',
    name: 'admin.help_content',
    component: () => import('@/views/help/HelpContentAdmin.vue'),
    meta: { anyOf: ['system.manage_help_content'], title: 'admin.area.help_content' },
  },
  {
    path: 'admin/announcements',
    name: 'admin.announcements',
    component: () => import('@/views/experience/AnnouncementsAdmin.vue'),
    meta: { anyOf: ['system.publish_announcements'], title: 'admin.area.announcements' },
  },
  {
    path: 'admin/search',
    name: 'admin.search',
    component: () => import('@/views/experience/SearchConfigView.vue'),
    meta: { anyOf: ['system.manage_pages_menus'], title: 'admin.area.global_search' },
  },
  {
    path: 'admin/home-screens',
    name: 'admin.home_screens',
    component: () => import('@/views/experience/HomeScreensAdmin.vue'),
    meta: { anyOf: ['system.manage_pages_menus'], title: 'admin.area.home_screens' },
  },
  {
    path: 'admin/home-screens/:screen',
    name: 'admin.home_screens.screen',
    component: () => import('@/views/experience/HomeScreenEditor.vue'),
    meta: { anyOf: ['system.manage_pages_menus'], title: 'admin.area.home_screens' },
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
    path: 'admin/forms/:form/configure/:tab?',
    name: 'admin.forms.configure',
    component: () => import('@/views/admin/formconfig/FormConfigView.vue'),
    meta: { anyOf: ['system.manage_forms'], title: 'formconfig.page_title' },
  },
  {
    path: 'admin/forms/:form/downloads/:profile',
    name: 'admin.downloads.profile',
    component: () => import('@/views/downloads/ProfileEditor.vue'),
    meta: { anyOf: ['system.manage_download_profiles'], title: 'dl.page_title' },
  },
  {
    path: 'admin/automations',
    name: 'admin.automations',
    component: () => import('@/views/automations/AutomationList.vue'),
    meta: { anyOf: ['system.manage_automations'], title: 'admin.area.automations' },
  },
  {
    path: 'admin/forms/:form/automations/:automation',
    name: 'admin.automations.edit',
    component: () => import('@/views/automations/AutomationEditor.vue'),
    meta: { anyOf: ['system.manage_automations'], title: 'auto.page_title' },
  },
  {
    path: 'admin/scheduled-tasks',
    name: 'admin.scheduled_tasks',
    component: () => import('@/views/automations/ScheduledTasksView.vue'),
    meta: { anyOf: ['system.manage_automations', 'system.manage_operations'], title: 'admin.area.scheduled_tasks' },
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
