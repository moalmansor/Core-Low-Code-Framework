<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ApiError, get, send } from '@/api/http'
import SubjectPicker from '@/components/SubjectPicker.vue'
import { useSession } from '@/stores/session'
import { newUuid, uploadFile } from '../api'
import { formatDatetime } from '../format'
import JustificationDialog from '../JustificationDialog.vue'
import { useJustification } from '../useJustification'
import StatusBadge from './StatusBadge.vue'
import type { TransitionOption, WorkflowState } from './types'

/**
 * The workflow of a record on its page (specification §4.12, §4.25): the
 * status, the transitions the user may take (with comment, attachments,
 * confirmation and justification as the transition asks), the pending
 * approval and its decisions, the assignment, the claim and the SLA.
 */
const props = defineProps<{ form: string; record: string; rowVersion: number; fieldLabel: (key: string) => string; missing: (keys: string[]) => string[]; canEdit?: boolean }>()
const emit = defineEmits<{ changed: []; enabled: [on: boolean]; fill: [keys: string[], transition: string] }>()
const { t, locale } = useI18n()
const toast = useToast()
const session = useSession()
const state = ref<WorkflowState | null>(null)
watch(
  () => !!state.value?.enabled,
  (on) => emit('enabled', on),
  { immediate: true },
)
const justification = useJustification()

async function load(): Promise<void> {
  try {
    state.value = (await get<{ data: WorkflowState }>(`/r/${props.form}/${props.record}/workflow`)).data
  } catch {
    state.value = null
  }
}
watch(() => [props.form, props.record, props.rowVersion], load, { immediate: true })
defineExpose({ load })

const when = (iso: string | null) => (iso ? formatDatetime(iso, locale.value) : '—')
const claimedByMe = computed(() => state.value?.claim?.by.uuid === session.me?.uuid)
const queueItem = computed(() => state.value?.assignments.some((a) => a.mine && a.assignee.type !== 'user') ?? false)
const sla = computed(() => state.value?.sla[0] ?? null)

function fail(e: unknown): void {
  if (!(e instanceof ApiError)) return
  // Field errors name the fields by their labels, never by key.
  const fields = Object.keys(e.fieldErrors).filter((k) => !['transition', 'comment', 'attachments'].includes(k))
  const summary = fields.length ? `${t('workflow_run.fill_first')} ${fields.map(props.fieldLabel).join(', ')}` : (Object.values(e.fieldErrors)[0] ?? e.message)
  toast.add({ severity: 'error', summary, life: 8000 })
  if (e.status === 409) emit('changed')
}

// ── Transitions ──
const moving = ref<TransitionOption | null>(null)
const comment = ref('')
const files = ref<{ uuid: string; name: string }[]>([])
const uploading = ref(false)
const performing = ref(false)
// Required fields still empty in the record as shown (labels come from the server, in the user's language).
const missingFields = computed(() => (moving.value ? moving.value.required_fields.filter((f) => props.missing([f.key]).length > 0) : []))
function fillIn(): void {
  const m = moving.value
  if (!m) return
  const keys = missingFields.value.map((f) => f.key)
  moving.value = null
  emit('fill', keys, m.name)
}
const canSubmit = computed(() => {
  const m = moving.value
  if (!m || missingFields.value.length) return false
  if (m.comment === 'mandatory' && !comment.value.trim()) return false
  if (m.attachments === 'mandatory' && !files.value.length) return false
  return true
})
function start(tr: TransitionOption): void {
  moving.value = tr
  comment.value = ''
  files.value = []
}
async function onFiles(e: Event): Promise<void> {
  const input = e.target as HTMLInputElement
  const list = [...(input.files ?? [])]
  input.value = ''
  uploading.value = true
  try {
    for (const f of list) {
      const meta = await uploadFile(f, f.name)
      files.value.push({ uuid: meta.uuid, name: meta.name })
    }
  } catch (err) {
    fail(err)
  } finally {
    uploading.value = false
  }
}
async function perform(): Promise<void> {
  const m = moving.value
  if (!m) return
  performing.value = true
  const key = newUuid()
  try {
    const done = await justification.run((j) =>
      send(
        'post',
        `/r/${props.form}/${props.record}/transitions/${m.uuid}`,
        {
          row_version: props.rowVersion,
          comment: comment.value.trim() || null,
          attachments: files.value.map((f) => f.uuid),
          ...(j ? { justification: j } : {}),
        },
        { headers: { 'Idempotency-Key': key } },
      ),
    )
    if (done === null) return
    moving.value = null
    toast.add({ severity: 'success', summary: m.approval ? t('workflow_run.sent_for_approval') : t('workflow_run.moved', { status: m.to?.name ?? '' }), life: 4000 })
    emit('changed')
  } catch (e) {
    fail(e)
  } finally {
    performing.value = false
  }
}

