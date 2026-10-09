<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { issueArea, issueText } from '../issues'
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
      :class="['rounded px-2 py-1 text-xs flex gap-2', i.severity === 'error' ? 'bg-danger-subtle text-color border-s-4 border-danger' : 'bg-warning-subtle text-color border-s-4 border-warning']"
    >
      <i :class="i.severity === 'error' ? 'pi pi-times-circle text-danger' : 'pi pi-exclamation-triangle text-warning'" aria-hidden="true" />
      <span class="flex-1">{{ issueText(t, te, i) }}</span>
      <span v-if="issueArea(t, i.property)" class="text-muted-color">{{ issueArea(t, i.property) }}</span>
    </li>
  </ul>
</template>
