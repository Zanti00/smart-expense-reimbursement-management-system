<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useNotificationStore } from "@/stores/notification";
import { useToast } from "@/composables/useToast";
import NotificationPanel from "@/components/base/NotificationPanel.vue";
import ToastNotification from "@/components/ToastNotification.vue";
import {
  LayoutDashboard,
  Receipt,
  Wallet,
  FilePieChart,
  ShieldCheck,
  ClipboardList,
  FileBarChart2,
  Bell,
  LogOut,
  Menu,
  X,
  ChevronRight,
  FlaskConical,
  FileCheck,
} from "lucide-vue-next";

const auth = useAuthStore();
const notif = useNotificationStore();
const router = useRouter();
const route = useRoute();
const { addToast } = useToast();

onMounted(() => {
  if (route.query.message) {
    addToast({
      message: route.query.message,
      type: 'success',
      duration: 5000
    });
    
    // Clean up URL so it doesn't persist on refresh
    const query = { ...route.query };
    delete query.message;
    router.replace({ query });
  }
});

const sidebarOpen = ref(true);
const notifOpen = ref(false);
const mobileOpen = ref(false);

const navLinks = computed(() => {
  const base = [
    { header: "OPERATOR" },
    { name: "Dashboard", to: "/dashboard", icon: LayoutDashboard },
    { name: "Reimbursements", to: "/reimbursements", icon: Receipt },
    { name: "Cash Advances", to: "/cash-advances", icon: Wallet },
    { name: "Liquidations", to: "/liquidations", icon: FilePieChart },
    { name: "Expenses", to: "/expense-management", icon: FileCheck },
  ];
  const admin = [
    { header: "SYSTEM ADMIN" },
    { name: "Policy", to: "/admin/policy", icon: ShieldCheck },
    { name: "Audit Log", to: "/admin/audit", icon: ClipboardList },
    { name: "Reports", to: "/admin/reports", icon: FileBarChart2 },
  ];
  return auth.isAdmin ? [...base, { divider: true }, ...admin] : base;
});

const unreadCount = computed(() => notif.alerts.filter((a) => !a.read).length);

const pageTitle = computed(() => route.meta?.title || "SERMS");

function isActive(path) {
  return route.path === path || route.path.startsWith(path + "/");
}

