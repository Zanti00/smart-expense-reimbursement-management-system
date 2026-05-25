<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notification'
import NotificationPanel from '@/components/base/NotificationPanel.vue'
import {
  LayoutDashboard, Receipt, Wallet, FilePieChart, ShieldCheck,
  ClipboardList, FileBarChart2, Bell, LogOut, Menu, ChevronRight, FileCheck,
  Search, Sparkles
} from 'lucide-vue-next'

const auth = useAuthStore()
const notif = useNotificationStore()
const router = useRouter()
const route = useRoute()

const sidebarOpen = ref(true)
const notifOpen = ref(false)
const mobileOpen = ref(false)

const navLinks = computed(() => {
  const base = [
    { header: 'OPERATOR' },
    { name: 'Dashboard',     to: '/dashboard',      icon: LayoutDashboard },
    { name: 'Reimbursements',to: '/reimbursements', icon: Receipt },
    { name: 'Cash Advances', to: '/cash-advances',  icon: Wallet },
    { name: 'Liquidations',  to: '/liquidations',   icon: FilePieChart },
  ]
  
  if (!auth.isAdmin) {
    base.push({ name: 'My Expense', to: '/receipts', icon: FileCheck })
  }

  const admin = [
    { header: 'SYSTEM ADMIN' },
    { name: 'Policy',        to: '/admin/policy',   icon: ShieldCheck },
    { name: 'Audit Log',     to: '/admin/audit',    icon: ClipboardList },
    { name: 'Reports',       to: '/admin/reports',  icon: FileBarChart2 },
  ]
  return auth.isAdmin ? [...base, { divider: true }, ...admin] : base
})

const unreadCount = computed(() => notif.alerts.filter(a => !a.read).length)
const pageTitle = computed(() => route.meta?.title || 'SERMS')

function isActive(path) {
  return route.path === path || route.path.startsWith(path + '/')
}

