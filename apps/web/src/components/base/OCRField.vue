<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  confidence: { type: Number, default: 100 },
  error: { type: Boolean, default: false },
  errorMessage: { type: String, default: 'SYSTEM ALERT: ERROR' },
  maxlength: { type: [Number, String], default: null }
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

const requiresReview = computed(() => props.confidence < 80)
const isMono = computed(() => 
  props.type === 'number' || 
  props.type === 'date' || 
  props.label.toLowerCase().includes('tin')
)

function onInput(e) {
  emit('update:modelValue', props.type === 'number' ? Number(e.target.value) : e.target.value)
}
</script>

<template>
  <div 
    class="flex flex-col gap-1.5 p-3 border-b border-slate-100 transition-colors duration-150 ease-out" 
    :class="requiresReview ? 'bg-amber-50/50' : 'bg-white'"
  >
    <div class="flex justify-between items-center">
      <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ label }}</label>
      <span v-if="requiresReview" class="text-[10px] font-bold text-amber-600 uppercase italic flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        [ Verify Accuracy ]
      </span>
    </div>

    <div class="relative mt-1">
      <input 
        :type="type" 
        :value="modelValue"
        :maxlength="maxlength"
        class="w-full text-sm border border-slate-300 rounded-none px-3 py-1.5 outline-none bg-white focus:ring-2 focus:ring-primary focus:ring-offset-1 focus:border-primary transition-all duration-150 ease-out"
        :class="[
          isMono ? 'font-mono tabular-nums' : 'font-sans',
          error ? 'border-danger focus:border-danger focus:ring-danger bg-danger/5' : ''
        ]"
        @input="onInput"
        @focus="$emit('focus')"
        @blur="$emit('blur')"
      />
      <div v-if="error" class="absolute -bottom-5 right-0 text-[9px] font-bold text-danger uppercase tracking-widest">
        {{ errorMessage }}
      </div>
    </div>
  </div>
</template>
