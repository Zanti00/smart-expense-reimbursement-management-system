<script setup>
import { cn } from '@/utils/cn'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <h3 :class="cn('font-sans text-lg font-bold leading-none tracking-tight text-slate-800', props.class)">
    <slot />
  </h3>
</template>
