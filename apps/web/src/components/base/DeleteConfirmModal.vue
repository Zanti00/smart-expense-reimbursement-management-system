<script setup>
import { ref, watch } from 'vue';
import { Trash2, Eye, EyeOff } from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: "Delete Receipt"
  },
  message: {
    type: String,
    default: "This action cannot be undone."
  }
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const password = ref("");
const showPassword = ref(false);

watch(() => props.modelValue, (newVal) => {
  if (newVal) {
    password.value = "";
    showPassword.value = false;
  }
});

function handleConfirm() {
  if (password.value) {
    emit('confirm', password.value);
  }
}

function close() {
  emit('update:modelValue', false);
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="modelValue"
      class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] flex items-center justify-center p-4"
    >
      <div class="card w-full max-w-sm shadow-2xl overflow-hidden">
        <div
          class="px-6 py-4 flex items-center gap-3 border-b border-red-100 bg-red-50"
        >
          <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center">
            <Trash2 class="w-4 h-4 text-danger" />
          </div>
          <div>
            <h3 class="text-sm font-semibold text-slate-800" style="font-family: 'Poppins', sans-serif">
              {{ title }}
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ message }}
            </p>
          </div>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <p class="text-sm text-slate-600" style="font-family: 'Open Sans', sans-serif">
            Please enter your password to confirm this action.
          </p>
          <div class="input-wrapper">
            <label class="input-label">Password</label>
            <div class="relative">
              <input
                :type="showPassword ? 'text' : 'password'"
                class="input w-full pr-10"
                v-model="password"
                placeholder="Enter your password"
                @keyup.enter="handleConfirm"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                tabindex="-1"
              >
                <Eye v-if="!showPassword" class="w-4 h-4" />
                <EyeOff v-else class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div class="flex gap-2.5">
            <button class="btn btn-secondary flex-1 min-h-10 px-4" @click="close">
              Cancel
            </button>
            <button
              class="btn btn-danger flex-1 min-h-10 px-4"
              :disabled="password.length === 0"
              @click="handleConfirm"
            >
              Confirm Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
