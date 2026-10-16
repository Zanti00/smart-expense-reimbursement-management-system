<script setup>
import { MoreHorizontal } from "lucide-vue-next";
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/components/ui/dropdown-menu";
import { cn } from "@/utils/cn";

/**
 * ActionDropdownMenu — Accessible Radix-backed dropdown for table row actions.
 *
 * @prop {Array} actions — Each item: { label, icon, visible, disabled, handler, variant }
 */
defineProps({
  actions: {
    type: Array,
    required: true,
    validator: (v) => v.every((a) => a.label && a.handler),
  },
});

function handleAction(action) {
  if (action.disabled) return;
  action.handler();
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <button
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition-all duration-200 ease-out hover:border-[#2E85D8]/40 hover:bg-[#EFF6FF] hover:text-[#2E85D8] focus:outline-none focus:ring-2 focus:ring-[#2E85D8]/20"
        title="Actions"
        @click.stop
      >
        <MoreHorizontal class="h-4 w-4" />
      </button>
    </DropdownMenuTrigger>

    <DropdownMenuContent align="end" class="min-w-[160px] p-1 shadow-md rounded-xl">
      <template v-for="(action, index) in actions" :key="index">
        <DropdownMenuItem
          v-if="action.visible !== false"
          :disabled="action.disabled"
          :class="
            cn(
              'flex items-center gap-2.5 px-3 py-2 text-xs font-semibold font-sans rounded-lg transition-colors cursor-pointer',
              action.disabled
                ? 'opacity-40 cursor-not-allowed'
                : action.variant === 'danger'
                  ? 'text-red-600 hover:bg-red-50 focus:bg-red-50 focus:text-red-700'
                  : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100'
            )
          "
          @select.stop="handleAction(action)"
        >
          <component
            :is="action.icon"
            v-if="action.icon"
            class="h-3.5 w-3.5 shrink-0"
          />
          <span>{{ action.label }}</span>
        </DropdownMenuItem>
      </template>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
