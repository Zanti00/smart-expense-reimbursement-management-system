<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { MoreHorizontal } from "lucide-vue-next";

/**
 * ActionDropdownMenu — Reusable ellipsis dropdown for table row actions.
 *
 * @prop {Array} actions — Each item: { label, icon, visible, disabled, handler, variant }
 *   - label: Display text
 *   - icon: Lucide icon component
 *   - visible: Boolean — whether the item is shown
 *   - disabled: Boolean (optional) — greyed-out if true
 *   - handler: Function — called on click
 *   - variant: 'default' | 'danger' (optional) — danger renders in red
 */
defineProps({
  actions: {
    type: Array,
    required: true,
    validator: (v) => v.every((a) => a.label && a.handler),
  },
});

const isOpen = ref(false);
const menuRef = ref(null);
const buttonRef = ref(null);
const floatingMenuRef = ref(null);
const menuPosition = ref({ top: 0, left: 0 });

function updateMenuPosition() {
  if (!buttonRef.value || !floatingMenuRef.value) return;

  const gap = 6;
  const viewportPadding = 8;
  const buttonRect = buttonRef.value.getBoundingClientRect();
  const menuRect = floatingMenuRef.value.getBoundingClientRect();
  const menuWidth = menuRect.width || 160;
  const menuHeight = menuRect.height || 0;

  const maxLeft = Math.max(
    viewportPadding,
    window.innerWidth - menuWidth - viewportPadding,
  );
  const left = Math.min(
    Math.max(viewportPadding, buttonRect.right - menuWidth),
    maxLeft,
  );
  const preferredTop = buttonRect.top - menuHeight - gap;
  const fallbackTop = buttonRect.bottom + gap;
  const top =
    preferredTop >= viewportPadding
      ? preferredTop
      : Math.min(fallbackTop, window.innerHeight - menuHeight - viewportPadding);

  menuPosition.value = {
    top: Math.max(viewportPadding, top),
    left,
  };
}

function addPositionListeners() {
  window.addEventListener("resize", updateMenuPosition);
  window.addEventListener("scroll", updateMenuPosition, true);
}

function removePositionListeners() {
  window.removeEventListener("resize", updateMenuPosition);
  window.removeEventListener("scroll", updateMenuPosition, true);
}

function toggle() {
  isOpen.value = !isOpen.value;
}

function handleAction(action) {
  if (action.disabled) return;
  isOpen.value = false;
  action.handler();
}

function handleClickOutside(event) {
  const clickedTrigger = menuRef.value?.contains(event.target);
  const clickedMenu = floatingMenuRef.value?.contains(event.target);

  if (!clickedTrigger && !clickedMenu) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener("mousedown", handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener("mousedown", handleClickOutside);
  removePositionListeners();
});

watch(isOpen, async (open) => {
  if (!open) {
    removePositionListeners();
    return;
  }

  await nextTick();
  updateMenuPosition();
  addPositionListeners();
});
</script>

<template>
  <div ref="menuRef" class="relative inline-flex items-center justify-center">
    <button
      ref="buttonRef"
      type="button"
      class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-all duration-200 ease-out hover:border-accent/30 hover:bg-accent/5 hover:text-accent focus:outline-none"
      :class="{ 'border-accent/30 bg-accent/5 text-accent': isOpen }"
      title="Actions"
      @click.stop="toggle"
    >
      <MoreHorizontal class="h-4 w-4" />
    </button>

    <Teleport to="body">
      <Transition name="dropdown">
        <div
          v-if="isOpen"
          ref="floatingMenuRef"
          class="fixed z-[9999] min-w-[160px] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg shadow-slate-200/50"
          :style="{
            top: `${menuPosition.top}px`,
            left: `${menuPosition.left}px`,
          }"
          @click.stop
        >
          <template v-for="(action, index) in actions" :key="index">
            <button
              v-if="action.visible !== false"
              type="button"
              class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-xs font-semibold transition-colors duration-150"
              :class="[
                action.disabled
                  ? 'cursor-not-allowed text-slate-300'
                  : action.variant === 'danger'
                    ? 'text-red-600 hover:bg-red-50'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800',
              ]"
              :disabled="action.disabled"
              @click.stop="handleAction(action)"
            >
              <component
                :is="action.icon"
                v-if="action.icon"
                class="h-3.5 w-3.5 shrink-0"
              />
              <span>{{ action.label }}</span>
            </button>
          </template>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.dropdown-enter-active {
  transition: opacity 0.15s ease-out, transform 0.15s ease-out;
}
.dropdown-leave-active {
  transition: opacity 0.1s ease-in, transform 0.1s ease-in;
}
.dropdown-enter-from {
  opacity: 0;
  transform: translateY(4px) scale(0.97);
}
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(2px) scale(0.98);
}
</style>
