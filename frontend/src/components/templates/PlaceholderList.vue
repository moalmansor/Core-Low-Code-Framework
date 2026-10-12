<script setup lang="ts">
import { useToast } from 'primevue/usetoast'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Placeholders } from '@/views/admin/notifications/api'

/** The placeholders a template may use; clicking one copies it, ready to paste into a text. */
const props = defineProps<{ placeholders: Placeholders; repeaterColumns?: boolean }>()
const { t } = useI18n()
const toast = useToast()
const tokenText = (token: string) => `{{ ${token} }}`
const all = computed(() => [
  ...props.placeholders.system,
  ...props.placeholders.fields,
  ...(props.repeaterColumns ? props.placeholders.repeaters.flatMap((r) => r.columns.map((c) => ({ token: `${r.key}.${c.key}`, label: `${r.label} › ${c.label}` }))) : []),
])
async function copy(token: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(tokenText(token))
    toast.add({ severity: 'info', summary: t('tpl.copied'), life: 2500 })
  } catch {
    toast.add({ severity: 'warn', summary: t('tpl.copy_manually', { text: tokenText(token) }), life: 6000 })
  }
}
</script>

<template>
  <ul class="tpl-tokens">
    <li v-for="p in all" :key="p.token">
      <button type="button" class="tpl-token" :title="t('tpl.copy')" @click="copy(p.token)">
        <span>{{ p.label }}</span
        ><code class="ltr-value">{{ tokenText(p.token) }}</code>
      </button>
    </li>
  </ul>
</template>

<style scoped>
.tpl-tokens {
  display: grid;
  gap: 0.25rem;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
}
.tpl-token {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 100%;
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg-surface);
  text-align: start;
  font-size: 0.8125rem;
  cursor: pointer;
}
.tpl-token:hover {
  border-color: var(--primary);
}
.tpl-token code {
  color: var(--text-muted);
  font-size: 0.75rem;
}
</style>
