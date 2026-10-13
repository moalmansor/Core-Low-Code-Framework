<script setup lang="ts">
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'
import ConfigField from '@/components/config/ConfigField.vue'
import ConfigItem from '@/components/config/ConfigItem.vue'
import ConfigSaveBar from '@/components/config/ConfigSaveBar.vue'
import ConfigSection from '@/components/config/ConfigSection.vue'
import EmptyState from '@/components/config/EmptyState.vue'
import SettingSwitch from '@/components/config/SettingSwitch.vue'
import TabIntro from '@/components/config/TabIntro.vue'
import { formatDatetime } from '@/runtime/format'
import { newUuid } from '@/views/admin/formconfig/api'
import ErrorList from '@/views/admin/formconfig/ErrorList.vue'
import { useHashedDocument } from '@/views/admin/formconfig/useHashedDocument'
import { opsApi, type AlertRuleDoc, type Metric } from './api'

/**
 * Threshold alerts (specification §4.2): when a measure reaches its
 * threshold, the members of the chosen roles get an e-mail, at most once per
 * quiet period. Event measures count over the period; the others are read as
 * they are now.
 */
const { t, n, locale } = useI18n()
const doc = useHashedDocument<AlertRuleDoc[]>(
  async () => {
    const r = await opsApi.alertRules()
    return { value: r.rules, hash: r.hash, extra: r as unknown as Record<string, unknown> }
  },
  async (v, h) => {
    const r = await opsApi.saveAlertRules(v, h)
    return { value: r.rules, hash: r.hash, extra: r as unknown as Record<string, unknown> }
  },
)
const metrics = computed(() => ((doc.extra.value.metrics ?? []) as Metric[]).map((m) => ({ value: m, label: t(`ops.metric.${m}`) })))
const instant = computed(() => (doc.extra.value.instant ?? []) as Metric[])
const values = computed(() => (doc.extra.value.values ?? {}) as Record<string, number>)
const roles = ref<{ uuid: string; name: string }[]>([])
onMounted(async () => {
  // Role names for everyone who may set alerts, not only those who manage permissions.
  roles.value = (await get<{ data: { uuid: string; name: string }[] }>('/subject-options', { type: 'role' }).catch(() => ({ data: [] }))).data
})
function add(): void {
  doc.value.value?.push({ uuid: newUuid(), metric: 'failed_submissions', threshold: 5, window: 60, roles: [], cooldown: 60, active: true })
}
const title = (r: AlertRuleDoc) => t('ops.rule_title', { measure: t(`ops.metric.${r.metric}`), threshold: n(r.threshold) })
const err = (path: string) => doc.errors.value[path]
</script>

<template>
  <div class="flex flex-col gap-6" data-testid="ops-alerts">
    <TabIntro :title="t('ops.tab.alerts')" :text="t('ops.alerts_hint')" />
    <div v-if="doc.value.value" class="rounded-xl border border-line bg-card px-5">
      <ConfigSection id="ops-alert-rules" :title="t('ops.alert_rules')" :count="doc.value.value.length">
        <EmptyState v-if="!doc.value.value.length" icon="pi pi-bell" :title="t('ops.no_alerts_title')" :description="t('ops.no_alerts_text')" testid="ops-alert-empty">
          <Button icon="pi pi-plus" :label="t('ops.add_alert')" size="small" outlined data-testid="ops-alert-add" @click="add" />
        </EmptyState>
        <template v-else>
          <ConfigItem
            v-for="(r, i) in doc.value.value"
            :key="r.uuid"
            :title="title(r)"
            :subtitle="r.last_triggered_at ? t('ops.last_alerted', { at: formatDatetime(r.last_triggered_at, locale) }) : undefined"
            :index="i"
            :count="doc.value.value.length"
            :invalid="Object.keys(doc.errors.value).some((k) => k.startsWith(`rules.${i}.`))"
            :testid="`ops-alert-${i}`"
            @remove="doc.value.value!.splice(i, 1)"
          >
            <template #badges>
              <Tag v-if="values[r.uuid] !== undefined" :severity="values[r.uuid] >= r.threshold ? 'danger' : 'success'" :value="t('ops.now_value', { n: n(values[r.uuid]) })" />
              <Tag v-if="!r.active" severity="secondary" :value="t('ops.inactive')" />
            </template>
            <div class="cfg-row">
              <ConfigField :label="t('ops.measure')" :for="`ops-m-${i}`" width="md" :error="err(`rules.${i}.metric`)">
                <Select v-model="r.metric" :input-id="`ops-m-${i}`" :options="metrics" option-label="label" option-value="value" size="small" />
              </ConfigField>
              <ConfigField :label="t('ops.threshold')" :for="`ops-t-${i}`" width="xs" :error="err(`rules.${i}.threshold`)">
                <InputNumber v-model="r.threshold" :input-id="`ops-t-${i}`" :min="1" :max="r.metric === 'storage_usage' ? 100 : 1000000" size="small" />
              </ConfigField>
              <ConfigField v-if="!instant.includes(r.metric)" :label="t('ops.window')" :for="`ops-w-${i}`" width="xs" :hint="t('ops.minutes')" :error="err(`rules.${i}.window`)">
                <InputNumber v-model="r.window" :input-id="`ops-w-${i}`" :min="5" :max="10080" size="small" />
              </ConfigField>
              <ConfigField :label="t('ops.cooldown')" :for="`ops-c-${i}`" width="xs" :hint="t('ops.minutes')" :error="err(`rules.${i}.cooldown`)">
                <InputNumber v-model="r.cooldown" :input-id="`ops-c-${i}`" :min="5" :max="10080" size="small" />
              </ConfigField>
            </div>
            <ConfigField :label="t('ops.alert_roles')" :for="`ops-r-${i}`" width="lg" :error="err(`rules.${i}.roles`)">
              <MultiSelect
                v-model="r.roles"
                :input-id="`ops-r-${i}`"
                :options="roles"
                option-label="name"
                option-value="uuid"
                display="chip"
                filter
                size="small"
                :data-testid="`ops-alert-roles-${i}`"
              />
            </ConfigField>
            <SettingSwitch :id="`ops-active-${i}`" v-model="r.active" :label="t('ops.active')" />
          </ConfigItem>
          <Button icon="pi pi-plus" :label="t('ops.add_alert')" size="small" outlined class="self-start" data-testid="ops-alert-add" @click="add" />
        </template>
      </ConfigSection>
    </div>
    <ErrorList :errors="doc.errors.value" />
    <ConfigSaveBar :dirty="doc.dirty.value" :saving="doc.saving.value" testid="ops-alert" @save="doc.save()" @discard="doc.discard()" />
  </div>
</template>
