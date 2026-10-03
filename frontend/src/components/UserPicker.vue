<script setup lang="ts">
import AutoComplete from 'primevue/autocomplete'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { get } from '@/api/http'

interface Option {
  uuid: string
  name: string
  email: string
}
const model = defineModel<Option | null>({ default: null })
const { t } = useI18n()
const suggestions = ref<Option[]>([])

async function search(e: { query: string }): Promise<void> {
  suggestions.value = (await get<{ data: Option[] }>('/users', { search: e.query, per_page: 10 })).data
}
</script>

<template>
  <AutoComplete v-model="model" :suggestions="suggestions" option-label="name" :placeholder="t('access.pick_user')" force-selection @complete="search">
    <template #option="{ option }">
      <div>
        {{ option.name }} <span class="text-sm text-muted-color ltr-value">{{ option.email }}</span>
      </div>
    </template>
  </AutoComplete>
</template>
