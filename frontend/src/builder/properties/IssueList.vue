<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { issueText } from '../issues'
import { useBuilder } from '../useBuilder'

/** The save errors and publish problems the server reported for one element (or the form, for null). */
const props = defineProps<{ uuid: string | null }>()
const { t, te } = useI18n()
const builder = useBuilder()
const issues = computed(() => [...builder.errors, ...builder.problems].filter((i) => i.uuid === props.uuid))
</script>

<template>
  <ul v-if="issues.length" class="flex flex-col gap-1" :aria-label="t('builder.problems.title')">
    <li
      v-for="(i, n) in issues"
      :key="n"
      :class="[
        'rounded px-2 py-1 text-xs flex gap-2',
        i.severity === 'error' ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-orange-50 text-orange-800 dark:bg-orange-950 dark:text-orange-200',
      ]"
    >
      <i :class="i.severity === 'error' ? 'pi pi-times-circle' : 'pi pi-exclamation-triangle'" aria-hidden="true" />
      <span class="flex-1">{{ issueText(t, te, i) }}</span>
      <span v-if="i.property" class="ltr-value text-muted-color">{{ i.property }}</span>
    </li>
  </ul>
</template>
