<script setup lang="ts">
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { helpFor, type HelpContextType } from '@/runtime/help'
import { startTour, tours } from '@/runtime/tours'
import SafeHtml from '@/runtime/SafeHtml'
import { useSession } from '@/stores/session'

/**
 * The Help side panel (specification §4.37): the help an administrator wrote
 * for what is on screen — a form, a page, an application or an admin area.
 * The button shows only where there is help to read or a tour to take.
 */
const props = defineProps<{ context: { type: HelpContextType; target: string } | null }>()
const { t, locale } = useI18n()
const session = useSession()
const open = ref(false)
const help = computed(() => (props.context ? helpFor(props.context.type, props.context.target, locale.value) : undefined))
const entries = computed(() => help.value?.panel ?? [])
function take(uuid: string): void {
  open.value = false
  startTour(uuid)
}
watch(
  () => props.context,
  () => (open.value = false),
)
</script>

<template>
  <Button v-if="entries.length || tours.here.length" icon="pi pi-question-circle" :label="t('help.button')" text class="max-sm:!px-2" data-testid="help-button" data-tour="help" @click="open = true" />
  <Drawer v-model:visible="open" :position="session.direction === 'rtl' ? 'left' : 'right'" :header="t('help.title')" class="!w-full md:!w-[28rem]">
    <div class="flex flex-col gap-4" data-testid="help-panel">
      <section v-if="tours.here.length" class="flex flex-col gap-2 border-b border-line pb-4">
        <h3 class="m-0 text-sm font-semibold text-muted-color">{{ t('tour.tours') }}</h3>
        <Button
          v-for="tr in tours.here"
          :key="tr.uuid"
          icon="pi pi-directions"
          :label="tr.name ?? t('tour.title')"
          severity="secondary"
          outlined
          size="small"
          class="justify-start"
          data-testid="help-tour"
          @click="take(tr.uuid)"
        />
      </section>
      <article v-for="e in entries" :key="e.uuid" class="flex flex-col gap-2 border-b border-line pb-4 last:border-b-0">
        <h3 v-if="e.title" class="m-0 text-base font-semibold" dir="auto">{{ e.title }}</h3>
        <SafeHtml v-if="e.body" :html="e.body" class="text-sm" />
        <RouterLink v-if="e.display === 'help_page'" :to="{ name: 'help.page', params: { help: e.uuid } }" class="text-sm text-primary inline-flex items-center gap-1" @click="open = false">
          {{ t('help.read_more') }}<i class="pi pi-arrow-right rtl:rotate-180 text-xs" aria-hidden="true" />
        </RouterLink>
      </article>
    </div>
  </Drawer>
</template>
