import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('serms_token') || null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  // Restore session from localStorage on app boot
  function restoreSession() {
    const stored = localStorage.getItem('serms_user')
    if (stored && token.value) {
      user.value = JSON.parse(stored)
    }
  }

  /**
   * In a real app, POST /api/auth/login and receive token + user.
   * For now, we use mock auth.
   */
  async function login(email, password) {
    // --- MOCK AUTH (replace with real API call) ---
    const mockUsers = {
      'admin@serms.com': { id: 1, name: 'Alex Reyes', email: 'admin@serms.com', role: 'admin', avatar: 'AR', grade: 'EXEC', department: 'FINANCE' },
      'employee@serms.com': { id: 3, name: 'John Santos', email: 'employee@serms.com', role: 'employee', avatar: 'JS', grade: 'L2', department: 'SALES' }
    }
    await new Promise(r => setTimeout(r, 800)) // simulate network delay
    const found = mockUsers[email]
    if (!found || password !== 'password') {
      throw new Error('Invalid email or password.')
    }
    user.value = found
    token.value = `mock_token_${found.id}`
    localStorage.setItem('serms_token', token.value)
    localStorage.setItem('serms_user', JSON.stringify(found))
    // --- END MOCK AUTH ---
  }

  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('serms_token')
    localStorage.removeItem('serms_user')
  }

  return { user, token, isAuthenticated, isAdmin, login, logout, restoreSession }
})
