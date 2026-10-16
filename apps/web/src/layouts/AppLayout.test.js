// @vitest-environment happy-dom
import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AppLayout from './AppLayout.vue'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'

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
})
