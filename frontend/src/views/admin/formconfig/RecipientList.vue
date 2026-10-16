<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import PathPicker from './PathPicker.vue'
import type { Path, PathNode, RecipientDoc, RecipientType } from './api'

/**
 * One list of recipients (To, CC or BCC) of a notification rule, or of an
 * action's or automation's message (specification §4.16): chosen users,
 * roles and departments; people the record holds — a user, role, department
 * or email field of this form or of a form it links to, its assignee, whoever
 * last changed its status, its creator and, for actions, the person running
 * it; and outside addresses. Everything taken from the record is read when
 * the message is sent.
 */
const props = defineProps<{
  types: RecipientType[]
  /** The form's field tree (fieldTreeApi), for fields that name people. */
  tree: PathNode[]
  errors: Record<string, string>
  /** Prefix of the server's error paths for this list. */
  path: string
  testid: string
}>()
const items = defineModel<RecipientDoc[]>({ required: true })
const { t } = useI18n()
const session = useSession()
const dl = computed(() => session.boot?.default_locale ?? 'en')

const GROUPS: { key: 'people' | 'record' | 'outside'; types: RecipientType[] }[] = [
  { key: 'people', types: ['user', 'role', 'department'] },
  { key: 'record', types: ['field', 'assignee', 'status_changer', 'creator', 'actor'] },
  { key: 'outside', types: ['static'] },
]
const options = computed(() =>
  GROUPS.map((g) => ({
    label: t(`notif.recipient_group.${g.key}`),
    items: g.types.filter((v) => props.types.includes(v)).map((v) => ({ value: v, label: t(`notif.recipient.${v}`) })),
  })).filter((g) => g.items.length),
)

// Only fields that can name people, and the links that lead to them.
const peopleTree = computed<PathNode[]>(() => {
  const keep = (nodes: PathNode[]): PathNode[] =>
    nodes.flatMap((n) => {
      const children = keep(n.children)
      return n.kind || children.length ? [{ ...n, children }] : []
    })
  return keep(props.tree)
})
const hasFields = computed(() => peopleTree.value.length > 0)
const asPath = (ref: string | null): Path | null => (ref ? ref.split('.') : null)
const subjectOf = (item: RecipientDoc) => ({ type: item.type, uuid: item.ref })
const hinted = new Set<RecipientType>(['field', 'assignee', 'status_changer'])

function setType(item: RecipientDoc, type: RecipientType): void {
  item.type = type
  item.ref = null
}
</script>

<template>
  <div class="flex flex-col gap-3" :data-testid="testid">
    <p v-if="errors[path]" class="m-0 text-xs text-danger">{{ errors[path] }}</p>
    <p v-if="!items.length" class="m-0 text-sm text-muted-color italic">{{ t('notif.recipients_empty') }}</p>
    <div v-for="(item, j) in items" :key="j" class="flex flex-col gap-1" :data-testid="`${testid}-${j}`">
      <div class="flex flex-wrap items-start gap-2">
        <Select
          :model-value="item.type"
          :options="options"
          option-label="label"
          option-value="value"
          option-group-label="label"
          option-group-children="items"
          size="small"
          class="w-field-md"
          :aria-label="t('notif.recipient_type')"
          :data-testid="`${testid}-${j}-type`"
          @update:model-value="(v: RecipientType) => setType(item, v)"
        />
        <div v-if="['user', 'role', 'department'].includes(item.type)" class="w-field-md">
          <SubjectPicker :model-value="subjectOf(item) as never" :types="[item.type as 'user']" @update:model-value="(v: { uuid: string | null } | null) => (item.ref = v?.uuid ?? null)" />
        </div>
        <div v-else-if="item.type === 'field'" class="w-field-md">
          <PathPicker
            v-if="hasFields"
            :model-value="asPath(item.ref)"
            :tree="peopleTree"
            leaf-only
            :default-locale="dl"
            :input-id="`${testid}-${j}-field`"
            :invalid="!!errors[`${path}.${j}`]"
            @update:model-value="(p: Path | null | undefined) => (item.ref = p?.length ? p.join('.') : null)"
          />
          <p v-else class="m-0 text-sm text-muted-color">{{ t('notif.recipient_field_none') }}</p>
        </div>
        <InputText
          v-else-if="item.type === 'static'"
          v-model="item.ref as string"
          type="email"
          dir="ltr"
          size="small"
          class="w-field-md"
          :placeholder="t('notif.address')"
          :aria-label="t('notif.address')"
        />
        <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('notif.remove_recipient')" @click="items.splice(j, 1)" />
      </div>
      <p v-if="hinted.has(item.type)" class="m-0 text-xs text-muted-color">{{ t(`notif.recipient_hint.${item.type}`) }}</p>
      <p v-if="errors[`${path}.${j}`]" class="m-0 text-xs text-danger">{{ errors[`${path}.${j}`] }}</p>
    </div>
    <Button icon="pi pi-plus" :label="t('notif.add_recipient')" size="small" text class="self-start" :data-testid="`${testid}-add`" @click="items.push({ type: 'role', ref: null })" />
  </div>
</template>
