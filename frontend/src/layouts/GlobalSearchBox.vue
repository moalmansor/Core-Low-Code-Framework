<script setup lang="ts">
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Popover from 'primevue/popover'
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { searchApi, type SearchHit } from '@/views/experience/api'

/**
 * Global search in the top bar (specification §4.29): records of the forms an
 * administrator made searchable, found only where the person may see them.
 */
const { t } = useI18n()
const router = useRouter()
const term = ref('')
const hits = ref<SearchHit[]>([])
const searching = ref(false)
const searched = ref(false)
const panel = ref<InstanceType<typeof Popover> | null>(null)
const box = ref<HTMLElement | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined
let ticket = 0

watch(term, (v) => {
  clearTimeout(timer)
  const q = v.trim()
  if (q.length < 2) {
    hits.value = []
    searched.value = false
    panel.value?.hide()
    return
  }
  timer = setTimeout(() => void run(q), 300)
})
onBeforeUnmount(() => clearTimeout(timer))

async function run(q: string): Promise<void> {
  const mine = ++ticket
  searching.value = true
  try {
    const found = await searchApi.search(q)
    if (mine !== ticket) return
    hits.value = found
  } catch {
    if (mine === ticket) hits.value = []
  } finally {
    if (mine === ticket) {
      searching.value = false
      searched.value = true
      if (box.value) panel.value?.show({ currentTarget: box.value } as unknown as Event, box.value)
    }
  }
}
function open(hit: SearchHit): void {
  panel.value?.hide()
  term.value = ''
  void router.push({ name: 'records.view', params: { form: hit.form.uuid, record: hit.record.uuid } })
}
</script>

<template>
  <div ref="box" class="min-w-0 flex-1 max-w-md">
    <IconField>
      <InputIcon :class="searching ? 'pi pi-spin pi-spinner' : 'pi pi-search'" />
      <InputText v-model="term" type="search" :placeholder="t('xp.search_placeholder')" :aria-label="t('xp.search_label')" class="w-full" size="small" data-testid="global-search" />
    </IconField>
    <Popover ref="panel">
      <div class="search-results" data-testid="global-search-results">
        <p v-if="searched && !hits.length" class="m-0 text-sm text-muted-color">{{ t('xp.search_none') }}</p>
        <button v-for="h in hits" :key="`${h.form.uuid}-${h.record.uuid}`" type="button" class="search-hit" @click="open(h)">
          <span class="text-sm font-medium truncate" dir="auto">{{ h.record.title ?? h.form.name }}</span>
          <span class="text-xs text-muted-color truncate" dir="auto"
            >{{ h.form.name }}<template v-if="h.record.subtitle"> · {{ h.record.subtitle }}</template></span
          >
        </button>
      </div>
    </Popover>
  </div>
</template>

<style scoped>
.search-results {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  width: min(28rem, 85vw);
  max-height: 60vh;
  overflow-y: auto;
}
.search-hit {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.125rem;
  padding: 0.5rem 0.75rem;
  border: 0;
  border-radius: var(--radius-control);
  background: none;
  color: inherit;
  text-align: start;
  cursor: pointer;
  min-width: 0;
}
.search-hit:hover,
.search-hit:focus-visible {
  background: var(--bg-subtle);
}
</style>
