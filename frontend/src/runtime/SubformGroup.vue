<script setup lang="ts">
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRenderer } from './context'
import GroupBody from './GroupBody.vue'
import { pickText } from './i18nText'
import type { ClientGroup } from './types'

/**
 * Inline sub-form of a linked form. The linked form's records live in their
 * own table (foreign key to this record) and are created through that form's
 * own records API, so this group opens the linked form's records.
 */
const props = defineProps<{ group: ClientGroup }>()
const ctx = useRenderer()
const { t } = useI18n()
const title = computed(() => pickText(props.group.i18n?.title, ctx.locale.value) ?? props.group.key)
const description = computed(() => pickText(props.group.i18n?.description, ctx.locale.value))
const target = computed(() => props.group.subform?.form ?? null)
</script>

<template>
  <section class="rounded-lg border border-dashed border-surface-300 dark:border-surface-600 p-4" :data-group="group.key">
    <header class="flex flex-wrap items-center gap-2 mb-2">
      <i class="pi pi-clone" />
      <h3 class="text-base font-semibold flex-1">{{ title }}</h3>
      <RouterLink v-if="target && ctx.formUuid.value && ctx.mode.value !== 'print'" v-slot="{ navigate }" :to="`/app/${target}`" custom>
        <Button type="button" :label="t('runtime.subform_open')" icon="pi pi-external-link" size="small" outlined @click="navigate" />
      </RouterLink>
    </header>
    <p v-if="description" class="text-sm text-muted-color mb-2">{{ description }}</p>
    <p class="text-sm text-muted-color">{{ ctx.mode.value === 'create' ? t('runtime.subform_after_save') : t('runtime.subform_hint') }}</p>
    <GroupBody :group="group" :row="null" />
  </section>
</template>
