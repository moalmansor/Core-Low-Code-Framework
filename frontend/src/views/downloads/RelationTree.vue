<script setup lang="ts">
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { downloadsApi, pathKey, type TreeNode } from './api'

/**
 * The relation tree explorer (specification §4.23): the form's fields, the
 * forms it links to, the forms whose records link back to it, its repeating
 * sections and its system values; each linked form opens on demand, as deep
 * as the setting allows. Ticking a field adds it as a column.
 */
const props = defineProps<{ form: string; personal: boolean; selected: Set<string>; prefix?: string[]; depth?: number }>()
const emit = defineEmits<{ toggle: [node: TreeNode, on: boolean]; loaded: [prefix: string[], nodes: TreeNode[]] }>()
const { t } = useI18n()
const nodes = ref<TreeNode[] | null>(null)
const failed = ref(false)
const open = ref<Record<string, boolean>>({})

async function load(): Promise<void> {
  failed.value = false
  try {
    const res = await downloadsApi.tree(props.form, props.prefix ?? [], props.personal)
    nodes.value = res.data
    emit('loaded', props.prefix ?? [], res.data)
  } catch {
    failed.value = true
  }
}
onMounted(load)
watch(() => [props.form, props.personal], load)

const icon = (n: TreeNode) => ({ field: n.sensitive ? 'pi pi-lock' : 'pi pi-circle', link: 'pi pi-link', reverse: 'pi pi-reply', repeater: 'pi pi-list', system: 'pi pi-cog' })[n.kind]
const expandable = (n: TreeNode) => n.kind === 'link' || n.kind === 'reverse' || n.kind === 'repeater'
const id = (n: TreeNode) => `dl-node-${n.kind}-${pathKey(n.path).replace(/[^a-zA-Z0-9_-]/g, '_')}`
</script>

<template>
  <p v-if="failed" class="m-0 text-sm text-danger">{{ t('dl.tree_failed') }}</p>
  <p v-else-if="nodes === null" class="m-0 text-sm text-muted-color">{{ t('dl.loading') }}</p>
  <ul v-else class="dl-tree" :data-testid="prefix?.length ? undefined : 'dl-tree'">
    <li v-for="n in nodes" :key="n.kind + pathKey(n.path)">
      <div class="dl-node">
        <button
          v-if="expandable(n)"
          type="button"
          class="dl-toggle"
          :aria-expanded="!!open[pathKey(n.path)]"
          :aria-label="open[pathKey(n.path)] ? t('dl.collapse') : t('dl.expand')"
          :data-testid="`dl-expand-${n.label}`"
          @click="open[pathKey(n.path)] = !open[pathKey(n.path)]"
        >
          <i :class="['pi', open[pathKey(n.path)] ? 'pi-chevron-down' : 'pi-chevron-right', 'rtl:rotate-180']" aria-hidden="true" />
        </button>
        <Checkbox v-else :input-id="id(n)" binary :model-value="selected.has(pathKey(n.path))" :data-testid="`dl-pick-${n.label}`" @update:model-value="(v: boolean) => emit('toggle', n, v)" />
        <i :class="[icon(n), 'text-muted-color text-xs']" aria-hidden="true" />
        <label :for="expandable(n) ? undefined : id(n)" class="text-sm" dir="auto">
          {{ n.label }}<span v-if="n.kind === 'link' && n.form" class="text-muted-color"> → {{ n.form }}</span>
        </label>
        <Tag v-if="n.toMany && (expandable(n) || n.kind === 'field')" severity="secondary" :value="t('dl.many')" class="dl-many" />
        <Tag v-if="n.sensitive" severity="warn" :value="t('dl.sensitive')" class="dl-many" />
      </div>
      <div v-if="expandable(n) && open[pathKey(n.path)]" class="dl-children">
        <RelationTree
          :form="form"
          :personal="personal"
          :selected="selected"
          :prefix="n.path"
          :depth="(depth ?? 0) + 1"
          @toggle="(node, on) => emit('toggle', node, on)"
          @loaded="(p, list) => emit('loaded', p, list)"
        />
      </div>
    </li>
  </ul>
</template>

<style scoped>
.dl-tree {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
}
.dl-node {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 1.875rem;
}
.dl-toggle {
  width: 1.375rem;
  height: 1.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  border-radius: 4px;
}
.dl-toggle:hover {
  background: var(--bg-subtle);
}
.dl-children {
  padding-inline-start: 1.25rem;
  border-inline-start: 1px solid var(--border);
  margin-inline-start: 0.6875rem;
}
.dl-many {
  font-size: 0.6875rem;
  padding: 0 0.375rem;
}
</style>
