<script setup lang="ts">
import Button from 'primevue/button'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { errorText } from '@/views/admin/building/shared'
import { joinList } from '@/runtime/lists'
import { downloadsApi, type ProfileSummary } from './api'

/**
 * Download profiles of a form (specification §4.23): the shared ones a
 * profile manager maintains (the form configuration's "Downloads" tab), or a
 * user's own personal ones (from the records table). Opens the builder.
 */
const props = defineProps<{ form?: string; personal?: boolean }>()
const route = useRoute()
const router = useRouter()
const { t, n, locale } = useI18n()
const toast = useToast()
const form = computed(() => props.form ?? String(route.params.form))
const personal = computed(() => props.personal ?? route.meta.personalDownloads === true)
const profiles = ref<ProfileSummary[] | null>(null)
const published = ref(true)
const loadError = ref('')

async function load(): Promise<void> {
  try {
    const r = await downloadsApi.list(form.value)
    profiles.value = r.profiles.filter((p) => (personal.value ? p.personal && p.mine : !p.personal))
    published.value = r.published
  } catch (e) {
    loadError.value = errorText(e, t('dl.load_failed'))
  }
}
onMounted(load)

const open = (uuid: string) =>
  router.push(personal.value ? { name: 'records.download_profile', params: { form: form.value, profile: uuid } } : { name: 'admin.downloads.profile', params: { form: form.value, profile: uuid } })
async function duplicate(p: ProfileSummary): Promise<void> {
  try {
    const r = await downloadsApi.duplicate(p.uuid)
    toast.add({ severity: 'success', summary: t('dl.duplicated'), life: 3000 })
    await open(r.profile.uuid!)
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.save_failed')), life: 6000 })
  }
}
async function remove(p: ProfileSummary): Promise<void> {
  if (!window.confirm(t('dl.delete_confirm', { name: p.name }))) return
  try {
    await downloadsApi.remove(p.uuid)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('dl.save_failed')), life: 6000 })
  }
}
const subtitle = (p: ProfileSummary) =>
  [
    joinList(
      p.formats.map((f) => t(`dl.format.${f}`)),
      locale.value,
    ),
    t('dl.n_columns', { n: n(p.columns) }),
    p.schedules ? t('dl.n_schedules', { n: n(p.schedules) }) : null,
  ]
    .filter(Boolean)
    .join(' · ')
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="download-profiles">
    <template v-if="personal">
      <header>
        <RouterLink :to="{ name: 'records.list', params: { form } }" class="text-sm text-primary inline-flex items-center gap-1 mb-1">
          <i class="pi pi-arrow-left rtl:rotate-180" aria-hidden="true" />{{ t('dl.back_to_records') }}
        </RouterLink>
        <h1 class="page-title !mb-1">{{ t('dl.my_profiles') }}</h1>
        <p class="m-0 text-sm text-muted-color">{{ t('dl.my_profiles_hint') }}</p>
      </header>
    </template>
    <TabIntro v-else :title="t('formconfig.tab.downloads')" :text="t('dl.hint')" />
    <Message v-if="loadError" severity="error">{{ loadError }}</Message>
    <EmptyState v-else-if="!published" icon="pi pi-lock" :title="t('dl.unpublished_title')" :description="t('dl.publish_first')" testid="dl-unpublished" />
    <template v-else-if="profiles">
      <EmptyState v-if="!profiles.length" icon="pi pi-download" :title="t('dl.empty_title')" :description="t('dl.empty_text')" testid="dl-empty" />
      <ul v-else class="m-0 p-0 list-none flex flex-col gap-2">
        <li v-for="p in profiles" :key="p.uuid" class="dl-row" :data-testid="`dl-profile-${p.uuid}`">
          <button type="button" class="dl-open" @click="open(p.uuid)">
            <span class="font-medium" dir="auto">{{ p.name }}</span>
            <span class="text-xs text-muted-color">{{ subtitle(p) }}</span>
          </button>
          <Tag v-if="!p.active" severity="secondary" :value="t('dl.inactive')" />
          <Button icon="pi pi-copy" text rounded size="small" severity="secondary" :aria-label="t('dl.duplicate')" @click="duplicate(p)" />
          <Button icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('dl.delete')" @click="remove(p)" />
        </li>
      </ul>
      <Button icon="pi pi-plus" :label="t('dl.new')" size="small" outlined class="self-start" data-testid="dl-new" @click="open('new')" />
    </template>
  </div>
</template>

<style scoped>
.dl-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  background: var(--bg-surface);
}
.dl-open {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  text-align: start;
  color: inherit;
}
</style>