// ── Approval ──
const deciding = ref<'approved' | 'rejected' | null>(null)
const decisionComment = ref('')
async function decide(): Promise<void> {
  const a = state.value?.approval
  if (!a || !deciding.value) return
  try {
    await send('post', `/approvals/${a.uuid}/decide`, { decision: deciding.value, comment: decisionComment.value.trim() || null })
    deciding.value = null
    decisionComment.value = ''
    toast.add({ severity: 'success', summary: t('workflow_run.decided'), life: 4000 })
    emit('changed')
  } catch (e) {
    fail(e)
  }
}

// ── Assignment and claim ──
const assigning = ref(false)
const assignee = ref<{ type: string; uuid: string | null } | null>(null)
async function assign(): Promise<void> {
  if (!assignee.value?.uuid) return
  try {
    const done = await justification.run((j) => send('post', `/r/${props.form}/${props.record}/assign`, { assignee: assignee.value, ...(j ? { justification: j } : {}) }))
    if (done === null) return
    assigning.value = false
    assignee.value = null
    toast.add({ severity: 'success', summary: t('workflow_run.assigned'), life: 4000 })
    await load()
  } catch (e) {
    fail(e)
  }
}
async function claim(release: boolean): Promise<void> {
  try {
    await send('post', `/r/${props.form}/${props.record}/${release ? 'release' : 'claim'}`)
    await load()
  } catch (e) {
    fail(e)
  }
}
</script>

