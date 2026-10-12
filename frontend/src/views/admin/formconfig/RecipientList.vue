<script setup lang="ts">
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SubjectPicker from '@/components/SubjectPicker.vue'
import type { Placeholder } from '../notifications/api'
import type { RecipientDoc, RecipientType } from './api'

/**
 * One list of recipients (To, CC or BCC) of a notification rule or an
 * action's message: users, roles, departments, a user field, users of linked
 * records, the creator, outside addresses — and, for actions, the person
 * running it.
 */
const props = defineProps<{
  types: RecipientType[]
  userFields: { value: string; label: string }[]
  linkedUsers: Placeholder[]
  errors: Record<string, string>
  /** Prefix of the server's error paths for this list. */
  path: string
  testid: string
}>()
const items = defineModel<RecipientDoc[]>({ required: true })
const { t } = useI18n()
const options = computed(() =>
  props.types.filter((v) => (v !== 'field_user' || props.userFields.length) && (v !== 'linked_record_users' || props.linkedUsers.length)).map((v) => ({ value: v, label: t(`notif.recipient.${v}`) })),
)
const subjectOf = (item: RecipientDoc) => ({ type: item.type, uuid: item.ref })
</script>

<template>
  <div class="flex flex-col gap-2" :data-testid="testid">
    <p v-if="errors[path]" class="m-0 text-xs text-danger">{{ errors[path] }}</p>
    <div v-for="(item, j) in items" :key="j" class="flex flex-wrap items-start gap-2">
      <Select
        v-model="item.type"
        :options="options"
        option-label="label"
        option-value="value"
        size="small"
        class="w-field-sm"
        :aria-label="t('notif.recipient_type')"
        @update:model-value="item.ref = null"
      />
      <div v-if="['user', 'role', 'department'].includes(item.type)" class="w-field-md">
        <SubjectPicker :model-value="subjectOf(item) as never" :types="[item.type as 'user']" @update:model-value="(v: { uuid: string | null } | null) => (item.ref = v?.uuid ?? null)" />
      </div>
      <Select
        v-else-if="item.type === 'field_user'"
        v-model="item.ref"
        :options="userFields"
        option-label="label"
        option-value="value"
        size="small"
        class="w-field-md"
        :aria-label="t('notif.recipient.field_user')"
      />
      <Select
        v-else-if="item.type === 'linked_record_users'"
        v-model="item.ref"
        :options="linkedUsers"
        option-label="label"
        option-value="token"
        size="small"
        class="w-field-md"
        :aria-label="t('notif.recipient.linked_record_users')"
      />
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
      <p v-if="errors[`${path}.${j}`]" class="basis-full m-0 text-xs text-danger">{{ errors[`${path}.${j}`] }}</p>
    </div>
    <Button icon="pi pi-plus" :label="t('notif.add_recipient')" size="small" text class="self-start" :data-testid="`${testid}-add`" @click="items.push({ type: 'role', ref: null })" />
  </div>
</template>
