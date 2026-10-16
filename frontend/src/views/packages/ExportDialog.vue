<script setup lang="ts">
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ConfigField from '@/components/config/ConfigField.vue'
import { errorText, fieldErrors } from '../admin/building/shared'
import { OBJECT_TYPES, packagesApi, type Catalog, type ObjectType, type PackageDetail } from './api'

/** Choose what goes into a package; what the chosen objects depend on is added on the server. */
const props = defineProps<{ visible: boolean }>()
const emit = defineEmits<{ close: []; exported: [p: PackageDetail] }>()
const { t } = useI18n()
const toast = useToast()
const catalog = ref<Catalog | null>(null)
const chosen = ref<string[]>([])
const name = ref('')
const search = ref('')
const busy = ref(false)
const errors = ref<Record<string, string>>({})

watch(
  () => props.visible,
  async (v) => {
    if (!v) return
    chosen.value = []
    name.value = ''
    errors.value = {}
    try {
      catalog.value = await packagesApi.catalog()
    } catch (e) {
      toast.add({ severity: 'error', summary: errorText(e, t('pkg.load_failed')), life: 6000 })
    }
  },
  { immediate: true },
)
const groups = computed(() =>
  OBJECT_TYPES.map((type) => ({
    type,
    items: (catalog.value?.[type] ?? []).filter((i) => !search.value || `${i.label} ${i.key}`.toLowerCase().includes(search.value.toLowerCase())),
  })).filter((g) => g.items.length),
)
const typeOf = computed(() => {
  const m = new Map<string, ObjectType>()
  for (const type of OBJECT_TYPES) for (const i of catalog.value?.[type] ?? []) m.set(i.uuid, type)
  return m
})
async function submit(): Promise<void> {
  busy.value = true
  errors.value = {}
  try {
    const made = await packagesApi.export(
      name.value.trim(),
      chosen.value.map((uuid) => ({ uuid, type: typeOf.value.get(uuid)! })),
    )
    await packagesApi.download(made.uuid, made.name)
    emit('exported', made)
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) toast.add({ severity: 'error', summary: errorText(e, t('pkg.export_failed')), life: 6000 })
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('pkg.export_title')"
    :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }"
    data-testid="pkg-export-dialog"
    @update:visible="(v) => !v && emit('close')"
  >
    <div class="flex flex-col gap-4">
      <p class="m-0 text-sm text-muted-color">{{ t('pkg.export_intro') }}</p>
      <ConfigField :label="t('pkg.name')" for="pkg-name" width="full" :error="errors.name" required>
        <InputText id="pkg-name" v-model="name" size="small" maxlength="255" dir="auto" data-testid="pkg-name" />
      </ConfigField>
      <ConfigField :label="t('pkg.objects')" for="pkg-search" width="full" :error="errors.objects">
        <InputText id="pkg-search" v-model="search" size="small" :placeholder="t('pkg.search')" />
      </ConfigField>
      <div class="flex flex-col gap-4 max-h-[22rem] overflow-y-auto pe-1">
        <fieldset v-for="g in groups" :key="g.type" class="m-0 p-0 border-0 flex flex-col gap-2">
          <legend class="text-sm font-semibold mb-1">{{ t(`pkg.types.${g.type}`) }}</legend>
          <label v-for="i in g.items" :key="i.uuid" class="flex items-start gap-2 text-sm cursor-pointer">
            <Checkbox v-model="chosen" :value="i.uuid" :input-id="`pkg-pick-${i.uuid}`" :data-testid="`pkg-pick-${g.type}-${i.key}`" :disabled="g.type === 'form' && !!i.note" />
            <span class="flex flex-col">
              <span dir="auto">{{ i.label }}</span>
              <span v-if="i.note" class="text-xs text-muted-color">{{ i.note }}</span>
            </span>
          </label>
        </fieldset>
        <p v-if="catalog && !groups.length" class="m-0 text-sm text-muted-color">{{ t('pkg.nothing_found') }}</p>
      </div>
      <p class="m-0 text-xs text-muted-color">{{ t('pkg.dependencies_hint') }}</p>
    </div>
    <template #footer>
      <Button :label="t('pkg.cancel')" text size="small" @click="emit('close')" />
      <Button icon="pi pi-download" :label="t('pkg.export_download')" size="small" :loading="busy" :disabled="!chosen.length || !name.trim()" data-testid="pkg-export-submit" @click="submit" />
    </template>
  </Dialog>
</template>
