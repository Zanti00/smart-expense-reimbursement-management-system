<script setup>
import { cn } from '@/utils/cn'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <div :class="cn('rounded-[14px] bg-white border border-slate-100 shadow-sm text-foreground transition-all duration-200 hover:shadow-md hover:-translate-y-1', props.class)">
    <slot />
  </div>
</template>
