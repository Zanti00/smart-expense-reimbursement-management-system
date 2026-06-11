<script setup>
import { ref, computed } from "vue";
import BaseModal from "@/components/base/BaseModal.vue";
import { Activity, CheckCircle, XCircle, Eye, EyeOff } from "lucide-vue-next";

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String, // 'approve' | 'reject'
    required: true,
  },
  isSubmitting: {
    type: Boolean,
    default: false,
  },
  password: {
    type: String,
    default: "",
  },
  comment: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:password", "update:comment", "close", "confirm"]);

const showConfirmPassword = ref(false);

const localPassword = computed({
  get: () => props.password,
  set: (val) => emit("update:password", val),
});

const localComment = computed({
  get: () => props.comment,
  set: (val) => emit("update:comment", val),
});

const isApprove = computed(() => props.mode === "approve");

const config = computed(() => {
  if (isApprove.value) {
    return {
      icon: CheckCircle,
      iconWrapperClass: "bg-emerald-50 text-emerald-600",
      title: "Approve Reimbursement Claim",
      description: "Are you sure you want to approve this reimbursement claim? This action will set the status to approved. Please verify your identity by entering your password.",
      btnClass: "bg-emerald-600 hover:bg-emerald-700",
      btnText: "Confirm Approve",
      isConfirmDisabled: !props.password || props.isSubmitting,
    };
  }
  return {
    icon: XCircle,
    iconWrapperClass: "bg-red-50 text-red-600",
    title: "Reject Reimbursement Claim",
    description: "Please provide a reason for rejecting this claim and enter your current password to authorize this action.",
    btnClass: "bg-red-600 hover:bg-red-700",
    btnText: "Confirm Reject",
    isConfirmDisabled: props.comment.length < 10 || !props.password || props.isSubmitting,
  };
});

function handleClose() {
  showConfirmPassword.value = false;
  emit("close");
}

function handleConfirm() {
  if (!config.value.isConfirmDisabled) {
    emit("confirm");
  }
}
</script>

<template>
  <BaseModal :isOpen="isOpen" @close="handleClose" contentClass="!p-0">
    <div
      class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3"
    >
      <div
        class="flex h-9 w-9 items-center justify-center rounded-lg"
        :class="config.iconWrapperClass"
      >
        <component :is="config.icon" class="w-5 h-5" />
      </div>
      <h3 class="font-heading text-sm font-bold text-slate-800">
        {{ config.title }}
      </h3>
    </div>
    <div class="p-5 flex flex-col gap-4">
      <p class="text-sm font-medium text-slate-600 leading-relaxed">
        {{ config.description }}
      </p>

      <div v-if="!isApprove" class="input-wrapper">
        <label class="input-label mb-1 block"
          >Rejection Comment <span class="text-danger">*</span></label
        >
        <textarea
          v-model="localComment"
          rows="3"
          class="input !font-sans resize-none"
          placeholder="Explain the reason for rejecting this claim (minimum 10 characters)..."
        />
        <div
          class="text-[10px] font-bold uppercase tracking-widest flex justify-between mt-1"
          :class="
            localComment.length < 10 ? 'text-danger' : 'text-accent'
          "
        >
          <span>Requirement: >= 10 Chars</span>
          <span>{{ localComment.length }} / 10+</span>
        </div>
      </div>

      <div class="input-wrapper">
        <label class="input-label mb-1 block"
          >Password <span class="text-danger">*</span></label
        >
        <div class="relative">
          <input
            :type="showConfirmPassword ? 'text' : 'password'"
            class="input w-full pr-10"
            v-model="localPassword"
            placeholder="Enter your current password"
            @keyup.enter="handleConfirm"
          />
          <button
            type="button"
            @click="showConfirmPassword = !showConfirmPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
            tabindex="-1"
          >
            <Eye v-if="!showConfirmPassword" class="w-4 h-4" />
            <EyeOff v-else class="w-4 h-4" />
          </button>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 mt-2">
        <button
          class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
          type="button"
          @click="handleClose"
        >
          Cancel
        </button>
        <button
          class="inline-flex min-h-9 items-center justify-center rounded-lg px-4 text-xs font-bold text-white transition-colors disabled:cursor-not-allowed disabled:opacity-60"
          :class="config.btnClass"
          type="button"
          :disabled="config.isConfirmDisabled"
          @click="handleConfirm"
        >
          <Activity
            v-if="isSubmitting"
            class="w-3.5 h-3.5 animate-spin mr-1.5"
          />
          {{ config.btnText }}
        </button>
      </div>
    </div>
  </BaseModal>
</template>
