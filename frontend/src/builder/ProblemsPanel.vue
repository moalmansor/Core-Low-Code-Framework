<script setup lang="ts">
import Button from 'primevue/button'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { builderApi } from './api'
import { pick } from './conditions/scope'
import { findField, findGroup, locateIssues } from './document'
import { issueArea, issueText } from './issues'
import { useBuilder } from './useBuilder'

/**
 * Everything the server reported on the draft: errors stop it from being
 * saved, problems stop preview and publishing. Selecting an entry selects
 * the element it concerns.
 */
const { t, te } = useI18n()
const builder = useBuilder()
const checking = ref(false)
const failure = ref<string | null>(null)
const all = computed(() => [...builder.errors, ...builder.problems])

function elementName(uuid: string | null): string {
  if (!uuid || !builder.doc) return t('builder.problems.form')
  const f = findField(builder.doc, uuid)
  if (f) return `${pick(f.i18n?.label, builder.locale, f.key)} (${f.key})`
  const g = findGroup(builder.doc, uuid)
  if (g) return `${pick(g.i18n?.title, builder.locale, g.key)} (${g.key})`
  return uuid
}

async function validateNow(): Promise<void> {
  if (!builder.doc) return
  checking.value = true
  failure.value = null
  try {
    builder.commit()
    const sent = JSON.parse(JSON.stringify(builder.doc))
    const result = await builderApi.validateDraft(builder.formUuid, sent)
    builder.errors = locateIssues(sent, result.errors, 'error')
    builder.problems = locateIssues(sent, result.problems, 'problem')
  } catch (e) {
    failure.value = e instanceof ApiError ? e.message : String(e)
  } finally {
    checking.value = false
  }
}
</script>

<template>
  <section class="flex flex-col min-h-0 h-full" :aria-label="t('builder.problems.title')" data-testid="problems-panel">
    <header class="flex items-center gap-2 px-3 py-1.5 border-b border-line">
      <h2 class="text-sm font-semibold flex-1">
        {{ t('builder.problems.title') }}
        <span class="text-xs font-normal text-muted-color">{{ t('builder.problems.counts', { errors: builder.errors.length, problems: builder.problems.length }) }}</span>
      </h2>
      <Button size="small" text icon="pi pi-check-circle" :label="t('builder.problems.check')" :loading="checking" @click="validateNow" />
    </header>
    <p v-if="failure" class="px-3 py-1 text-xs text-danger">{{ failure }}</p>
    <p v-if="!all.length" class="px-3 py-2 text-sm text-muted-color">{{ t('builder.problems.none') }}</p>
    <ul v-else class="flex-1 overflow-auto divide-y divide-line">
      <li v-for="(i, n) in all" :key="n">
        <button type="button" class="w-full text-start px-3 py-1.5 flex items-start gap-2 text-sm hover:bg-subtle" @click="builder.select(i.uuid)">
          <i :class="i.severity === 'error' ? 'pi pi-times-circle text-danger mt-0.5' : 'pi pi-exclamation-triangle text-warning mt-0.5'" aria-hidden="true" />
          <span class="flex-1 min-w-0">
            <span class="font-medium">{{ elementName(i.uuid) }}</span>
            <span class="text-xs text-muted-color ms-1">{{ i.severity === 'error' ? t('builder.problems.blocks_save') : t('builder.problems.blocks_publish') }}</span>
            <span class="block">{{ issueText(t, te, i) }}</span>
          </span>
          <span v-if="issueArea(t, i.property)" class="text-xs text-muted-color shrink-0">{{ issueArea(t, i.property) }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>