<template>
  <section v-if="state?.enabled" class="flex flex-col gap-3" data-testid="workflow-panel">
    <!-- One aligned bar (design system §5.6): facts as labelled columns at the start, every action in one row at the end. -->
    <div class="wf-bar">
      <dl class="wf-facts">
        <div class="wf-fact">
          <dt>{{ t('workflow_run.status') }}</dt>
          <dd>
            <StatusBadge :status="state.status" />
            <Tag
              v-if="sla"
              :severity="sla.state === 'breached' ? 'danger' : sla.state === 'warned' ? 'warn' : 'secondary'"
              :value="t(`workflow_run.sla.${sla.state}`, { at: when(sla.due_at) })"
              data-testid="sla-badge"
            />
          </dd>
        </div>
        <div class="wf-fact">
          <dt>{{ t('workflow_run.assigned_to') }}</dt>
          <dd>
            <span v-if="!state.assignments.length" class="lcf-empty">{{ t('workflow_run.unassigned') }}</span>
            <span v-for="a in state.assignments" :key="a.uuid" class="inline-flex items-center gap-1">
              <i :class="a.assignee.type === 'user' ? 'pi pi-user' : 'pi pi-users'" class="text-muted-color" aria-hidden="true" />{{ a.assignee.name }}
              <span v-if="a.due_at" class="text-sm font-normal text-muted-color">({{ t('workflow_run.due', { at: when(a.due_at) }) }})</span>
            </span>
            <span v-if="state.claim" class="inline-flex items-center gap-1 text-sm font-normal text-muted-color"
              ><i class="pi pi-lock" aria-hidden="true" />{{ t('workflow_run.claimed_by', { name: state.claim.by.name ?? '' }) }}</span
            >
          </dd>
        </div>
      </dl>
      <div class="wf-actions">
        <Button
          v-for="tr in state.transitions"
          :key="tr.uuid"
          :icon="tr.style?.icon ?? 'pi pi-arrow-right rtl:rotate-180'"
          :label="tr.name"
          size="small"
          :style="tr.style?.color ? { backgroundColor: tr.style.color, borderColor: tr.style.color } : undefined"
          :data-testid="`transition-${tr.key}`"
          @click="start(tr)"
        />
        <span v-if="state.transitions.length && (queueItem || claimedByMe || state.can.assign || state.can.reassign)" class="wf-sep" aria-hidden="true" />
        <Button v-if="queueItem && !state.claim" icon="pi pi-lock" :label="t('workflow_run.claim')" size="small" severity="secondary" outlined data-testid="claim" @click="claim(false)" />
        <Button v-if="claimedByMe" icon="pi pi-lock-open" :label="t('workflow_run.release')" size="small" severity="secondary" outlined data-testid="release" @click="claim(true)" />
        <Button
          v-if="state.assignments.length ? state.can.reassign : state.can.assign"
          icon="pi pi-user-edit"
          :label="state.assignments.length ? t('workflow_run.reassign') : t('workflow_run.assign')"
          size="small"
          severity="secondary"
          outlined
          data-testid="assign"
          @click="assigning = true"
        />
      </div>
    </div>
    <p v-if="state.transitions.some((x) => x.on_behalf_of)" class="m-0 text-xs text-muted-color">{{ t('workflow_run.delegated_hint') }}</p>

    <div v-if="state.approval" class="rounded-lg bg-primary-subtle p-3 flex flex-col gap-2" data-testid="approval-card">
      <div class="flex flex-wrap items-center gap-2">
        <span class="font-medium">{{ t(`workflow_run.approval_status.${state.approval.status}`) }}</span>
        <span class="text-sm text-muted-color">{{ t(`workflow.approval.${state.approval.mode}`) }}</span>
        <span v-if="state.approval.due_at" class="text-sm text-muted-color">· {{ t('workflow_run.due', { at: when(state.approval.due_at) }) }}</span>
        <span class="flex-1" />
        <template v-if="state.approval.can_decide">
          <Button icon="pi pi-check" :label="t('workflow_run.approve')" size="small" severity="success" data-testid="approve" @click="deciding = 'approved'" />
          <Button icon="pi pi-times" :label="t('workflow_run.reject')" size="small" severity="danger" outlined data-testid="reject" @click="deciding = 'rejected'" />
        </template>
      </div>
      <ul class="text-sm flex flex-col gap-1">
        <li v-for="(d, i) in state.approval.decisions" :key="i" class="flex flex-wrap gap-2">
          <span class="font-medium">{{ d.approver.name }}</span>
          <Tag :severity="d.decision === 'approved' ? 'success' : d.decision === 'rejected' ? 'danger' : 'secondary'" :value="t(`workflow_run.decision.${d.decision}`)" />
          <span v-if="d.decided_by" class="text-muted-color"
            >{{ d.decided_by }}<template v-if="d.on_behalf"> ({{ t('workflow_run.on_behalf') }})</template> · {{ when(d.decided_at) }}</span
          >
          <span v-if="d.comment" class="w-full ps-4" dir="auto">{{ d.comment }}</span>
        </li>
      </ul>
    </div>

    <Dialog :visible="!!moving" modal :header="moving?.name" class="w-full max-w-lg" @update:visible="(v) => !v && (moving = null)">
      <form v-if="moving" class="flex flex-col gap-3" data-testid="transition-dialog" @submit.prevent="perform">
        <p class="flex items-center gap-2 text-sm">{{ t('workflow_run.moves_to') }} <StatusBadge :status="moving.to" size="sm" /></p>
        <Message v-if="moving.approval" severity="info" size="small" :closable="false">{{ t('workflow_run.needs_approval') }}</Message>
        <Message v-if="missingFields.length" severity="warn" :closable="false" data-testid="transition-missing">
          <p class="m-0">{{ t('workflow_run.fill_first') }}</p>
          <ul class="m-0 mt-1 ps-5 list-disc">
            <li v-for="f in missingFields" :key="f.key" dir="auto">{{ f.label }}</li>
          </ul>
          <Button v-if="canEdit" type="button" icon="pi pi-pencil" :label="t('workflow_run.fill_in')" size="small" class="mt-2" data-testid="transition-fill" @click="fillIn" />
        </Message>
        <Message v-if="moving.on_behalf_of" severity="secondary" size="small" :closable="false">{{ t('workflow_run.acting_for', { name: moving.on_behalf_of }) }}</Message>
        <div v-if="moving.comment !== 'none'" class="field">
          <label for="tr-comment">{{ t('workflow.comment') }}<span v-if="moving.comment === 'mandatory'" class="text-danger ms-1" aria-hidden="true">*</span></label>
          <Textarea id="tr-comment" v-model="comment" rows="3" auto-resize maxlength="5000" data-testid="transition-comment" />
        </div>
        <div v-if="moving.attachments !== 'none'" class="field">
          <label for="tr-files">{{ t('workflow.attachments') }}<span v-if="moving.attachments === 'mandatory'" class="text-danger ms-1" aria-hidden="true">*</span></label>
          <input id="tr-files" type="file" multiple :disabled="uploading" @change="onFiles" />
          <ul v-if="files.length" class="text-sm">
            <li v-for="(f, i) in files" :key="f.uuid" class="flex items-center gap-2">
              <i class="pi pi-paperclip" /><span class="flex-1 truncate" dir="auto">{{ f.name }}</span>
              <Button icon="pi pi-times" text size="small" :aria-label="t('common.delete')" @click="files.splice(i, 1)" />
            </li>
          </ul>
        </div>
        <p v-if="moving.confirmation" class="text-sm font-medium">{{ t('workflow_run.confirm', { name: moving.name }) }}</p>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('common.cancel')" text @click="moving = null" />
          <Button type="submit" :label="moving.name" icon="pi pi-check" :disabled="!canSubmit || uploading" :loading="performing" data-testid="transition-perform" />
        </div>
      </form>
    </Dialog>

    <Dialog
      :visible="!!deciding"
      modal
      :header="deciding === 'approved' ? t('workflow_run.approve') : t('workflow_run.reject')"
      class="w-full max-w-md"
      @update:visible="(v) => !v && (deciding = null)"
    >
      <form class="flex flex-col gap-3" @submit.prevent="decide">
        <div class="field">
          <label for="dc-comment">{{ t('workflow.comment') }}<span v-if="deciding === 'rejected'" class="text-danger ms-1" aria-hidden="true">*</span></label>
          <Textarea id="dc-comment" v-model="decisionComment" rows="3" auto-resize maxlength="5000" data-testid="decision-comment" />
        </div>
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('common.cancel')" text @click="deciding = null" />
          <Button type="submit" :label="t('workflow_run.submit_decision')" :disabled="deciding === 'rejected' && !decisionComment.trim()" data-testid="decision-submit" />
        </div>
      </form>
    </Dialog>

    <Dialog :visible="assigning" modal :header="state.assignments.length ? t('workflow_run.reassign') : t('workflow_run.assign')" class="w-full max-w-md" @update:visible="(v) => (assigning = v)">
      <form class="flex flex-col gap-3" @submit.prevent="assign">
        <SubjectPicker v-model="assignee" :types="['user', 'role', 'department']" />
        <div class="flex justify-end gap-2">
          <Button type="button" :label="t('common.cancel')" text @click="assigning = false" />
          <Button type="submit" :label="t('workflow_run.assign')" :disabled="!assignee?.uuid" data-testid="assign-submit" />
        </div>
      </form>
    </Dialog>

    <JustificationDialog :prompt="justification.prompt.value" :errors="justification.errors.value" :busy="justification.busy.value" @submit="justification.submit" @cancel="justification.cancel" />
  </section>
</template>

<style scoped>
.wf-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem 2rem;
  padding: 1rem 1.25rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-card);
  background: var(--bg-surface);
}
.wf-facts {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 2.5rem;
  margin: 0;
}
.wf-fact dt {
  font-size: var(--text-size-xs);
  color: var(--text-muted);
}
.wf-fact dd {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  min-height: 1.75rem;
  margin: 0.25rem 0 0;
  font-weight: 500;
}
.wf-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.wf-sep {
  width: 1px;
  align-self: stretch;
  margin-inline: 0.25rem;
  background: var(--border);
}
</style>
