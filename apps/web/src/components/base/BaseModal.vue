<script setup>
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { DialogTitle, DialogDescription, VisuallyHidden } from 'radix-vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  maxWidthClass: {
    type: String,
    default: "max-w-md",
  },
  zIndexClass: {
    type: String,
    default: "z-[60]",
  },
  contentClass: {
    type: String,
    default: "",
  },
  title: {
    type: String,
    default: "Modal Window",
  },
  description: {
    type: String,
    default: "Modal Window Content",
  }
})

const emit = defineEmits(["close"])

function onOpenChange(open) {
  if (!open) {
    emit("close")
  }
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="onOpenChange">
    <DialogContent
      :show-close="false"
      :class="cn('p-0 overflow-hidden rounded-2xl border-none shadow-2xl bg-white', maxWidthClass, contentClass)"
    >
      <!-- VisuallyHidden fallback for Radix Dialog Accessibility -->
      <VisuallyHidden as-child>
        <DialogTitle>{{ title }}</DialogTitle>
      </VisuallyHidden>
      <VisuallyHidden as-child>
        <DialogDescription>{{ description }}</DialogDescription>
      </VisuallyHidden>

      <slot />
    </DialogContent>
  </Dialog>
</template>
