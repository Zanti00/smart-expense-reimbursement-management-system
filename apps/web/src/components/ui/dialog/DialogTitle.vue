<script setup>
import { DialogTitle } from 'radix-vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: String, default: undefined },
  class: { type: String, default: '' },
})
</script>

<template>
  <DialogTitle
    v-bind="props"
    :class="cn('text-xl font-bold font-sans text-slate-800 leading-none tracking-tight', props.class)"
  >
    <slot />
  </DialogTitle>
</template>
