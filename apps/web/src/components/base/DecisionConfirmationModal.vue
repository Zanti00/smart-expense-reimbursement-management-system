<script setup>
import { ref, computed, watch } from "vue";
import BaseModal from "@/components/base/BaseModal.vue";
import { Activity, CheckCircle, XCircle, AlertTriangle } from "lucide-vue-next";

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String, // 'approve' | 'reject' | 'disburse'
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
  title: {
    type: String,
    default: "",
  },
  description: {
    type: String,
    default: "",
  },
  minCommentLength: {
    type: Number,
    default: 10,
  },
  maxCommentLength: {
    type: Number,
    default: 255,
  },
});

const emit = defineEmits([
  "update:password",
  "update:comment",
  "close",
  "confirm",
]);

const isConfirmStep = ref(false);

const localComment = computed({
  get: () => props.comment,
  set: (val) => emit("update:comment", val),
});

watch(
  () => props.isOpen,
  (open) => {
    if (!open) {
      isConfirmStep.value = false;
    }
  },
);

const isRejectNextDisabled = computed(() => {
  const len = (localComment.value || "").trim().length;
  return (
    len < props.minCommentLength ||
    len > props.maxCommentLength ||
    props.isSubmitting
  );
});

const config = computed(() => {
  if (props.mode === "approve") {
    return {
      icon: CheckCircle,
      iconWrapperClass: "bg-emerald-50 text-emerald-600 border border-emerald-200",
      title: props.title || "Approve Request",
      promptTitle: "Confirm Approval",
      promptMessage:
        props.description ||
        "Are you sure you want to approve this request? Please confirm that all details and supporting documents have been verified.",
      proceedBtnClass: "btn-primary",
    };
  }
  if (props.mode === "disburse") {
    return {
      icon: Activity,
      iconWrapperClass: "bg-accent/10 text-accent border border-accent/20",
      title: props.title || "Disburse Request",
      promptTitle: "Confirm Disbursement",
      promptMessage:
        props.description ||
        "Are you sure you want to disburse this request? Please confirm that funds are ready for release.",
      proceedBtnClass: "btn-primary",
    };
  }
  return {
    icon: XCircle,
    iconWrapperClass: "bg-red-50 text-red-600 border border-red-200",
    title: props.title || "Reject Request",
    promptTitle: "Confirm Rejection",
    promptMessage:
      props.description ||
      "Are you sure you want to reject this request? This action will decline the submission and cannot be undone.",
    proceedBtnClass: "btn-danger",
  };
});

function handleClose() {
  isConfirmStep.value = false;
  emit("close");
}

function handleRejectNext() {
  if (!isRejectNextDisabled.value) {
    isConfirmStep.value = true;
  }
}

function handleFinalConfirm() {
  emit("confirm");
}
</script>

