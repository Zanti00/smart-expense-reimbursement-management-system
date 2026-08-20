<template>
  <div class="fixed top-4 right-4 z-[9999] pointer-events-none w-80 max-w-full sm:w-96">
    <TransitionGroup name="toast" tag="div" class="flex flex-col gap-3">
      <div 
        v-for="toast in toasts" 
        :key="toast.id"
        class="toast-item pointer-events-auto flex items-start gap-3 p-4 rounded-lg bg-white border border-slate-200 shadow-xl text-sm min-w-[300px] max-w-md transform transition-all duration-200 ease-out border-l-4"
        :class="getBorderClass(toast.type)"
      >
        <component
          :is="getIcon(toast.type)"
          class="w-5 h-5 shrink-0 mt-0.5"
          :class="getIconClass(toast.type)"
        />
        <div class="flex-1 min-w-0 flex flex-col">
          <div v-if="toast.title" class="font-bold text-xs uppercase tracking-wider text-slate-800 mb-0.5">
            {{ toast.title }}
          </div>
          <div class="text-sm font-semibold text-slate-700 break-words" :class="{ 'uppercase tracking-wide': !toast.title }">
            {{ toast.message }}
          </div>
        </div>
        <button 
          @click="removeToast(toast.id)"
          class="shrink-0 ml-2 text-slate-400 hover:text-slate-700 transition-colors p-0.5 rounded-md hover:bg-slate-100"
          aria-label="Close"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { AlertCircle, AlertTriangle, CheckCircle, Info, X } from 'lucide-vue-next'
import { useToast } from '@/composables/useToast'

const { toasts, removeToast } = useToast()

function getBorderClass(type) {
  switch (type) {
    case 'error':
    case 'danger':
      return 'border-l-danger'
    case 'warning':
    case 'warn':
      return 'border-l-warning'
    case 'success':
      return 'border-l-success'
    case 'info':
    default:
      return 'border-l-accent'
  }
}

function getIcon(type) {
  switch (type) {
    case 'error':
    case 'danger':
      return AlertCircle
    case 'warning':
    case 'warn':
      return AlertTriangle
    case 'success':
      return CheckCircle
    case 'info':
    default:
      return Info
  }
}

function getIconClass(type) {
  switch (type) {
    case 'error':
    case 'danger':
      return 'text-danger'
    case 'warning':
    case 'warn':
      return 'text-warning'
    case 'success':
      return 'text-success'
    case 'info':
    default:
      return 'text-accent'
  }
}
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(30px) scale(0.95);
}
.toast-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>
