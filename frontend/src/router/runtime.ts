import type { RouteRecordRaw } from 'vue-router'

/**
 * Routes of the runtime area, mounted inside the application shell. `:form`
 * is the form uuid and `:record` the record uuid; access is checked by the
 * records API on every request (the screens follow its answers).
 */
const uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'

export const runtimeRoutes: RouteRecordRaw[] = [
  { path: `app/:form(${uuid})`, name: 'records.list', component: () => import('@/views/records/RecordList.vue'), meta: { title: 'records.title' } },
  { path: `app/:form(${uuid})/new`, name: 'records.create', component: () => import('@/views/records/RecordForm.vue'), meta: { title: 'records.new' } },
  { path: `app/:form(${uuid})/:record(${uuid})`, name: 'records.view', component: () => import('@/views/records/RecordView.vue'), meta: { title: 'records.view' } },
  { path: `app/:form(${uuid})/:record(${uuid})/edit`, name: 'records.edit', component: () => import('@/views/records/RecordForm.vue'), meta: { title: 'records.edit' } },
]
