<script setup>
import { ref, watch } from "vue";
import { Activity } from "lucide-vue-next";
import BaseModal from "@/components/base/BaseModal.vue";
import BaseButton from "@/components/base/BaseButton.vue";

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  id: {
    type: [String, Number],
    required: true,
  },
  type: {
    type: String,
    default: "advance", // "advance" or "liquidation"
  }
});

const emit = defineEmits(["close", "confirm"]);

const rejectionComment = ref("");

// Reset comment when modal opens
watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    rejectionComment.value = "";
  }
});

function handleCancel() {
  emit("close");
}

function handleConfirm() {
  if (rejectionComment.value.length >= 10) {
    emit("confirm", rejectionComment.value);
  }
}
</script>

<template>
  <BaseModal
    :isOpen="isOpen"
    @close="handleCancel"
    contentClass="!p-0"
  >
    <div
      class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3"
    >
      <div
        class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-danger"
      >
        <Activity class="w-4 h-4" />
      </div>
      <h3 class="font-heading text-sm font-bold text-slate-800">
        Reject {{ type === "advance" ? "Advance Request" : "Liquidation" }}
      </h3>
    </div>
    <div class="p-5 flex flex-col gap-4">
      <p class="text-sm font-medium text-slate-600">
        Please provide a valid justification for rejecting Ref #{{ id }}.
      </p>
      <div class="input-wrapper">
        <textarea
          v-model="rejectionComment"
          rows="3"
          class="input !font-sans resize-none"
          :class="
            rejectionComment.length > 0 && rejectionComment.length < 10
              ? 'border-danger focus:border-danger focus:ring-danger'
              : ''
          "
          placeholder="REJECTION REASON (MIN 10 CHARACTERS)"
        />
        <div
          class="text-[10px] font-bold uppercase tracking-widest flex justify-between mt-1"
          :class="
            rejectionComment.length < 10 ? 'text-danger' : 'text-accent'
          "
        >
          <span>Requirement: >= 10 Chars</span>
          <span>{{ rejectionComment.length }} / 10+</span>
        </div>
      </div>
      <div class="flex items-center justify-end gap-2 mt-2">
        <BaseButton variant="secondary" @click="handleCancel">
          CANCEL
        </BaseButton>
        <BaseButton
          variant="primary"
          :disabled="rejectionComment.length < 10"
          class="!bg-danger !border-danger"
          @click="handleConfirm"
        >
          CONFIRM REJECTION
        </BaseButton>
      </div>
    </div>
  </BaseModal>
</template>
