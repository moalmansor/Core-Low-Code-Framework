<script setup lang="ts">
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { defineAsyncComponent, onMounted, provide, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get } from '@/api/http'
import { useSession } from '@/stores/session'
import { RECORD_FILES } from './context'
import type { ClientDefinition, FileMeta, RecordPayload } from './types'

/** A referenced record opened in a side drawer (view mode), with a link to its full page. */
const props = defineProps<{ form: string; record: string }>()
const emit = defineEmits<{ close: [] }>()
const FormRenderer = defineAsyncComponent(() => import('./FormRenderer.vue'))
const { t } = useI18n()
const session = useSession()
const visible = ref(true)
const definition = ref<ClientDefinition | null>(null)
const payload = ref<RecordPayload | null>(null)
const error = ref('')
const files = ref<Record<string, FileMeta>>({})
provide(RECORD_FILES, files)

onMounted(async () => {
  try {
    const [d, r] = await Promise.all([get<{ data: ClientDefinition }>(`/r/${props.form}/definition`, { mode: 'view' }), get<{ data: RecordPayload }>(`/r/${props.form}/${props.record}`)])
    definition.value = d.data
    payload.value = r.data
    files.value = r.data.files ?? {}
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : t('runtime.load_failed')
  }
})

function close(): void {
  visible.value = false
  emit('close')
}
</script>

<template>
  <Drawer
    :visible="visible"
    :position="session.direction === 'rtl' ? 'left' : 'right'"
    :header="payload?.title ?? t('runtime.referenced_record')"
    class="!w-full md:!w-[36rem]"
    @update:visible="(v: boolean) => !v && close()"
  >
    <Message v-if="error" severity="error">{{ error }}</Message>
    <div v-else-if="!definition || !payload" class="flex justify-center p-6"><ProgressSpinner style="width: 2rem; height: 2rem" /></div>
    <template v-else>
      <RouterLink v-slot="{ navigate }" :to="`/app/${form}/${record}`" custom>
        <Button :label="t('runtime.open_full_record')" icon="pi pi-external-link" size="small" outlined class="mb-4" @click="navigate" />
      </RouterLink>
      <FormRenderer :definition="definition" :model-value="payload.values" mode="view" :form-uuid="form" :references="payload.references" />
    </template>
  </Drawer>
</template>
