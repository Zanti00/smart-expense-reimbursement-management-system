<script setup>
import { DialogDescription } from 'radix-vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: String, default: undefined },
  class: { type: String, default: '' },
})
</script>

<template>
  <DialogDescription
    v-bind="props"
    :class="cn('text-sm text-slate-500 font-sans', props.class)"
  >
    <slot />
  </DialogDescription>
</template>
