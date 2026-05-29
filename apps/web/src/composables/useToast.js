import { ref } from 'vue'

// Global state for toasts so they can persist across component instances
const toasts = ref([])
let nextId = 0

export function useToast() {
  /**
   * Add a new toast notification
   * @param {Object} options - Toast options
   * @param {string} options.message - The message to display
   * @param {('success'|'error'|'info')} [options.type='info'] - The type of toast
   * @param {number} [options.duration=5000] - Duration in ms before the toast disappears
   */
  const addToast = (options) => {
    const id = nextId++
    const toast = {
      id,
      message: options.message,
      type: options.type || 'info',
      duration: options.duration || 5000
    }
    toasts.value.push(toast)

    if (toast.duration && toast.duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, toast.duration)
    }
  }

  /**
   * Remove a toast by its ID
   * @param {number} id - The toast ID to remove
   */
  const removeToast = (id) => {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }

  return {
    toasts,
    addToast,
    removeToast
  }
}
