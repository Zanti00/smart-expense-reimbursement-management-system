import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useToast } from './composables/useToast'
import './assets/index.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Restore auth session before mounting
const auth = useAuthStore()
auth.restoreSession()

// Global fetch interceptor for 401 Unauthorized
const originalFetch = window.fetch
let isLoggingOut = false

window.fetch = async (...args) => {
  const response = await originalFetch(...args)
  
  if (response.status === 401) {
    console.log('[Fetch Interceptor] Detected 401 Unauthorized for URL:', typeof args[0] === 'string' ? args[0] : (args[0]?.url || 'unknown'))
    
    if (!isLoggingOut) {
      console.log('[Fetch Interceptor] Initiating logout sequence...')
      isLoggingOut = true
      
      try {
        const { addToast } = useToast()
        addToast({
          message: 'You have been logged out due to session timeout.',
          type: 'error',
          duration: 3000
        })
      } catch (err) {
        console.error('[Fetch Interceptor] Failed to show toast:', err)
      }
      
      try {
        auth.clearSession()
      } catch (err) {
        console.error('[Fetch Interceptor] Failed to clear session:', err)
      }
      
      setTimeout(() => {
        console.log('[Fetch Interceptor] Redirecting to login...')
        auth.logout()
      }, 3000)
    }
  }
  
  return response
}

app.mount('#app')
