import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/http'
import { reportDirty } from '@/components/config/configScreen'
import { errorText } from '../building/shared'

/**
 * Load/edit/save of a configuration document saved whole with its
 * concurrency hash: tracks unsaved changes, shows inline validation errors,
 * and on a 409 reloads the newer version instead of overwriting it.
 */
let documents = 0

export function useHashedDocument<T>(
  loader: () => Promise<{ value: T; hash: string; extra: Record<string, unknown> }>,
  saver: (value: T, hash: string) => Promise<{ value: T; hash: string; extra: Record<string, unknown> }>,
) {
  const { t } = useI18n()
  const toast = useToast()
  const value = ref<T | null>(null) as Ref<T | null>
  const extra = ref<Record<string, unknown>>({})
  const hash = ref('')
  const saved = ref('')
  const saving = ref(false)
  const loading = ref(true)
  const errors = ref<Record<string, string>>({})
  const dirty = computed(() => value.value !== null && JSON.stringify(value.value) !== saved.value)

  function apply(r: { value: T; hash: string; extra: Record<string, unknown> }): void {
    value.value = JSON.parse(JSON.stringify(r.value)) as T
    hash.value = r.hash
    extra.value = r.extra
    saved.value = JSON.stringify(value.value)
  }

  async function load(): Promise<void> {
    loading.value = true
    try {
      apply(await loader())
    } catch (e) {
      toast.add({ severity: 'error', summary: errorText(e, t('workflow.load_failed')), life: 6000 })
    } finally {
      loading.value = false
    }
  }

  async function save(): Promise<boolean> {
    if (value.value === null) return false
    saving.value = true
    errors.value = {}
    try {
      apply(await saver(value.value, hash.value))
      toast.add({ severity: 'success', summary: t('workflow.saved'), life: 3000 })
      return true
    } catch (e) {
      if (e instanceof ApiError && e.status === 409) {
        toast.add({ severity: 'warn', summary: t('workflow.conflict'), life: 8000 })
        await load()
      } else {
        errors.value = e instanceof ApiError ? e.fieldErrors : {}
        toast.add({ severity: 'error', summary: errorText(e, t('workflow.save_failed')), life: 6000 })
      }
      return false
    } finally {
      saving.value = false
    }
  }

  /** Returns to the last saved version. */
  function discard(): void {
    if (saved.value) value.value = JSON.parse(saved.value) as T
    errors.value = {}
  }

  reportDirty(`doc-${++documents}`, () => dirty.value)
  onMounted(load)
  return { value, extra, hash, saving, loading, errors, dirty, load, save, discard }
}
