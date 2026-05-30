import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
  const alerts = ref([
    { id: 1, type: 'warning', message: 'RM-2024-005 requires your approval.', time: '5m ago', read: false },
    { id: 2, type: 'success', message: 'RM-2024-004 has been paid out.', time: '1h ago', read: false },
    { id: 3, type: 'danger', message: 'CA-2024-003 liquidation is overdue.', time: '2h ago', read: true },
    { id: 4, type: 'info', message: 'Monthly report is ready for export.', time: '1d ago', read: true },
  ])

  const unreadCount = computed => alerts.value.filter(a => !a.read).length

  function markRead(id) {
    const a = alerts.value.find(a => a.id === id)
    if (a) a.read = true
  }

  function markAllRead() {
    alerts.value.forEach(a => a.read = true)
  }

  function push(notification) {
    alerts.value.unshift({
      id: Date.now(),
      read: false,
      time: 'Just now',
      ...notification
    })
  }

  function info(message) {
    push({ type: 'info', message })
  }

  function success(message) {
    push({ type: 'success', message })
  }

  function warning(message) {
    push({ type: 'warning', message })
  }

  function error(message) {
    push({ type: 'danger', message })
  }

  return { alerts, unreadCount, markRead, markAllRead, push, info, success, warning, error }
})
