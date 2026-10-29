<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import EmptyState from '@/components/config/EmptyState.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { errorText } from '../admin/building/shared'
import { homeApi, type HomeScreenSummary } from './api'

/** Home screens (specification §4.29): who gets which landing page. */
const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const screens = ref<HomeScreenSummary[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    screens.value = await homeApi.list()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('xp.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
})
const open = (screen: string) => router.push({ name: 'admin.home_screens.screen', params: { screen } })
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="home-screens-admin">
    <header class="page-head">
      <div class="flex-1 min-w-0">
        <h1 class="page-title !mb-1">{{ t('admin.area.home_screens') }}</h1>
        <p class="m-0 text-sm text-muted-color max-w-[var(--measure)]">{{ t('xp.home_subtitle') }}</p>
      </div>
      <Button icon="pi pi-plus" :label="t('xp.new_home')" size="small" data-testid="home-new" @click="open('new')" />
    </header>
    <EmptyState v-if="!loading && !screens.length" icon="pi pi-home" :title="t('xp.home_empty_title')" :description="t('xp.home_empty_text')" testid="home-screens-empty">
      <Button icon="pi pi-plus" :label="t('xp.new_home')" size="small" @click="open('new')" />
    </EmptyState>
    <DataTable v-else :value="screens" data-key="uuid" :loading="loading" size="small" class="records-table" row-hover @row-click="(e) => open((e.data as HomeScreenSummary).uuid)">
      <Column :header="t('xp.name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.label }}</span></template
        >
      </Column>
      <Column :header="t('xp.for_whom')">
        <template #body="{ data }"
          >{{ t(`xp.target.${data.target_type}`)
          }}<template v-if="data.target_name"
            >: <span dir="auto">{{ data.target_name }}</span></template
          ></template
        >
      </Column>
      <Column :header="t('xp.where')">
        <template #body="{ data }">
          <span v-if="data.application_name" dir="auto">{{ data.application_name }}</span>
          <span v-else>{{ t('xp.main_home') }}</span>
        </template>
      </Column>
      <Column :header="t('xp.priority')">
        <template #body="{ data }">{{ data.priority }}</template>
      </Column>
      <Column :header="t('xp.widgets')">
        <template #body="{ data }"
          ><template v-if="data.widgets">{{ data.widgets }}</template
          ><EmptyValue v-else
        /></template>
      </Column>
      <Column :header="t('xp.state')">
        <template #body="{ data }"><Tag :severity="data.active ? 'success' : 'secondary'" :value="data.active ? t('xp.active') : t('xp.inactive')" /></template>
      </Column>
    </DataTable>
  </div>
</template>
