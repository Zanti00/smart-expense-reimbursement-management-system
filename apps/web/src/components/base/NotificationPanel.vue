<script setup>
import { computed } from 'vue'
import { Bell, CheckCheck, AlertTriangle, CheckCircle, Info, AlertCircle, X, Activity, Clock } from 'lucide-vue-next'
import { useNotificationStore } from '@/stores/notification'

defineProps({ open: Boolean })
defineEmits(['close'])

const store = useNotificationStore()

const unread = computed(() => store.alerts.filter(a => !a.read).length)

const iconMap = {
  warning: { icon: AlertTriangle, cls: 'text-warning border-warning/20 bg-warning/5' },
  success: { icon: CheckCircle,   cls: 'text-success border-success/20 bg-success/5' },
  danger:  { icon: AlertCircle,   cls: 'text-danger border-danger/20 bg-danger/5' },
  info:    { icon: Info,          cls: 'text-primary border-primary/20 bg-primary/5' },
}
</script>

<template>
  <!-- Backdrop -->
  <Transition name="fade">
    <div v-if="open" class="fixed inset-0 z-[55] bg-slate-900/40 backdrop-blur-[1px]" @click="$emit('close')" />
  </Transition>

  <!-- Panel -->
  <Transition name="slide-right">
    <aside
      v-if="open"
      class="fixed top-0 right-0 z-[60] flex h-full w-80 flex-col bg-white shadow-2xl border-l border-slate-200 font-sans"
    >
      <!-- Panel Header -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-slate-50">
        <div class="flex items-center gap-2">
          <Bell class="w-4 h-4 text-primary" />
          <h2 class="text-xs font-bold text-primary uppercase tracking-widest">Recent Activity</h2>
          <span v-if="unread > 0" class="text-[9px] font-bold bg-primary text-white px-1.5 py-0.5 rounded-none">{{ unread }}</span>
        </div>
        <div class="flex items-center gap-2">
          <button class="text-[9px] font-bold text-slate-400 hover:text-primary transition-none uppercase tracking-widest" @click="store.markAllRead()">
            Mark All Read
          </button>
          <button class="text-slate-300 hover:text-danger transition-none" @click="$emit('close')">
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Notification Flux -->
      <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
        <div v-if="store.alerts.length === 0" class="h-40 flex flex-col items-center justify-center text-center opacity-40 grayscale uppercase font-bold tracking-[0.2em]">
          <Clock class="w-6 h-6 text-slate-300 mb-2" />
          <p class="text-[9px] text-slate-400 leading-loose">No new notifications</p>
        </div>

        <TransitionGroup name="fade">
          <div
            v-for="alert in store.alerts"
            :key="alert.id"
            :class="['flex gap-4 px-5 py-4 cursor-pointer transition-none border-l-2',
              alert.read ? 'bg-white border-transparent' : 'bg-primary/[0.02] border-primary'
            ]"
            @click="store.markRead(alert.id)"
          >
            <div :class="['w-8 h-8 border flex items-center justify-center flex-shrink-0 mt-0.5', iconMap[alert.type]?.cls]">
              <component :is="iconMap[alert.type]?.icon" class="w-3.5 h-3.5" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2 mb-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">[{{ alert.type }}]</p>
                <span class="text-[8px] font-mono text-slate-300">{{ alert.time }}</span>
              </div>
              <p class="text-xs text-slate-600 font-medium leading-relaxed">
                {{ alert.message }}
              </p>
            </div>
          </div>
        </TransitionGroup>
      </div>

      <!-- Registry Control -->
      <div v-if="store.alerts.length > 0" class="p-4 bg-slate-50 border-t border-slate-100">
        <button class="w-full text-[10px] font-bold text-slate-400 hover:text-danger transition-none uppercase tracking-widest text-center py-2 border border-slate-200 bg-white" @click="store.clearAll()">
          Clear All Messages
        </button>
      </div>
    </aside>
  </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.1s linear; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.15s linear; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
</style>
