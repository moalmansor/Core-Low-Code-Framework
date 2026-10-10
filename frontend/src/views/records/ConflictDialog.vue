<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { conflictRows, defaultChoices, type Choice, type ConflictPayload } from '@/runtime/conflict'
import { FormIndex } from '@/runtime/formIndex'
import { formatDatetime, formatLoose, formatValue } from '@/runtime/format'
import { labelOf, pickText, humanize } from '@/runtime/i18nText'
import type { ClientDefinition, References, Values } from '@/runtime/types'

/**
 * The conflict screen (specification §4.9): someone saved this record after
 * it was opened. It shows which fields changed, the value they had, the other
 * user's value and the user's own, and who changed them when. The user
 * reloads (discarding their changes), keeps the other value or overwrites
 * field by field, or cancels and keeps editing.
 */
const props = defineProps<{ payload: ConflictPayload; submitted: Values; definition: ClientDefinition; references: References }>()
const emit = defineEmits<{ reload: []; resolve: [choices: Record<string, Choice>]; cancel: [] }>()
const { t, locale } = useI18n()

const index = computed(() => new FormIndex(props.definition))
const rows = computed(() => conflictRows(props.payload, props.submitted))
const choices = reactive<Record<string, Choice>>(defaultChoices(rows.value))
const conflicting = computed(() => rows.value.filter((r) => r.conflicting))
const others = computed(() => rows.value.filter((r) => !r.conflicting))
const unchangedMine = computed(() => Object.keys(props.submitted).filter((k) => !rows.value.some((r) => r.field === k)))

function label(key: string): string {
  const f = index.value.fieldByKey(key)
  if (f) return labelOf(f.i18n.label, locale.value, key)
  const rep = index.value.repeaterKeys.get(key)
  const g = rep ? index.value.groups.get(rep) : undefined
  return (g && pickText(g.i18n?.title, locale.value)) ?? humanize(key)
}
function show(key: string, value: unknown): string {
  const f = index.value.fieldByKey(key)
  if (index.value.repeaterKeys.has(key)) return Array.isArray(value) ? t('runtime.rows_count', { count: value.length }) : formatLoose(value)
  const text = f
    ? formatValue(index.value, f, value, { locale: locale.value, references: props.references, yes: t('runtime.yes'), no: t('runtime.no'), untitled: t('runtime.untitled_record') })
    : formatLoose(value)
  return text === '' ? '—' : text
}
const who = computed(() => props.payload.changed_by ?? t('records.conflict_someone'))
const when = computed(() => (props.payload.changed_at ? formatDatetime(props.payload.changed_at, locale.value) : ''))
const overwriting = computed(() => Object.values(choices).filter((c) => c === 'mine').length)
</script>

<template>
  <Dialog :visible="true" modal :closable="false" :header="t('records.conflict_title')" :style="{ width: '60rem' }" :breakpoints="{ '960px': '96vw' }" data-testid="conflict-dialog">
    <Message severity="warn" class="mb-4">
      {{ when ? t('records.conflict_intro_when', { name: who, when }) : t('records.conflict_intro', { name: who }) }}
    </Message>

    <template v-if="conflicting.length">
      <h3 class="font-semibold mb-2">{{ t('records.conflict_fields') }}</h3>
      <div class="overflow-x-auto mb-4">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="border-b border-line text-start">
              <th class="p-2 text-start">{{ t('records.conflict_field') }}</th>
              <th class="p-2 text-start">{{ t('records.conflict_base') }}</th>
              <th class="p-2 text-start">{{ t('records.conflict_theirs', { name: who }) }}</th>
              <th class="p-2 text-start">{{ t('records.conflict_mine') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in conflicting" :key="r.field" class="border-b border-line align-top" :data-testid="`conflict-${r.field}`">
              <td class="p-2 font-medium">{{ label(r.field) }}</td>
              <td class="p-2 text-muted-color" dir="auto">{{ show(r.field, r.base) }}</td>
              <td class="p-2">
                <label class="flex items-start gap-2 cursor-pointer">
                  <RadioButton v-model="choices[r.field]" value="theirs" :name="`c-${r.field}`" :input-id="`c-${r.field}-theirs`" :data-testid="`keep-theirs-${r.field}`" />
                  <span dir="auto">{{ show(r.field, r.theirs) }}</span>
                </label>
              </td>
              <td class="p-2">
                <label class="flex items-start gap-2 cursor-pointer">
                  <RadioButton v-model="choices[r.field]" value="mine" :name="`c-${r.field}`" :input-id="`c-${r.field}-mine`" :data-testid="`keep-mine-${r.field}`" />
                  <span dir="auto">{{ show(r.field, r.mine) }}</span>
                </label>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <template v-if="others.length">
      <h3 class="font-semibold mb-2">{{ t('records.conflict_other_changes') }}</h3>
      <ul class="text-sm mb-4 flex flex-col gap-1">
        <li v-for="r in others" :key="r.field">
          <span class="font-medium">{{ label(r.field) }}</span
          >: <span dir="auto">{{ show(r.field, r.base) }}</span> → <span dir="auto">{{ show(r.field, r.theirs) }}</span>
        </li>
      </ul>
    </template>

    <p v-if="unchangedMine.length" class="text-sm text-muted-color">{{ t('records.conflict_mine_kept', { fields: unchangedMine.map(label).join(', ') }) }}</p>
    <p v-if="!conflicting.length && !others.length" class="text-sm text-muted-color">{{ t('records.conflict_no_details') }}</p>

    <template #footer>
      <Button :label="t('records.conflict_cancel')" severity="secondary" text data-testid="conflict-cancel" @click="emit('cancel')" />
      <Button :label="t('records.conflict_reload')" icon="pi pi-refresh" severity="secondary" outlined data-testid="conflict-reload" @click="emit('reload')" />
      <Button
        :label="overwriting ? t('records.conflict_save_choices', { n: overwriting }) : t('records.conflict_save_keep')"
        icon="pi pi-check"
        data-testid="conflict-resolve"
        @click="emit('resolve', { ...choices })"
      />
    </template>
  </Dialog>
</template>
