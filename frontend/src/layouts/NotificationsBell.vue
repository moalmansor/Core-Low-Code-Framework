<script setup lang="ts">
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { get, send } from '@/api/http'
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
const { t } = useI18n()
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
async function choose(n: Item): Promise<void> {
  if (!n.read) {
    unread.value = (await send<{ data: { unread: number } }>('post', `/me/notifications/${n.uuid}/read`)).data.unread
    n.read = true
  }
  panel.value?.hide()
  if (n.link && n.link.startsWith('/')) void router.push(n.link)
}
async function readAll(): Promise<void> {
  await send('post', '/me/notifications/read-all')
  unread.value = 0
  items.value.forEach((n) => (n.read = true))
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
    <Button icon="pi pi-bell" text rounded :aria-label="unread ? t('inbox.open_unread', { n: unread }) : t('inbox.open')" aria-haspopup="true" data-testid="notifications-bell" @click="open" />
    <Badge v-if="unread" :value="unread > 99 ? '99+' : unread" severity="danger" class="bell-badge" data-testid="notifications-unread" />
  </span>
  <Popover ref="panel">
    <div class="w-[22rem] max-w-[90vw] flex flex-col gap-2" data-testid="notifications-panel">
      <div class="flex items-center justify-between gap-2">
        <h2 class="m-0 text-base font-semibold">{{ t('inbox.title') }}</h2>
        <Button v-if="unread" :label="t('inbox.read_all')" text size="small" @click="readAll" />
      </div>
      <p v-if="!loading && !items.length" class="m-0 py-6 text-center text-sm text-muted-color">{{ t('inbox.empty') }}</p>
      <ul class="flex flex-col max-h-[24rem] overflow-auto -mx-2">
        <li v-for="n in items" :key="n.uuid">
          <button type="button" class="inbox-item" :class="{ 'is-unread': !n.read }" @click="choose(n)">
            <span class="font-medium text-sm" dir="auto">{{ n.title }}</span>
            <span v-if="n.body" class="text-xs text-muted-color" dir="auto">{{ n.body }}</span>
            <span class="text-xs text-faint">{{ when(n.created_at) }}</span>
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
