<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DiffEntry, VersionDiff } from './api'
import { printExpression } from './conditions/print'
import { pick } from './conditions/scope'
import { useSession } from '@/stores/session'

/**
 * A readable rendering of a structured version diff (architecture §13.3):
 * per element, added / removed / changed, with each changed property's
 * value before and after.
 */
const props = defineProps<{ diff: VersionDiff }>()
const { t } = useI18n()
const session = useSession()

const sections = computed(() => (['groups', 'fields', 'relations', 'conditions'] as const).map((kind) => ({ kind, entries: props.diff[kind] ?? [] })).filter((s) => s.entries.length))

function title(kind: string, e: DiffEntry): string {
  const obj = e.after ?? e.before ?? {}
  const i18n = (obj.i18n ?? {}) as Record<string, Record<string, string>>
  const name = kind === 'fields' ? pick(i18n.label, session.locale) : kind === 'groups' ? pick(i18n.title, session.locale) : kind === 'conditions' ? String(obj.name ?? '') : ''
  return [name, e.key ? `(${e.key})` : ''].filter(Boolean).join(' ') || e.uuid
}

function show(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'object' && value !== null && 'k' in value) {
    try {
      return printExpression(value as Record<string, unknown>)
    } catch {
      /* fall through to JSON */
    }
  }
  if (typeof value === 'string') return value
  const json = JSON.stringify(value)
  return json.length > 300 ? `${json.slice(0, 300)}…` : json
}
</script>

<template>
  <div class="flex flex-col gap-3 text-sm" data-testid="diff-view">
    <p class="text-muted-color">{{ t('builder.diff.summary', { added: diff.summary.added, removed: diff.summary.removed, changed: diff.summary.changed }) }}</p>
    <section v-if="diff.form.length">
      <h4 class="font-semibold mb-1">{{ t('builder.diff.form') }}</h4>
      <table class="w-full text-xs">
        <tbody>
          <tr v-for="c in diff.form" :key="c.path" class="border-t border-surface-200 dark:border-surface-700 align-top">
            <td class="py-1 pe-2 ltr-value font-mono w-1/4">{{ c.path }}</td>
            <td class="py-1 pe-2 text-red-700 dark:text-red-300 break-all">
              <span class="ltr-value">{{ show(c.before) }}</span>
            </td>
            <td class="py-1 text-green-700 dark:text-green-300 break-all">
              <span class="ltr-value">{{ show(c.after) }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </section>
    <section v-for="s in sections" :key="s.kind">
      <h4 class="font-semibold mb-1">{{ t(`builder.diff.${s.kind}`) }}</h4>
      <ul class="flex flex-col gap-1">
        <li
          v-for="e in s.entries"
          :key="e.uuid"
          :class="[
            'rounded border p-2',
            e.change === 'added'
              ? 'border-green-300 bg-green-50 dark:bg-green-950'
              : e.change === 'removed'
                ? 'border-red-300 bg-red-50 dark:bg-red-950'
                : 'border-surface-200 dark:border-surface-700',
          ]"
        >
          <div class="flex items-center gap-2">
            <span :class="['text-xs font-semibold uppercase', e.change === 'added' ? 'text-green-700' : e.change === 'removed' ? 'text-red-700' : 'text-primary']">{{
              t(`builder.diff.${e.change}`)
            }}</span>
            <span>{{ title(s.kind, e) }}</span>
          </div>
          <table v-if="e.changes?.length" class="w-full text-xs mt-1">
            <thead>
              <tr class="text-muted-color">
                <th class="text-start font-normal">{{ t('builder.diff.property') }}</th>
                <th class="text-start font-normal">{{ t('builder.diff.before') }}</th>
                <th class="text-start font-normal">{{ t('builder.diff.after') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in e.changes" :key="c.path" class="border-t border-surface-200 dark:border-surface-700 align-top">
                <td class="py-1 pe-2 ltr-value font-mono w-1/4">{{ c.path }}</td>
                <td class="py-1 pe-2 text-red-700 dark:text-red-300 break-all">
                  <span class="ltr-value">{{ show(c.before) }}</span>
                </td>
                <td class="py-1 text-green-700 dark:text-green-300 break-all">
                  <span class="ltr-value">{{ show(c.after) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </li>
      </ul>
    </section>
    <p v-if="diff.access.added || diff.access.removed || diff.access.changed" class="text-muted-color">{{ t('builder.diff.access', diff.access) }}</p>
    <p v-if="!sections.length && !diff.form.length" class="text-muted-color">{{ t('builder.diff.none') }}</p>
  </div>
</template>
