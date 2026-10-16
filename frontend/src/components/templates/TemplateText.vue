<script setup lang="ts">
import { ref } from 'vue'
import I18nInput from '@/builder/I18nInput.vue'
import type { PathNode } from '@/views/admin/formconfig/api'
import type { Placeholder } from '@/views/admin/notifications/api'
import { token, type Format } from './formats'
import PlaceholderPicker from './PlaceholderPicker.vue'

/**
 * A template text per language with a value picker beside its label: the
 * chosen field or system value goes in at the caret as its placeholder, so
 * nobody types `{{ … }}` by hand (specification §4.17).
 */
withDefaults(
  defineProps<{
    label: string
    form?: string | null
    tree?: PathNode[] | null
    system?: Placeholder[]
    multiline?: boolean
    rows?: number
    maxlength?: number
    invalid?: boolean
    testid?: string
  }>(),
  { form: null, tree: null, system: () => [], rows: 3, maxlength: 1000, testid: undefined },
)
const model = defineModel<Record<string, string> | undefined>()
const input = ref<InstanceType<typeof I18nInput> | null>(null)
const add = (path: string, f: Format) => input.value?.insert(token(path, f))
</script>

<template>
  <I18nInput ref="input" v-model="model" :label="label" :multiline="multiline" :rows="rows" :maxlength="maxlength" :invalid="invalid" :data-testid="testid">
    <template #tools>
      <PlaceholderPicker :form="form" :tree="tree" :system="system" :testid="testid ? `${testid}-pick` : undefined" @choose="add" />
    </template>
  </I18nInput>
</template>
