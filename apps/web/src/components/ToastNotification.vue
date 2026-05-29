<template>
  <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none">
    <TransitionGroup name="toast">
      <div 
        v-for="toast in toasts" 
        :key="toast.id"
        class="toast-item pointer-events-auto flex items-center p-4 rounded-none shadow-lg text-sm min-w-[300px] max-w-md transform transition-all border-l-4"
        :class="[
          toast.type === 'error' ? 'bg-white text-slate-700 border border-slate-200 border-l-danger shadow-sm' : '',
          toast.type === 'success' ? 'bg-white text-slate-700 border border-slate-200 border-l-success shadow-sm' : '',
          toast.type === 'info' ? 'bg-white text-slate-700 border border-slate-200 border-l-primary shadow-sm' : ''
        ]"
      >
        <div class="flex-1 font-bold tracking-wide uppercase">{{ toast.message }}</div>
        <button 
          @click="removeToast(toast.id)"
          class="ml-4 text-slate-400 hover:text-slate-800 transition-colors text-xl"
          aria-label="Close"
        >
          &times;
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, removeToast } = useToast()
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
