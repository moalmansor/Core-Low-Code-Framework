<script setup lang="ts">
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { inject } from 'vue'
import { useI18n } from 'vue-i18n'
import draggable from 'vuedraggable'
import type { MenuNode, MenuTreeContext } from './menuTree'

/** One level of the menu tree; nested levels share the drag group so items can move between them. */
defineProps<{ nodes: MenuNode[]; depth: number }>()
const ctx = inject<MenuTreeContext>('menuTree')!
const { t } = useI18n()
const typeIcon: Record<string, string> = { form: 'pi pi-file-edit', collection: 'pi pi-table', link: 'pi pi-link', header: 'pi pi-folder', separator: 'pi pi-minus' }
</script>

<template>
  <draggable :list="nodes" item-key="id" group="menu" handle=".menu-drag" tag="ul" :animation="150" class="menu-level flex flex-col gap-1" :class="depth > 0 ? 'ps-6 min-h-3' : 'min-h-10'">
    <template #item="{ element }">
      <li :data-testid="`menu-node-${element.id}`">
        <div
          class="flex items-center gap-2 rounded-lg border px-2 py-1.5 bg-surface-0 dark:bg-surface-900 cursor-pointer"
          :class="[
            ctx.selected() === element.id ? 'border-primary' : 'border-surface-200 dark:border-surface-700',
            ctx.hasProblem(element.id) ? 'border-red-500' : '',
            element.is_active ? '' : 'opacity-60',
          ]"
          role="button"
          tabindex="0"
          @click="ctx.select(element.id)"
          @keydown.enter.prevent="ctx.select(element.id)"
        >
          <i class="pi pi-bars menu-drag cursor-grab text-muted-color" :title="t('building.menu.drag')" aria-hidden="true" />
          <i :class="element.icon || typeIcon[element.type]" aria-hidden="true" />
          <span class="flex-1 min-w-0 truncate">
            <template v-if="element.type === 'separator'"
              ><span class="text-muted-color">— {{ t('building.menu.type.separator') }} —</span></template
            >
            <template v-else>{{ ctx.labelOf(element) }}</template>
            <span v-if="ctx.targetOf(element)" class="text-xs text-muted-color ms-2">{{ ctx.targetOf(element) }}</span>
          </span>
          <Tag :value="t(`building.menu.type.${element.type}`)" severity="secondary" class="hidden sm:inline-flex" />
          <i v-if="ctx.hasProblem(element.id)" class="pi pi-exclamation-triangle text-red-500" :title="t('building.menu.has_problem')" />
          <div class="flex items-center" @click.stop>
            <Button icon="pi pi-arrow-up" text rounded size="small" :aria-label="t('building.menu.move_up')" @click="ctx.move(element.id, 'up')" />
            <Button icon="pi pi-arrow-down" text rounded size="small" :aria-label="t('building.menu.move_down')" @click="ctx.move(element.id, 'down')" />
            <Button icon="pi pi-angle-double-left rtl:rotate-180" text rounded size="small" :aria-label="t('building.menu.outdent')" @click="ctx.move(element.id, 'out')" />
            <Button icon="pi pi-angle-double-right rtl:rotate-180" text rounded size="small" :aria-label="t('building.menu.indent')" @click="ctx.move(element.id, 'in')" />
            <Button v-if="element.type !== 'separator'" icon="pi pi-plus" text rounded size="small" :aria-label="t('building.menu.add_child')" @click="ctx.addChild(element.id)" />
            <Button icon="pi pi-trash" text rounded size="small" severity="danger" :aria-label="t('common.delete')" @click="ctx.remove(element.id)" />
          </div>
        </div>
        <MenuTreeList v-if="element.type !== 'separator'" :nodes="element.children" :depth="depth + 1" class="mt-1" />
      </li>
    </template>
  </draggable>
</template>
