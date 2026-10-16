<script setup>
import { cn } from '@/utils/cn'

const props = defineProps({
  class: { type: String, default: '' },
})
</script>

<template>
  <div :class="cn('animate-pulse rounded-md bg-slate-200/70', props.class)" />
</template>
