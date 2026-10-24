<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import FormPicker from '@/builder/FormPicker.vue'
import { errorText, fieldErrors } from './admin/building/shared'
import { personalApi, type Pin, type Shortcut } from './access/api'

/**
 * The person's own corner of the home page (specification §4.36, ADR-0063):
 * the records they pinned and the shortcuts they made, for what they may
 * still open.
 */
const { t } = useI18n()
const toast = useToast()
const pins = ref<Pin[]>([])
const shortcuts = ref<Shortcut[]>([])
const editing = ref<Shortcut[] | null>(null)
const errors = ref<Record<string, string>>({})
const actions = computed(() => (['list', 'new'] as const).map((v) => ({ v, l: t(`personal.shortcut_action.${v}`) })))

async function load(): Promise<void> {
  try {
    ;[pins.value, shortcuts.value] = await Promise.all([personalApi.pins(), personalApi.shortcuts()])
  } catch {
    // The home page still works without them.
  }
}
onMounted(load)
async function unpin(p: Pin): Promise<void> {
  await personalApi.unpin(p.record)
  pins.value = pins.value.filter((x) => x.record !== p.record)
}
function edit(): void {
  errors.value = {}
  editing.value = shortcuts.value.map((s) => ({ form: s.form, action: s.action, label: s.label }))
}
async function save(): Promise<void> {
  errors.value = {}
  try {
    shortcuts.value = await personalApi.saveShortcuts((editing.value ?? []).filter((s) => s.form))
    editing.value = null
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.add({ severity: 'error', summary: errorText(e, t('personal.save_failed')), life: 6000 })
  }
}
const shortcutTo = (s: Shortcut) => (s.action === 'new' ? `/app/${s.form}/new` : `/app/${s.form}`)
</script>

<template>
  <div class="grid gap-4 md:grid-cols-2" data-testid="personal-corner">
    <section class="flex flex-col gap-2">
      <h2 class="text-base font-semibold m-0">{{ t('personal.pins_title') }}</h2>
      <p v-if="!pins.length" class="m-0 text-sm text-muted-color">{{ t('personal.pins_empty') }}</p>
      <ul v-else class="list-none m-0 p-0 flex flex-col gap-1" data-testid="pins">
        <li v-for="p in pins" :key="p.record" class="flex items-center gap-2">
          <RouterLink :to="`/app/${p.form}/${p.record}`" class="flex-1 min-w-0 text-link truncate" dir="auto">{{ p.title }}</RouterLink>
          <span class="text-xs text-muted-color" dir="auto">{{ p.form_name }}</span>
          <Button icon="pi pi-times" text rounded size="small" severity="secondary" :aria-label="t('personal.unpin')" @click="unpin(p)" />
        </li>
      </ul>
    </section>
    <section class="flex flex-col gap-2">
      <div class="flex items-center gap-2">
        <h2 class="text-base font-semibold m-0 flex-1">{{ t('personal.shortcuts_title') }}</h2>
        <Button icon="pi pi-pencil" :label="t('personal.edit_shortcuts')" text size="small" data-testid="shortcuts-edit" @click="edit" />
      </div>
      <p v-if="!shortcuts.length" class="m-0 text-sm text-muted-color">{{ t('personal.shortcuts_empty') }}</p>
      <div v-else class="flex flex-wrap gap-2" data-testid="shortcuts">
        <RouterLink v-for="(s, i) in shortcuts" :key="i" :to="shortcutTo(s)" class="no-underline">
          <Button :icon="s.action === 'new' ? 'pi pi-plus' : 'pi pi-list'" :label="s.label || s.form_name" severity="secondary" outlined size="small" />
        </RouterLink>
      </div>
    </section>
  </div>

  <Dialog :visible="!!editing" modal :header="t('personal.edit_shortcuts')" class="w-[40rem] max-w-[95vw]" @update:visible="(v: boolean) => !v && (editing = null)">
    <form v-if="editing" class="flex flex-col gap-3" @submit.prevent="save">
      <div v-for="(s, i) in editing" :key="i" class="flex flex-wrap items-start gap-2">
        <div class="flex flex-col gap-1 flex-1 min-w-48">
          <FormPicker v-model="s.form" :input-id="`sc-form-${i}`" />
          <small v-if="errors[`shortcuts.${i}.form`]" class="text-danger">{{ errors[`shortcuts.${i}.form`] }}</small>
        </div>
        <Select v-model="s.action" :options="actions" option-label="l" option-value="v" size="small" class="w-36" :aria-label="t('personal.shortcut_action_label')" />
        <InputText v-model="s.label" :placeholder="t('personal.shortcut_label')" size="small" class="w-40" :aria-label="t('personal.shortcut_label')" />
        <Button type="button" icon="pi pi-trash" text severity="danger" :aria-label="t('personal.remove')" @click="editing.splice(i, 1)" />
      </div>
      <Button
        v-if="editing.length < 20"
        type="button"
        icon="pi pi-plus"
        :label="t('personal.add_shortcut')"
        text
        size="small"
        class="self-start"
        @click="editing.push({ form: '', action: 'list', label: null })"
      />
      <div class="flex justify-end gap-2">
        <Button type="button" severity="secondary" :label="t('personal.cancel')" @click="editing = null" />
        <Button type="submit" :label="t('personal.save')" data-testid="shortcuts-save" />
      </div>
    </form>
  </Dialog>
</template>
