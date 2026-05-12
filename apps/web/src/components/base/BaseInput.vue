<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  error: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])

const isMono = computed(() => props.type === 'number')

function onInput(e) {
  emit('update:modelValue', props.type === 'number' ? Number(e.target.value) : e.target.value)
}
</script>

<template>
  <div class="input-wrapper">
    <label v-if="label" class="input-label">{{ label }}</label>
    <input
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :class="['input', isMono ? 'font-mono tabular-nums' : '', error ? 'input-error' : '']"
      @input="onInput"
    />
  </div>
</template>
