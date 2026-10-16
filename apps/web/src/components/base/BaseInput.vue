<script setup>
import { computed } from 'vue'
import { cn } from '@/utils/cn'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  error: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const isMono = computed(() => props.type === 'number')

function onInput(e) {
  emit('update:modelValue', props.type === 'number' ? Number(e.target.value) : e.target.value)
}
</script>

<template>
  <div class="flex flex-col gap-1.5 w-full">
    <!-- Canonical Eyebrow Label -->
    <label v-if="label" class="block font-sans text-[11px] font-bold uppercase tracking-wider text-slate-500">
      {{ label }}
    </label>
    <input
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="
        cn(
          'w-full h-10 px-3.5 py-2 text-sm font-sans rounded-lg bg-white border transition-all duration-200 shadow-sm outline-none placeholder:text-slate-400',
          'border-slate-200 hover:border-slate-300 focus:border-[#2E85D8] focus:ring-2 focus:ring-[#2E85D8]/20',
          error ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20 bg-red-50/10' : '',
          isMono ? 'font-mono tabular-nums' : '',
          disabled ? 'opacity-50 cursor-not-allowed bg-slate-50' : ''
        )
      "
      @input="onInput"
    />
    <p v-if="error && errorMessage" class="text-xs text-red-500 font-sans mt-0.5">
      {{ errorMessage }}
    </p>
  </div>
</template>
