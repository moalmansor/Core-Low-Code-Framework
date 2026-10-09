<script setup lang="ts">
import { computed } from 'vue'
import BulkProperties from './properties/BulkProperties.vue'
import FieldProperties from './properties/FieldProperties.vue'
import FormProperties from './properties/FormProperties.vue'
import GroupProperties from './properties/GroupProperties.vue'
import { refOf } from './document'
import { useBuilder } from './useBuilder'

/** The right-hand panel: the selected element, the selection, or the form itself. */
const builder = useBuilder()
const selected = computed(() => (builder.doc && builder.selection.length === 1 ? refOf(builder.doc, builder.selection[0]!) : null))
</script>

<template>
  <div v-if="builder.doc" class="p-3">
    <BulkProperties v-if="builder.selection.length > 1" />
    <FieldProperties v-else-if="selected?.kind === 'field'" :key="selected.uuid" :uuid="selected.uuid" />
    <GroupProperties v-else-if="selected?.kind === 'group'" :key="selected.uuid" :uuid="selected.uuid" />
    <FormProperties v-else />
  </div>
</template>
