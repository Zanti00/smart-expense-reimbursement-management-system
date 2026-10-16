<script setup>
import { DropdownMenuSeparator } from 'radix-vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  asChild: { type: Boolean, default: false },
  as: { type: String, default: undefined },
  class: { type: String, default: '' },
})
</script>

<template>
  <DropdownMenuSeparator
    v-bind="props"
    :class="cn('-mx-1 my-1 h-px bg-slate-100', props.class)"
  />
</template>
