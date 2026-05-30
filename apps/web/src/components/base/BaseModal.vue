<script setup>
import { onMounted, onUnmounted } from "vue";

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  maxWidthClass: {
    type: String,
    default: "max-w-md",
  },
  zIndexClass: {
    type: String,
    default: "z-[60]",
  },
  contentClass: {
    type: String,
    default: "",
  }
});

const emit = defineEmits(["close"]);

function handleKeydown(e) {
  if (e.key === "Escape" && props.isOpen) {
    emit("close");
  }
}

onMounted(() => {
  document.addEventListener("keydown", handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener("keydown", handleKeydown);
});
</script>

<template>
  <Transition name="modal">
    <div
      v-if="isOpen"
      :class="['fixed inset-0 flex items-center justify-center bg-slate-950/35 p-4 backdrop-blur-sm transition-opacity', zIndexClass]"
      @click.self="$emit('close')"
    >
      <div
        :class="['w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl transform transition-all', maxWidthClass, contentClass]"
      >
        <slot></slot>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active { transition: opacity 0.2s ease-out; }
.modal-leave-active { transition: opacity 0.15s ease-in; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

.modal-enter-active > div {
  animation: modal-pop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes modal-pop {
  from { transform: scale(0.95) translateY(8px); opacity: 0; }
  to   { transform: scale(1) translateY(0); opacity: 1; }
}
</style>
