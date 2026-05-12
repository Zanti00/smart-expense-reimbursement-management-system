<script setup>
import { ref, watch, onUnmounted } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger
  size: { type: String, default: 'md' },          // sm | md | icon
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
  requireHold: { type: Boolean, default: false }, // If true, requires 2s hold to emit click
  holdDuration: { type: Number, default: 2000 }
})

const emit = defineEmits(['click'])

const variantClass = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  ghost: 'btn-ghost',
  danger: 'btn-danger',
  icon: 'btn-icon',
  cta: 'btn-cta'
}

const sizeClass = {
  sm: 'btn-sm',
  md: '',
  icon: 'p-1.5'
}

const isHolding = ref(false)
const progress = ref(0)
let holdTimer = null
let animationFrame = null
let startTime = null

function cancelHold() {
  isHolding.value = false
  progress.value = 0
  if (holdTimer) clearTimeout(holdTimer)
  if (animationFrame) cancelAnimationFrame(animationFrame)
  holdTimer = null
  animationFrame = null
  startTime = null
}

function updateProgress(timestamp) {
  if (!startTime) startTime = timestamp
  const elapsed = timestamp - startTime
  progress.value = Math.min((elapsed / props.holdDuration) * 100, 100)
  
  if (isHolding.value && progress.value < 100) {
    animationFrame = requestAnimationFrame(updateProgress)
  }
}

function startHold(e) {
  if (props.disabled || props.loading) return
  if (!props.requireHold) {
    emit('click', e)
    return
  }
  
  isHolding.value = true
  progress.value = 0
  animationFrame = requestAnimationFrame(updateProgress)
  
  holdTimer = setTimeout(() => {
    cancelHold()
    emit('click', e)
  }, props.holdDuration)
}

onUnmounted(() => {
  cancelHold()
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[variantClass[variant], sizeClass[size], 'btn relative overflow-hidden transition-all duration-150']"
    @mousedown="startHold"
    @mouseup="cancelHold"
    @mouseleave="cancelHold"
    @touchstart.passive="startHold"
    @touchend.passive="cancelHold"
    @touchcancel.passive="cancelHold"
  >
    <!-- Hold Progress Indicator -->
    <div 
      v-if="requireHold && isHolding" 
      class="absolute inset-0 bg-black/20 origin-left"
      :style="{ width: `${progress}%`, transition: 'none' }"
    ></div>
    
    <div class="relative z-10 flex items-center justify-center gap-2">
      <svg v-if="loading" class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
      <slot />
      <span v-if="requireHold && !isHolding && !loading" class="text-[9px] opacity-70 ml-1">(HOLD)</span>
    </div>
  </button>
</template>
