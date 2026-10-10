<script setup lang="ts">
import Message from 'primevue/message'
import { useI18n } from 'vue-i18n'

/**
 * Server validation messages of a configuration document. Each message says
 * where it applies in words ("Transition 2 › Column 1"), never as the
 * document's internal path (design system §5.6).
 */
const props = defineProps<{ errors: Record<string, string>; name?: (collection: string, index: number) => string | null }>()
const { t, te } = useI18n()

function where(path: string): string {
  const parts = path.split('.')
  const out: string[] = []
  for (let i = 0; i < parts.length - 1; i++) {
    const n = Number(parts[i + 1])
    if (!Number.isInteger(n) || !te(`formconfig.where.${parts[i]}`)) continue
    out.push(props.name?.(parts[i]!, n) ?? t(`formconfig.where.${parts[i]}`, { n: n + 1 }))
  }
  return out.join(' › ')
}
</script>

<template>
  <Message v-for="(m, k) in errors" :key="k" severity="error" :closable="false" class="text-sm" data-testid="config-error">
    <template v-if="where(String(k))">
      <span class="font-medium" dir="auto">{{ where(String(k)) }}</span
      >:
    </template>
    {{ m }}
  </Message>
</template>