async function logout() {
  auth.logout();
  // auth.logout() redirects to the capstone-auth-module's logout endpoint
  // via window.location.href — no router.push needed
}
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-clinical font-sans select-none">
    <ToastNotification />
    <!-- ======================== SIDEBAR ======================== -->
    <!-- Mobile overlay -->
    <div
      v-if="mobileOpen"
      class="fixed inset-0 z-20 bg-black/40 lg:hidden"
      @click="mobileOpen = false"
    />

    <aside
      :class="[
        'flex flex-col bg-primary z-30 transition-all duration-150 ease-out border-r border-white/10 flex-shrink-0',
        'fixed lg:relative inset-y-0 left-0',
        sidebarOpen ? 'w-[240px]' : 'w-16',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      ]"
    >
      <!-- Branding Module -->
      <div
        class="flex items-center gap-3 px-4 py-6 border-b border-white/10 min-h-[70px]"
      >
        <div
          class="w-8 h-8 border border-white/30 flex items-center justify-center bg-white/10 flex-shrink-0"
        >
          <FlaskConical class="w-4 h-4 text-white" />
        </div>
        <Transition name="fade">
          <div v-if="sidebarOpen" class="overflow-hidden whitespace-nowrap">
            <p
              class="text-white font-bold text-base tracking-widest uppercase leading-none"
            >
              SERMS
            </p>
          </div>
        </Transition>
      </div>

      <!-- Navigation Log -->
      <nav class="flex-1 px-0 py-4 space-y-0 overflow-y-auto scrollbar-thin">
        <template v-for="(link, i) in navLinks" :key="i">
          <div v-if="link.divider" class="border-t border-white/5 my-4 mx-4" />
          <div v-else-if="link.header" class="px-6 py-2">
            <Transition name="fade">
              <span
                v-if="sidebarOpen"
                class="text-[11px] font-bold uppercase tracking-wider text-primary-300 opacity-80 flex items-center gap-2"
              >
                <div class="w-1.5 h-1.5 bg-primary-400 opacity-50" />
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
              :class="[
                'w-4 h-4 flex-shrink-0',
                isActive(link.to) ? 'text-white' : 'text-slate-400',
              ]"
            />
            <Transition name="fade">
              <span v-if="sidebarOpen" class="truncate">{{ link.name }}</span>
            </Transition>
          </RouterLink>
        </template>
      </nav>

      <!-- Operator Status -->
      <div class="border-t border-white/10 bg-black/10 p-4">
        <div class="flex items-center gap-3">
          <div
            class="w-8 h-8 border border-white/20 flex items-center justify-center flex-shrink-0 text-white text-[10px] font-bold font-mono"
          >
            {{ auth.user?.avatar }}
          </div>
          <Transition name="fade">
            <div v-if="sidebarOpen" class="flex-1 min-w-0">
              <p
                class="text-white text-xs font-bold uppercase truncate tracking-wide"
              >
                {{ auth.user?.name }}
              </p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <div class="w-1.5 h-1.5 bg-success rounded-full" />
                <p
                  class="text-white/70 text-[10px] font-bold uppercase tracking-wider"
                >
                  {{ auth.user?.role }}
                </p>
              </div>
            </div>
          </Transition>
          <button
            v-if="sidebarOpen"
            class="text-white/30 hover:text-white transition-none p-1"
            title="Logout"
            @click="logout"
          >
            <LogOut class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </aside>

    <!-- ======================== WORKSPACE (MAIN) ======================== -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
      <!-- Console Header -->
      <header
        class="flex items-center gap-4 px-4 lg:px-6 py-3 bg-white border-b border-slate-200 z-10 min-h-[70px]"
      >
        <!-- Mobile Trigger -->
        <button class="lg:hidden btn-icon" @click="mobileOpen = !mobileOpen">
          <Menu class="w-5 h-5" />
        </button>

        <!-- Sidebar Controller -->
        <button
          class="hidden lg:flex btn btn-secondary !p-1.5"
          :title="sidebarOpen ? 'Contract' : 'Expand'"
          @click="sidebarOpen = !sidebarOpen"
        >
          <Menu class="w-3.5 h-3.5" />
        </button>

        <div class="flex-1">
          <!-- Page title removed as requested -->
        </div>

        <!-- Search Module -->
        <div class="hidden md:block">
          <div class="input-wrapper w-64">
            <input placeholder="Search..." class="input !py-1 text-[11px]" />
          </div>
        </div>

        <!-- System Alerts -->
        <button
          id="notif-bell"
          class="btn btn-secondary !p-2 relative"
          @click="notifOpen = !notifOpen"
        >
          <Bell class="w-4 h-4" />
          <span
            v-if="unreadCount > 0"
            class="absolute -top-1 -right-1 w-4 h-4 bg-danger text-white text-[9px] font-bold flex items-center justify-center border border-white"
          >
            {{ unreadCount }}
          </span>
        </button>
      </header>

      <!-- Active Workspace -->
      <main
        class="flex-1 overflow-y-auto scrollbar-thin p-6 animate-fade-in bg-clinical/50"
      >
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
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.1s linear;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.page-enter-active,
.page-leave-active {
  transition: opacity 0.15s linear;
}
.page-enter-from,
.page-leave-to {
  opacity: 0;
}

.animate-fade-in {
  animation: fadeIn 0.2s linear;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>

<style scoped>
.fade-text-enter-active,
.fade-text-leave-active {
  transition:
    opacity 0.15s ease,
    width 0.3s ease;
}
.fade-text-enter-from,
.fade-text-leave-to {
  opacity: 0;
}
</style>
