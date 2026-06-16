<script setup>
import BaseModal from "./BaseModal.vue";
import { AlertTriangle, Info } from "lucide-vue-next";

defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  message: {
    type: String,
    required: true,
  },
  confirmText: {
    type: String,
    default: "Confirm",
  },
  cancelText: {
    type: String,
    default: "Cancel",
  },
  danger: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["close", "confirm"]);
</script>

<template>
  <BaseModal :is-open="isOpen" @close="$emit('close')" max-width-class="max-w-sm">
    <div class="p-6">
      <div class="flex flex-col items-center text-center gap-4">
        <div
          class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0"
          :class="danger ? 'bg-red-50 text-danger' : 'bg-accent/10 text-accent'"
        >
          <AlertTriangle v-if="danger" class="w-6 h-6" />
          <Info v-else class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-lg font-bold text-slate-800" style="font-family: 'Poppins', sans-serif">
            {{ title }}
          </h3>
          <p class="text-sm text-slate-500 mt-2 leading-relaxed">
            {{ message }}
          </p>
        </div>
      </div>
      <div class="flex gap-3 mt-8">
        <button class="btn btn-secondary flex-1" @click="$emit('close')">
          {{ cancelText }}
        </button>
        <button
          class="btn flex-1"
          :class="danger ? 'bg-danger text-white hover:bg-red-700 border border-transparent' : 'btn-primary'"
          @click="$emit('confirm')"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
  </BaseModal>
</template>
