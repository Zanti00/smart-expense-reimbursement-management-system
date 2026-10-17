import { ref } from 'vue'

// Global state for toasts so they can persist across component instances
const toasts = ref([])
let nextId = 0

// Internal timer tracking — maps toast id to { timeoutId, remaining, startTime }
const timers = new Map()

/**
 * Start (or restart) the auto-dismiss countdown for a toast.
 * @param {number} id - Toast ID
 * @param {number} duration - Milliseconds until auto-dismiss
 */
function startTimer(id, duration) {
  const timeoutId = setTimeout(() => removeToast(id), duration)
  timers.set(id, { timeoutId, remaining: duration, startTime: Date.now() })
}

/**
 * Pause the auto-dismiss timer (e.g. on hover).
 * Clears the active timeout and stores the remaining time.
 * @param {number} id - Toast ID
 */
function pauseToast(id) {
  const timer = timers.get(id)
  if (!timer) return
  clearTimeout(timer.timeoutId)
  const elapsed = Date.now() - timer.startTime
  timer.remaining = Math.max(timer.remaining - elapsed, 0)
  timer.timeoutId = null
}

/**
 * Resume the auto-dismiss timer with the remaining time.
 * @param {number} id - Toast ID
 */
function resumeToast(id) {
  const timer = timers.get(id)
  if (!timer || timer.timeoutId !== null) return
  timer.timeoutId = setTimeout(() => removeToast(id), timer.remaining)
  timer.startTime = Date.now()
}

/**
 * Remove a toast by its ID and clean up its timer.
 * @param {number} id - The toast ID to remove
 */
function removeToast(id) {
  const timer = timers.get(id)
  if (timer?.timeoutId) clearTimeout(timer.timeoutId)
  timers.delete(id)
  toasts.value = toasts.value.filter(t => t.id !== id)
}

export function useToast() {
  /**
   * Add a new toast notification
   * @param {Object|string} options - Toast options or a plain message string
   * @param {string} options.message - The message to display
   * @param {string} [options.title] - Optional bold title
   * @param {('success'|'error'|'info'|'warning')} [options.type='info'] - The type of toast
   * @param {number} [options.duration=4000] - Duration in ms before the toast disappears
   */
  const addToast = (options) => {
    const id = nextId++
    const isString = typeof options === 'string'
    const toast = isString
      ? { id, title: '', message: options, type: 'info', duration: 4000 }
      : {
          id,
          title: options.title || '',
          message: options.message || '',
          type: options.type || 'info',
          duration: options.duration !== undefined ? options.duration : 4000
        }
    toasts.value = [...toasts.value, toast]

    if (toast.duration && toast.duration > 0) {
      startTimer(id, toast.duration)
    }
  }

  return {
    toasts,
    addToast,
    removeToast,
    pauseToast,
    resumeToast
  }
}