<template>
  <BaseModal :isOpen="isOpen" @close="handleClose" contentClass="!p-0 overflow-hidden">
    <!-- Header -->
    <div
      class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3"
    >
      <div
        class="flex h-9 w-9 items-center justify-center rounded-lg"
        :class="
          isConfirmStep && mode === 'reject'
            ? 'bg-amber-50 text-amber-600 border border-amber-200'
            : config.iconWrapperClass
        "
      >
        <AlertTriangle
          v-if="isConfirmStep && mode === 'reject'"
          class="w-5 h-5"
        />
        <component :is="config.icon" v-else class="w-5 h-5" />
      </div>
      <div>
        <h3 class="font-heading text-sm font-bold text-slate-800">
          {{ isConfirmStep && mode === 'reject' ? config.promptTitle : config.title }}
        </h3>
        <p class="text-[11px] text-slate-500 font-medium">
          {{
            mode === 'reject' && !isConfirmStep
              ? 'Provide a rejection justification'
              : 'Action Confirmation'
          }}
        </p>
      </div>
    </div>

    <!-- Content Area -->
    <div class="p-5 flex flex-col gap-4">
      <!-- REJECT: Step 1 (Enter Rejection Comment) -->
      <template v-if="mode === 'reject' && !isConfirmStep">
        <p class="text-sm font-medium text-slate-600 leading-relaxed">
          Please provide a specific reason for rejecting this request. The applicant will be notified of this comment.
        </p>

        <div class="input-wrapper">
          <label class="input-label mb-1 block">
            Rejection Comment <span class="text-danger">*</span>
          </label>
          <textarea
            v-model="localComment"
            rows="3"
            :maxlength="maxCommentLength"
            class="input !font-sans resize-none"
            placeholder="Explain the reason for rejecting..."
          />
          <div
            class="text-[10px] font-bold uppercase tracking-widest flex justify-between mt-1"
          >
            <span
              v-if="(localComment || '').trim().length < minCommentLength"
              class="text-danger"
            >
              Minimum {{ minCommentLength }} characters required
            </span>
            <span v-else class="text-slate-400">
              Valid justification length
            </span>
            <span
              :class="
                (localComment || '').trim().length < minCommentLength ||
                (localComment || '').length > maxCommentLength
                  ? 'text-danger'
                  : 'text-accent'
              "
            >
              {{ (localComment || "").length }}/{{ maxCommentLength }}
            </span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-2 pt-2 border-t border-slate-100">
          <button
            class="btn btn-secondary min-h-9 px-4"
            type="button"
            :disabled="isSubmitting"
            @click="handleClose"
          >
            Cancel
          </button>
          <button
            class="btn btn-danger min-h-9 px-4"
            type="button"
            :disabled="isRejectNextDisabled"
            @click="handleRejectNext"
          >
            Confirm Reject
          </button>
        </div>
      </template>

      <!-- REJECT: Step 2 (Confirmation Popup) -->
      <template v-else-if="mode === 'reject' && isConfirmStep">
        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 space-y-2">
          <p class="text-sm font-semibold text-slate-800 leading-relaxed">
            {{ config.promptMessage }}
          </p>
          <div v-if="localComment" class="mt-2 pt-2 border-t border-amber-200/60">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-1">
              Rejection Reason:
            </p>
            <p class="text-xs text-slate-700 italic bg-white/70 rounded p-2 border border-amber-100 break-words">
              "{{ localComment }}"
            </p>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-2 pt-2 border-t border-slate-100">
          <button
            class="btn btn-secondary min-h-9 px-4"
            type="button"
            :disabled="isSubmitting"
            @click="isConfirmStep = false"
          >
            Cancel
          </button>
          <button
            class="btn btn-danger min-h-9 px-5"
            type="button"
            :disabled="isSubmitting"
            @click="handleFinalConfirm"
          >
            <span
              v-if="isSubmitting"
              class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin"
              aria-hidden="true"
            />
            {{ isSubmitting ? "Processing..." : "Proceed" }}
          </button>
        </div>
      </template>

      <!-- APPROVE or DISBURSE: Direct Confirmation Popup -->
      <template v-else>
        <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-2">
          <p class="text-sm font-medium text-slate-700 leading-relaxed">
            {{ config.promptMessage }}
          </p>
        </div>

        <div class="flex items-center justify-end gap-2 mt-2 pt-2 border-t border-slate-100">
          <button
            class="btn btn-secondary min-h-9 px-4"
            type="button"
            :disabled="isSubmitting"
            @click="handleClose"
          >
            Cancel
          </button>
          <button
            class="btn min-h-9 px-5"
            :class="config.proceedBtnClass"
            type="button"
            :disabled="isSubmitting"
            @click="handleFinalConfirm"
          >
            <span
              v-if="isSubmitting"
              class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin"
              aria-hidden="true"
            />
            {{ isSubmitting ? "Processing..." : "Proceed" }}
          </button>
        </div>
      </template>
    </div>
  </BaseModal>
</template>
