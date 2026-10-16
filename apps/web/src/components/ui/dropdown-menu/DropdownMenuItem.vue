<script setup>
import { DropdownMenuItem, useForwardPropsEmits } from 'radix-vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  textValue: { type: String, default: undefined },
  asChild: { type: Boolean, default: false },
  as: { type: String, default: undefined },
  class: { type: String, default: '' },
})
const emits = defineEmits(['select'])

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <DropdownMenuItem
    v-bind="forwarded"
    :class="
      cn(
        'relative flex cursor-pointer select-none items-center gap-2 rounded-lg px-3 py-2 text-sm font-sans outline-none transition-colors hover:bg-slate-100 focus:bg-slate-100 data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
        props.class
      )
    "
  >
    <slot />
  </DropdownMenuItem>
</template>
