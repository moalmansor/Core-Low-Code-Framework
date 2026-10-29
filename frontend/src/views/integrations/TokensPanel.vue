<script setup lang="ts">
import Button from 'primevue/button'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmptyState from '@/components/config/EmptyState.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import EmptyValue from '@/components/EmptyValue.vue'
import { errorText, formatDateTime } from '../admin/building/shared'
import { tokensApi, type ApiToken } from './api'

/** Every API token of the organization: who holds it, when it was last used, and revoking it. */
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const rows = ref<ApiToken[]>([])
const loading = ref(true)
async function load(): Promise<void> {
  loading.value = true
  try {
    rows.value = await tokensApi.all()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('intg.load_failed')), life: 6000 })
  } finally {
    loading.value = false
  }
}
onMounted(load)
function revoke(row: ApiToken): void {
  confirm.require({
    header: t('intg.token_revoke_title'),
    message: t('intg.token_revoke_text', { name: row.name }),
    acceptLabel: t('intg.token_revoke'),
    rejectLabel: t('common.cancel'),
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await tokensApi.revoke(row.uuid)
        await load()
      } catch (e) {
        toast.add({ severity: 'error', summary: errorText(e, t('intg.token_revoke_failed')), life: 6000 })
      }
    },
  })
}
const state = (r: ApiToken) => (r.revoked_at ? 'revoked' : r.active ? 'active' : 'expired')
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="tokens-panel">
    <TabIntro :title="t('intg.tab.tokens')" :text="t('intg.tokens_intro')" />
    <EmptyState v-if="!loading && !rows.length" icon="pi pi-key" :title="t('intg.tokens_empty_title')" :description="t('intg.tokens_empty_text')" testid="tokens-empty" />
    <DataTable v-else :value="rows" data-key="uuid" :loading="loading" size="small" class="records-table">
      <Column :header="t('intg.token_name')">
        <template #body="{ data }"
          ><span class="font-medium" dir="auto">{{ data.name }}</span></template
        >
      </Column>
      <Column :header="t('intg.token_owner')">
        <template #body="{ data }"
          ><span dir="auto">{{ data.owner }}</span></template
        >
      </Column>
      <Column :header="t('intg.token_access')">
        <template #body="{ data }">{{ t('intg.token_forms', { n: data.abilities.length }) }}</template>
      </Column>
      <Column :header="t('intg.token_last_used')">
        <template #body="{ data }">
          <span v-if="data.last_used_at">{{ formatDateTime(data.last_used_at, locale) }}</span>
          <EmptyValue v-else :text="t('intg.never_used')" />
        </template>
      </Column>
      <Column :header="t('intg.token_expires')">
        <template #body="{ data }">{{ formatDateTime(data.expires_at, locale) }}</template>
      </Column>
      <Column :header="t('intg.state')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :severity="state(data) === 'active' ? 'success' : 'secondary'" :value="t(`intg.token_state.${state(data)}`)" />
        </template>
      </Column>
      <Column style="width: 4rem">
        <template #body="{ data }">
          <Button v-if="state(data) === 'active'" icon="pi pi-ban" text rounded severity="danger" size="small" :aria-label="t('intg.token_revoke')" @click="revoke(data)" />
        </template>
      </Column>
    </DataTable>
  </div>
</template>