async function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-clinical font-sans select-none">

    <!-- Mobile overlay -->
    <Transition name="fade">
      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-20 bg-slate-900/50 backdrop-blur-sm lg:hidden"
        @click="mobileOpen = false"
      />
    </Transition>

    <!-- ======================== SIDEBAR ======================== -->
    <aside
      :class="[
        'flex flex-col z-30 transition-all duration-300 ease-out flex-shrink-0',
        'fixed lg:relative inset-y-0 left-0',
        sidebarOpen ? 'w-[240px]' : 'w-[68px]',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
      ]"
      style="background: linear-gradient(160deg, #252578 0%, #2F2F7E 50%, #1D1D61 100%);"
    >
      <!-- Subtle grid overlay for depth -->
      <div class="absolute inset-0 opacity-[0.03] pointer-events-none"
           style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 20px 20px;" />

      <!-- Branding -->
      <div class="flex items-center gap-3 px-4 py-5 border-b border-white/10 min-h-[68px] relative">
        <div class="w-9 h-9 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center flex-shrink-0 shadow-inner">
          <Sparkles class="w-4 h-4 text-white" />
        </div>
        <Transition name="fade">
          <div v-if="sidebarOpen" class="overflow-hidden">
            <p class="text-white font-bold text-base leading-none" style="font-family: 'Poppins', sans-serif; letter-spacing: 0.08em;">SERMS</p>
            <p class="text-white/40 text-[9px] mt-0.5 tracking-widest uppercase">Finance Suite</p>
          </div>
        </Transition>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-0 py-4 overflow-y-auto scrollbar-thin space-y-0.5">
        <template v-for="(link, i) in navLinks" :key="i">
          <div v-if="link.divider" class="border-t border-white/10 my-4 mx-4" />
          <div v-else-if="link.header" class="px-4 pt-3 pb-1">
            <Transition name="fade">
              <span
                v-if="sidebarOpen"
                class="text-[10px] font-semibold uppercase tracking-[0.15em] text-white/35"
                style="font-family: 'Poppins', sans-serif;"
              >
                {{ link.header }}
              </span>
            </Transition>
          </div>
          <RouterLink
            v-else
            :to="link.to"
            :class="['sidebar-link', isActive(link.to) ? 'active' : '']"
            :title="!sidebarOpen ? link.name : undefined"
            @click="mobileOpen = false"
          >
            <component
              :is="link.icon"
              :class="['w-4 h-4 flex-shrink-0 transition-colors', isActive(link.to) ? 'text-white' : 'text-white/50']"
            />
            <Transition name="fade">
              <span v-if="sidebarOpen" class="truncate text-sm">{{ link.name }}</span>
            </Transition>
            <Transition name="fade">
              <ChevronRight v-if="sidebarOpen && isActive(link.to)" class="w-3 h-3 ml-auto text-white/60" />
            </Transition>
          </RouterLink>
        </template>
      </nav>

      <!-- User Footer -->
      <div class="border-t border-white/10 p-3 bg-black/10">
        <div class="flex items-center gap-2.5">
          <div
            class="w-9 h-9 rounded-lg bg-gradient-to-br from-accent to-accent-700 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold shadow-sm"
            style="font-family: 'Poppins', sans-serif;"
          >
            {{ auth.user?.avatar }}
          </div>
          <Transition name="fade">
            <div v-if="sidebarOpen" class="flex-1 min-w-0">
              <p class="text-white text-xs font-semibold truncate" style="font-family: 'Poppins', sans-serif;">{{ auth.user?.name }}</p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <div class="w-1.5 h-1.5 bg-success rounded-full animate-pulse" />
                <p class="text-white/50 text-[10px] tracking-wide capitalize">{{ auth.user?.role }}</p>
              </div>
            </div>
          </Transition>
          <button
            v-if="sidebarOpen"
            class="text-white/30 hover:text-white/80 p-1.5 rounded-lg hover:bg-white/10 flex-shrink-0"
            title="Logout"
            @click="logout"
          >
            <LogOut class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ======================== MAIN AREA ======================== -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

      <!-- Frosted Glass Header -->
      <header class="flex items-center gap-3 px-4 lg:px-6 py-3 bg-white/80 backdrop-blur-md border-b border-slate-200/80 z-10 min-h-[68px] shadow-sm">
        <!-- Mobile trigger -->
        <button class="lg:hidden btn-icon" @click="mobileOpen = !mobileOpen">
          <Menu class="w-5 h-5" />
        </button>

        <!-- Collapse toggle -->
        <button
          class="hidden lg:flex btn-icon !p-2"
          :title="sidebarOpen ? 'Collapse' : 'Expand'"
          @click="sidebarOpen = !sidebarOpen"
        >
          <Menu class="w-4 h-4" />
        </button>

        <div class="flex-1" />

        <!-- Search -->
        <div class="hidden md:flex items-center gap-2 bg-slate-100/80 border border-slate-200/80 rounded-lg px-3 py-2 w-56 hover:border-accent/30 focus-within:border-accent/50 focus-within:bg-white transition-all duration-200">
          <Search class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
          <input
            placeholder="Search…"
            class="bg-transparent text-sm text-slate-600 placeholder-slate-400 outline-none flex-1 min-w-0"
            style="transition: none;"
          />
        </div>

        <!-- Notification Bell -->
        <button
          id="notif-bell"
          class="btn-icon !p-2.5 relative"
          @click="notifOpen = !notifOpen"
        >
          <Bell class="w-4 h-4" />
          <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-gradient-to-br from-danger to-red-600 text-white text-[9px] font-bold flex items-center justify-center rounded-full border-2 border-white shadow-sm"
          >
            {{ unreadCount }}
          </span>
        </button>
      </header>

      <!-- Main Content -->
      <main class="flex-1 overflow-y-auto scrollbar-thin p-6" style="background: linear-gradient(135deg, #F4F6FB 0%, #EEF2FF 100%);">
        <RouterView v-slot="{ Component }">
          <Transition name="page" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </main>
    </div>

    <!-- Notification Sidebar -->
    <NotificationPanel :open="notifOpen" @close="notifOpen = false" />
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease-out; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.page-enter-active { transition: opacity 0.2s ease-out, transform 0.2s ease-out; }
.page-leave-active { transition: opacity 0.15s ease-in; }
.page-enter-from { opacity: 0; transform: translateY(6px); }
.page-leave-to { opacity: 0; }
</style>
