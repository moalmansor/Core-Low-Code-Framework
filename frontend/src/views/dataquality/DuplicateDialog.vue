<script setup lang="ts">
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { useI18n } from 'vue-i18n'
import { recordName, type DuplicateAnswer } from './api'

/**
 * A save that looks like a duplicate (specification §4.35): the likely
 * duplicates the person may see, each opening in a new tab, and — when no
 * rule blocks it — "Save anyway".
 */
defineProps<{ answer: DuplicateAnswer; form: string; busy?: boolean }>()
const emit = defineEmits<{ confirm: []; cancel: [] }>()
const { t } = useI18n()
</script>

<template>
  <Dialog
    :visible="true"
    modal
    :header="answer.blocking ? t('dq.dup_blocked_title') : t('dq.dup_title')"
    :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }"
    data-testid="dup-dialog"
    @update:visible="(v: boolean) => !v && emit('cancel')"
  >
    <div class="flex flex-col gap-3">
      <Message :severity="answer.blocking ? 'error' : 'warn'" :closable="false">
        <span dir="auto">{{ answer.message }}</span>
      </Message>
      <p v-if="answer.rules.length" class="m-0 text-sm text-muted-color">{{ t('dq.dup_rules', { rules: answer.rules.join(t('dq.list_sep')) }) }}</p>
      <ul v-if="answer.duplicates.length" class="m-0 ps-5 flex flex-col gap-1 text-sm">
        <li v-for="d in answer.duplicates" :key="d.uuid">
          <RouterLink :to="{ name: 'records.view', params: { form, record: d.uuid } }" target="_blank" class="text-primary" dir="auto">{{ recordName(d, t('dq.a_record')) }}</RouterLink>
        </li>
      </ul>
      <p v-if="answer.hidden" class="m-0 text-xs text-muted-color">{{ t('dq.hidden_records', { n: answer.hidden }) }}</p>
    </div>
    <template #footer>
      <Button :label="answer.blocking ? t('dq.dup_back') : t('common.cancel')" severity="secondary" text data-testid="dup-cancel" @click="emit('cancel')" />
      <Button v-if="!answer.blocking" :label="t('dq.save_anyway')" severity="warn" :loading="busy" data-testid="dup-confirm" @click="emit('confirm')" />
    </template>
  </Dialog>
</template>
