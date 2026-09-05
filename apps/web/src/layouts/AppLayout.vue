<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useNotificationStore } from "@/stores/notification";
import { useToast } from "@/composables/useToast";
import { buildNavLinks } from "@/composables/useNavLinks";
import NotificationPanel from "@/components/base/NotificationPanel.vue";
import ToastNotification from "@/components/ToastNotification.vue";
import DuplicateReceiptModal from "@/components/expenses/DuplicateReceiptModal.vue";
import sbsiLogo from "@/assets/sbsi_logo.png";
import sbsiLogoShort from "@/assets/sbsi_logo_short.png";
import { Bell, LogOut, Menu, ChevronRight, Search } from "lucide-vue-next";

const auth = useAuthStore();
const notif = useNotificationStore();
const router = useRouter();
const route = useRoute();
const { addToast } = useToast();

onMounted(() => {
  if (route.query.message) {
    addToast({
      message: route.query.message,
      type: "success",
      duration: 5000,
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

const navLinks = buildNavLinks();

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
  <div
    class="flex h-screen overflow-hidden font-sans select-none bg-clinical text-slate-700"
  >
    <!-- ======================== SIDEBAR ======================== -->
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
        'fixed lg:relative inset-y-0 left-0 bg-primary',
        sidebarOpen ? 'w-[240px]' : 'w-16',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      ]"
    >
      <!-- Branding Module -->
      <div
        :class="[
          'flex items-center px-4 py-4 border-b border-white/10 min-h-[70px]',
          sidebarOpen ? 'justify-start' : 'justify-center',
        ]"
      >
        <img
          :src="sidebarOpen ? sbsiLogo : sbsiLogoShort"
          alt="SBSI"
          :class="[
            'object-contain transition-all duration-300 ease-out',
            sidebarOpen ? 'h-10 w-auto max-w-[190px]' : 'h-8 w-8',
          ]"
        />
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-0 py-4 overflow-y-auto scrollbar-thin space-y-0.5">
        <template v-for="(link, i) in navLinks" :key="i">
          <div v-if="link.divider" class="mx-4 my-4 border-t border-white/10" />
          <div v-else-if="link.header" class="px-4 pt-3 pb-1">
            <Transition name="fade">
              <span
                v-if="sidebarOpen"
                class="text-[10px] font-heading font-bold uppercase text-white/45 flex items-center gap-2"
              >
                <div class="w-1.5 h-1.5 rounded-full bg-accent opacity-70" />
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
            <Transition name="fade-text">
              <span
                v-if="sidebarOpen"
                class="flex-1 text-sm font-semibold truncate"
              >
                {{ link.name }}
              </span>
            </Transition>
            <Transition name="fade">
              <ChevronRight
                v-if="sidebarOpen && isActive(link.to)"
                class="w-3 h-3 ml-auto text-white/60"
              />
            </Transition>
          </RouterLink>
        </template>
      </nav>

      <!-- Operator Status -->
      <div class="p-4 border-t border-white/10 bg-black/10">
        <div class="flex items-center gap-3">
          <!-- <div
            class="w-8 h-8 rounded-md border border-white/10 bg-white/10 flex items-center justify-center flex-shrink-0 text-white text-[10px] font-bold font-mono shadow-sm"
          >
            {{ auth.user?.avatar }}
          </div> -->
          <Transition name="fade">
            <div v-if="sidebarOpen" class="flex-1 min-w-0">
              <p class="text-xs font-bold text-white truncate font-heading">
                {{ auth.user?.name }}
              </p>
              <div class="flex items-center gap-1.5 mt-0.5">
                <div class="w-1.5 h-1.5 bg-success rounded-full" />
                <p
                  class="text-white/60 text-[10px] font-heading font-bold uppercase"
                >
                  {{ auth.user?.role }}
                </p>
              </div>
            </div>
          </Transition>
          <button
            v-if="sidebarOpen"
            class="text-white/35 hover:text-white p-1.5 rounded-md hover:bg-white/10 flex-shrink-0 transition-all duration-200 ease-out"
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
      <!-- Console Header -->
      <header
        class="flex items-center gap-4 px-4 lg:px-6 py-3 bg-white/85 border-b border-black/5 shadow-sm backdrop-blur-md z-10 min-h-[70px]"
      >
        <!-- Mobile Trigger -->
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
        <!-- <div
          class="items-center hidden w-56 gap-2 px-3 py-2 transition-all duration-200 border rounded-md shadow-sm md:flex bg-white/80 border-black/5 hover:border-accent/30 focus-within:border-accent/50 focus-within:bg-white"
        >
          <Search class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
          <input
            placeholder="Search…"
            class="flex-1 min-w-0 text-sm bg-transparent outline-none text-slate-600 placeholder-slate-400"
            style="transition: none"
          />
        </div> -->

        <!-- System Alerts -->
        <!-- <button
          id="notif-bell"
          class="btn btn-secondary !p-2 relative"
          @click="notifOpen = !notifOpen"
        >
          <Bell class="w-4 h-4" />
          <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-danger text-white text-[9px] font-bold flex items-center justify-center rounded-full border-2 border-white shadow-sm"
          >
            {{ unreadCount }}
          </span>
        </button> -->
      </header>

      <!-- Active Workspace -->
      <main
        class="flex-1 p-6 overflow-y-auto scrollbar-thin animate-fade-in bg-clinical"
      >
        <RouterView v-slot="{ Component, route: viewRoute }">
          <div :key="viewRoute.path" class="animate-fade-in">
            <component :is="Component" />
          </div>
        </RouterView>
      </main>
    </div>

    <!-- Notification Sidebar -->
    <NotificationPanel :open="notifOpen" @close="notifOpen = false" />

    <!-- Global Toast Notifications -->
    <ToastNotification />

    <!-- Global Duplicate Receipt Modal -->
    <DuplicateReceiptModal />
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
