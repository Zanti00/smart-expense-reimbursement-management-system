// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppLayout from './AppLayout.vue'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import { useNotificationStore } from '@/stores/notification'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: { template: '<div>Dashboard</div>' }, meta: { title: 'Dashboard' } },
    { path: '/expenses', component: { template: '<div>Expenses</div>' }, meta: { title: 'Expenses' } },
  ]
})

describe('AppLayout Navigation Chrome', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders sidebar with canonical background Brand Primary #252578', async () => {
    const wrapper = mount(AppLayout, {
      global: { plugins: [router] }
    })
    const aside = wrapper.find('aside')
    expect(aside.exists()).toBe(true)
    expect(aside.classes()).toContain('bg-primary')
  })

  it('renders top header with page title and controls', () => {
    const wrapper = mount(AppLayout, {
      global: { plugins: [router] }
    })
    expect(wrapper.find('header').exists()).toBe(true)
    expect(wrapper.find('header').classes()).toContain('shadow-sm')
  })

  it('renders bottom sidebar footer with Logout action button and label', () => {
    const wrapper = mount(AppLayout, {
      global: { plugins: [router] }
    })
    const logoutBtn = wrapper.find('aside button')
    expect(logoutBtn.exists()).toBe(true)
    expect(logoutBtn.text()).toContain('Logout')
  })

  it('handles notification count badge rendering and caps at 99+', async () => {
    // Mock global fetch to prevent unhandled ECONNREFUSED in onMounted
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ unread_count: 0, data: [] })
    }))

    const notifStore = useNotificationStore()
    const wrapper = mount(AppLayout, {
      global: { plugins: [router] }
    })

    // When unreadCount === 0, badge should not exist
    expect(wrapper.find('[data-testid="notif-badge"]').exists()).toBe(false)

    // When unreadCount is 1
    notifStore.alerts = [{ id: 1, read: false }]
    await wrapper.vm.$nextTick()
    const badge = wrapper.find('[data-testid="notif-badge"]')
    expect(badge.exists()).toBe(true)
    expect(badge.text()).toBe('1')
    expect(badge.classes()).toContain('bg-secondary')
    expect(badge.classes()).toContain('rounded-full')
    expect(badge.classes()).toContain('min-w-[18px]')
    expect(badge.classes()).toContain('h-[18px]')

    // When unreadCount > 99
    notifStore.alerts = Array.from({ length: 105 }, (_, i) => ({ id: i + 1, read: false }))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[data-testid="notif-badge"]').text()).toBe('99+')

    vi.unstubAllGlobals()
  })
})
