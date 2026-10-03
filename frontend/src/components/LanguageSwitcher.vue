<script setup lang="ts">
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSession } from '@/stores/session'

const session = useSession()
const { t } = useI18n()
const options = computed(() => (session.boot?.locales ?? []).map((l) => ({ label: l.native_name, value: l.code })))
</script>

<template>
  <Select
    v-if="options.length > 1"
    :model-value="session.locale"
    :options="options"
    option-label="label"
    option-value="value"
    :aria-label="t('shell.language')"
    size="small"
    data-testid="language-switcher"
    @update:model-value="(v: string) => session.switchLocale(v)"
  />
</template>
