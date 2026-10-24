<script setup lang="ts">
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { errorText, formatDateTime } from '../admin/building/shared'
import { devicesApi, type TrustedDevice } from '../access/api'

/**
 * Devices trusted for this account (specification §4.36, ADR-0060): where
 * an access policy asks for trusted devices, a device becomes trusted when
 * the second factor is confirmed on it. Removing one makes it ask again.
 */
const { t, locale } = useI18n()
const toast = useToast()
const devices = ref<TrustedDevice[]>([])

async function load(): Promise<void> {
  devices.value = await devicesApi.list()
}
onMounted(load)
async function revoke(d: TrustedDevice): Promise<void> {
  try {
    await devicesApi.revoke(d.id)
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: errorText(e, t('pa.save_failed')), life: 6000 })
  }
}
</script>

<template>
  <section v-if="devices.length" class="flex flex-col gap-2 mt-6" data-testid="trusted-devices">
    <h2 class="text-base font-semibold m-0">{{ t('pa.my_devices') }}</h2>
    <p class="m-0 text-sm text-muted-color">{{ t('pa.my_devices_hint') }}</p>
    <ul class="list-none m-0 p-0 flex flex-col gap-2">
      <li v-for="d in devices" :key="d.id" class="flex flex-wrap items-center gap-3 border border-surface rounded-lg p-2">
        <i class="pi pi-desktop text-muted-color" aria-hidden="true" />
        <div class="flex-1 min-w-40">
          <div class="font-medium">{{ d.label ?? t('pa.device') }}</div>
          <div class="text-xs text-muted-color">{{ t('pa.trusted_until', { at: formatDateTime(d.expires_at, locale) }) }}</div>
        </div>
        <Tag v-if="d.current" :value="t('profile.this_device')" severity="success" />
        <Button size="small" severity="danger" text :label="t('pa.forget_device')" @click="revoke(d)" />
      </li>
    </ul>
  </section>
</template>
