<script setup lang="ts">
import Message from 'primevue/message'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { helpPage } from '@/runtime/help'
import SafeHtml from '@/runtime/SafeHtml'
import { errorText } from '../admin/building/shared'

/** A help page an administrator wrote (specification §4.37); the server shows it only to those who may open what it is about. */
const { t } = useI18n()
const route = useRoute()
const page = ref<{ title: string | null; body: string | null } | null>(null)
const failed = ref<string | null>(null)

watch(
  () => route.params.help,
  async (uuid) => {
    page.value = null
    failed.value = null
    try {
      page.value = await helpPage(String(uuid))
      if (page.value.title) document.title = page.value.title
    } catch (e) {
      failed.value = errorText(e, t('help.unavailable'))
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="flex flex-col gap-4" data-testid="help-page">
    <Message v-if="failed" severity="warn" :closable="false">{{ failed }}</Message>
    <template v-else-if="page">
      <header class="page-head">
        <h1 class="page-title !mb-0" dir="auto">{{ page.title ?? t('help.title') }}</h1>
      </header>
      <article class="max-w-[60rem] rounded-xl border border-line bg-card p-6">
        <SafeHtml :html="page.body ?? ''" />
      </article>
    </template>
  </div>
</template>
