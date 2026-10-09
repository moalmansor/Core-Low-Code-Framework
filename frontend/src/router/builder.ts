import type { RouteRecordRaw } from 'vue-router'

/** Routes of the builder area, mounted inside the application shell. */
export const builderRoutes: RouteRecordRaw[] = [
  {
    path: 'admin/forms/:form/builder',
    name: 'admin.forms.builder',
    component: () => import('@/views/builder/FormBuilder.vue'),
    meta: { anyOf: ['system.manage_forms'], title: 'builder.title' },
  },
  {
    path: 'admin/forms/:form/versions',
    name: 'admin.forms.versions',
    component: () => import('@/views/builder/FormVersions.vue'),
    meta: { anyOf: ['system.manage_forms'], title: 'builder.versions' },
  },
]
