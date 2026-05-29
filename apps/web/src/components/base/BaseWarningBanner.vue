<script setup>
import { ref } from "vue";
import { AlertTriangle, AlertCircle, Info, X } from "lucide-vue-next";

const props = defineProps({
  title: {
    type: String,
    default: "",
  },
  type: {
    type: String,
    default: "warning",
    validator: (value) => ["warning", "danger", "info"].includes(value),
  },
  dismissible: {
    type: Boolean,
    default: true,
  },
});

const emit = defineEmits(["close"]);
const isVisible = ref(true);

const themeConfig = {
  warning: {
    bg: "bg-amber-50/70 border-l-4 border-l-warning",
    text: "text-amber-900",
    titleColor: "text-amber-800",
    icon: AlertTriangle,
    iconColor: "text-warning",
  },
  danger: {
    bg: "bg-red-50/70 border-l-4 border-l-danger",
    text: "text-red-950",
    titleColor: "text-red-800",
    icon: AlertCircle,
    iconColor: "text-danger",
  },
  info: {
    bg: "bg-blue-50/70 border-l-4 border-l-blue-500",
    text: "text-blue-950",
    titleColor: "text-blue-800",
    icon: Info,
    iconColor: "text-blue-500",
  },
};

function dismiss() {
  isVisible.value = false;
  emit("close");
}
</script>

<template>
  <Transition name="slide-fade">
    <div
      v-if="isVisible"
      class="p-4 flex gap-3 shadow-sm select-none relative overflow-hidden backdrop-blur-sm"
      :class="[themeConfig[props.type].bg, themeConfig[props.type].text]"
    >
      <!-- Left side: Status Icon -->
      <component
        :is="themeConfig[props.type].icon"
        class="w-5 h-5 shrink-0 mt-0.5"
        :class="themeConfig[props.type].iconColor"
      />

      <!-- Middle: Content Area -->
      <div class="flex-1 space-y-1">
        <h4
          v-if="props.title"
          class="text-[11px] font-black uppercase tracking-wider mb-0.5"
          :class="themeConfig[props.type].titleColor"
        >
          {{ props.title }}
        </h4>
        <div class="text-xs leading-relaxed font-medium">
          <slot />
        </div>
      </div>

      <!-- Right side: Close Trigger -->
      <button
        v-if="props.dismissible"
        class="p-1 hover:bg-black/5 active:bg-black/10 rounded transition shrink-0 self-start"
        @click="dismiss"
      >
        <X class="w-4 h-4 opacity-70 hover:opacity-100" />
      </button>
    </div>
  </Transition>
</template>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-fade-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}

.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
