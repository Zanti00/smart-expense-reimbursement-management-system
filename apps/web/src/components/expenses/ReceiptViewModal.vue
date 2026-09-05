<script setup>
import { computed, ref } from "vue";
import { formatAmount, formatDate } from "@/utils/formatters";
import { canEditReceipt, canDeleteReceipt } from "@/utils/receiptUtils";
import BaseReceiptDetailModal from "@/components/base/BaseReceiptDetailModal.vue";
import ImagePreviewModal from "@/components/base/ImagePreviewModal.vue";
import { Pencil, Trash2 } from "lucide-vue-next";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  receipt: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "delete", "edit"]);

const canEdit = computed(() => canEditReceipt(props.receipt));
const canDelete = computed(() => canDeleteReceipt(props.receipt));
const isImagePreviewOpen = ref(false);

function close() {
  emit("update:modelValue", false);
}

function editReceipt() {
  emit("edit", props.receipt);
  close();
}

/** Normalize receipt to the shared shape */
const normalizedReceipt = computed(() => {
  if (!props.receipt) return null;
  return {
    imageUrl: props.receipt.thumbnail || null,
    invoiceNumber: props.receipt.invoiceNumber || props.receipt.fileName,
    date: formatDate(props.receipt.date || props.receipt.transaction_date || props.receipt.transactionDate),
    vendor: props.receipt.vendorName || "Unknown Vendor",
    category: props.receipt.category || "—",
    tin: props.receipt.tin || "—",
    vatClassification: props.receipt.vatClassification || "—",
    currency: props.receipt.currency || "PHP",
    items: props.receipt.items || [],
    amount: props.receipt.amount || 0,
    vat: props.receipt.vatAmount || 0,
    status: props.receipt.status || "pending",
  };
});
</script>

<template>
  <BaseReceiptDetailModal
    :is-open="modelValue && !!receipt"
    :receipt="normalizedReceipt"
    @close="close"
  >
    <template #actions>
      <div class="flex gap-2">
        <button
          v-if="canEdit"
          @click="editReceipt"
          class="btn btn-secondary flex-1"
        >
          <Pencil class="w-4 h-4" /> Edit
        </button>
        <button
          v-if="canDelete"
          @click="emit('delete', receipt.id); close();"
          class="btn btn-danger !px-3"
        >
          <Trash2 class="w-4 h-4" />
        </button>
      </div>
    </template>
  </BaseReceiptDetailModal>

  <ImagePreviewModal
    v-if="receipt?.thumbnail"
    :model-value="isImagePreviewOpen"
    @update:model-value="isImagePreviewOpen = $event"
    :src="receipt.thumbnail"
    :alt="receipt?.vendorName || 'Receipt'"
    :title="receipt?.invoiceNumber || receipt?.fileName || 'Receipt Image'"
  />
</template>
