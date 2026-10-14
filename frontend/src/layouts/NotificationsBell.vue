<script setup lang="ts">
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { get, send } from '@/api/http'
import EmptyState from '@/components/config/EmptyState.vue'
import { useSession } from '@/stores/session'

/**
 * The notifications center in the header (specification §4.16): a bell with
 * the unread count, checked every minute, opening the latest notifications;
 * choosing one marks it read and opens what it is about.
 */
interface Item {
  uuid: string
  title: string
  body: string | null
  link: string | null
  read: boolean
  created_at: string
}
const { t, n } = useI18n()
const router = useRouter()
const session = useSession()
const unread = ref(0)
const items = ref<Item[]>([])
const loading = ref(false)
const panel = ref<InstanceType<typeof Popover> | null>(null)
let timer: ReturnType<typeof setInterval> | null = null

async function count(): Promise<void> {
  try {
    unread.value = (await get<{ data: { unread: number } }>('/me/notifications/count')).data.unread
  } catch {
    // A missed check is retried a minute later.
  }
}
async function open(e: Event): Promise<void> {
  panel.value?.toggle(e)
  loading.value = true
  try {
    const res = await get<{ data: Item[]; meta: { unread: number } }>('/me/notifications')
    items.value = res.data
    unread.value = res.meta.unread
  } finally {
    loading.value = false
  }
}
async function choose(item: Item): Promise<void> {
  if (!item.read) {
    unread.value = (await send<{ data: { unread: number } }>('post', `/me/notifications/${item.uuid}/read`)).data.unread
    item.read = true
  }
  panel.value?.hide()
  if (item.link && item.link.startsWith('/')) void router.push(item.link)
}
async function readAll(): Promise<void> {
  await send('post', '/me/notifications/read-all')
  unread.value = 0
  items.value.forEach((item) => (item.read = true))
}
const when = (iso: string) => new Date(iso).toLocaleString(session.locale, { dateStyle: 'medium', timeStyle: 'short' })

onMounted(() => {
  void count()
  timer = setInterval(count, 60000)
})
onBeforeUnmount(() => timer && clearInterval(timer))
</script>

<template>
  <span class="relative inline-flex">
    <Button icon="pi pi-bell" text rounded :aria-label="unread ? t('inbox.open_unread', { n: n(unread) }) : t('inbox.open')" aria-haspopup="true" data-testid="notifications-bell" @click="open" />
    <Badge v-if="unread" :value="unread > 99 ? `${n(99)}+` : n(unread)" severity="danger" class="bell-badge" data-testid="notifications-unread" />
  </span>
  <Popover ref="panel">
    <div class="w-[22rem] max-w-[90vw] flex flex-col gap-2" data-testid="notifications-panel">
      <div class="flex items-center justify-between gap-2">
        <h2 class="m-0 text-base font-semibold">{{ t('inbox.title') }}</h2>
        <Button v-if="unread" :label="t('inbox.read_all')" text size="small" @click="readAll" />
      </div>
      <EmptyState v-if="!loading && !items.length" icon="pi pi-bell" :title="t('inbox.empty')" :description="t('inbox.empty_desc')" />
      <ul class="flex flex-col max-h-[24rem] overflow-auto -mx-2">
        <li v-for="item in items" :key="item.uuid">
          <button type="button" class="inbox-item" :class="{ 'is-unread': !item.read }" @click="choose(item)">
            <span class="font-medium text-sm" dir="auto">{{ item.title }}</span>
            <span v-if="item.body" class="text-xs text-muted-color" dir="auto">{{ item.body }}</span>
            <span class="text-xs text-faint">{{ when(item.created_at) }}</span>
          </button>
        </li>
      </ul>
    </div>
  </Popover>
</template>

<style scoped>
.bell-badge {
  position: absolute;
  inset-block-start: 2px;
  inset-inline-end: 0;
  pointer-events: none;
}
.inbox-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  text-align: start;
  background: transparent;
  cursor: pointer;
}
.inbox-item:hover {
  background: var(--bg-subtle);
}
.inbox-item.is-unread {
  background: var(--primary-subtle);
}
</style>
