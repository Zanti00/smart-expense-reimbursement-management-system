<script setup>
import { ref, computed, onBeforeUnmount, onMounted } from "vue";
import { useRouter } from "vue-router";
import { usePolicyStore } from "@/stores/policy";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useToast } from "@/composables/useToast";
import { Activity, Save, Send } from "lucide-vue-next";

// Components
import ReceiptsManagementHeader from "@/components/reimbursements/ReceiptsManagementHeader.vue";
import ScannedReceiptsList from "@/components/reimbursements/ScannedReceiptsList.vue";
import MetaAndAttachments from "@/components/reimbursements/MetaAndAttachments.vue";
import ReimbursementSummaryPanel from "@/components/reimbursements/ReimbursementSummaryPanel.vue";
import ReimbursementFormHeader from "@/components/reimbursements/ReimbursementFormHeader.vue";
import ReimbursementFormEmptyState from "@/components/reimbursements/ReimbursementFormEmptyState.vue";
import ConfirmModal from "@/components/base/ConfirmModal.vue";

// Composables
import { useReceiptUploads } from "@/composables/reimbursements/useReceiptUploads";
import { useReimbursementSubmit } from "@/composables/reimbursements/useReimbursementSubmit";
import { useUnsavedChanges } from "@/composables/useUnsavedChanges";

// Utils
import {
  tinFor,
  cleanName,
  normalizeVatClassification,
  receiptFinancials,
  getItems,
} from "@/utils/receiptUtils";

const props = defineProps({
  forwardedReceipts: {
    type: Array,
    default: () => [],
  },
  id: {
    type: [String, Number],
    default: null,
  },
});
const emit = defineEmits(["submitted", "close"]);

const policyStore = usePolicyStore();
const reimbursementStore = useReimbursementStore();
const { addToast } = useToast();
const router = useRouter();

// State
const cutoffPeriod = ref("");
const reportFile = ref(null);
const fetching = ref(false);

// Form uploads and file management
const {
  localReceipts,
  receiptDrag,
  receiptInput,
  handleReceiptDrop,
  handleReceiptSelect,
  removeReceipt,
} = useReceiptUploads();

const receipts = computed(() => [
  ...props.forwardedReceipts,
  ...localReceipts.value,
]);

// Financials
const totalAmount = computed(() =>
  receipts.value.reduce((sum, r) => sum + (Number(r.amount) || 0), 0),
);
const totalVat = computed(() =>
  receipts.value.reduce((sum, r) => sum + (Number(r.tax) || 0), 0),
);
const totalSubtotal = computed(() => totalAmount.value - totalVat.value);

const canProceed = computed(
  () =>
    receipts.value.length >= 1 &&
    cutoffPeriod.value &&
    reportFile.value &&
    receipts.value.every((r) => !r.isUploading),
);

const isDirty = computed(() => {
  return receipts.value.length > 0 || cutoffPeriod.value !== "" || reportFile.value !== null;
});

const isSubmitted = ref(false);

const {
  showConfirmModal,
  handleConfirmLeave,
  handleCancelLeave,
  dismissWithConfirm
} = useUnsavedChanges(isDirty, isSubmitted);

const isEditMode = computed(() => !!props.id);

// Form submission
const { submitting, submitReimbursement, updateReimbursement } = useReimbursementSubmit(
  emit,
  router,
);

async function handleSubmit() {
  isSubmitted.value = true;
  try {
    let success = false;
    if (isEditMode.value) {
      success = await updateReimbursement(props.id, {
        receipts: receipts.value,
        cutoffPeriod: cutoffPeriod.value,
        reportFile: reportFile.value,
        totalAmount: totalAmount.value,
      });
    } else {
      success = await submitReimbursement({
        receipts: receipts.value,
        cutoffPeriod: cutoffPeriod.value,
        reportFile: reportFile.value,
        totalAmount: totalAmount.value,
      });
    }
    if (!success) {
      isSubmitted.value = false;
    }
  } catch (error) {
    isSubmitted.value = false;
    throw error;
  }
}

// Lifecycle
onMounted(async () => {
  policyStore.fetchAll();

  if (isEditMode.value) {
    fetching.value = true;
    try {
      const data = await reimbursementStore.fetchOne(props.id);
      cutoffPeriod.value = data.cutoff_period;
      reportFile.value = data.report_file_path;
      
      if (data.receipts && Array.isArray(data.receipts)) {
        localReceipts.value = data.receipts.map((r) => {
          const amount = Number(r.total_amount) || 0;
          const vatClassification = normalizeVatClassification(
            r.vat_classification,
          );
          const items = (r.items || []).map((item) => ({
            name: item.name,
            qty: item.quantity,
            price: Number(item.price),
          }));
          const amounts = receiptFinancials(
            { amount, items },
            vatClassification,
          );

          return {
            id: r.id,
            fileName: r.vendor_name || `Receipt-${r.id}`,
            merchantName: r.vendor_name,
            date: r.transaction_date,
            amount: amounts.gross,
            subtotal: amounts.subtotal.toFixed(2),
            tax: amounts.vat.toFixed(2),
            vatClassification: amounts.vatClassification,
            tin: r.tin,
            invoiceNumber: r.invoice_number,
            thumbnail: r.file_path,
            items,
            isUploading: false,
          };
        });
      }
    } catch (error) {
      addToast({
        message: "Failed to load reimbursement details.",
        type: "error",
      });
      router.push("/reimbursements");
    } finally {
      fetching.value = false;
    }
  }

  const forwarded = sessionStorage.getItem(
    "serms_forwarded_liquidation_receipts",
  );
  if (forwarded) {
    try {
      const parsed = JSON.parse(forwarded);
      localReceipts.value = parsed.map((r) => ({
        ...r,
        ...(() => {
          const amounts = receiptFinancials(r, r.vatClassification);

          return {
            amount: amounts.gross,
            subtotal: amounts.subtotal.toFixed(2),
            tax: amounts.vat.toFixed(2),
            vatClassification: amounts.vatClassification,
          };
        })(),
        invoiceNumber: r.invoiceNumber || r.id,
        tin: r.tin || tinFor(r),
        merchantName: r.merchantName || cleanName(r.fileName),
        location: r.location || "Metro Manila, Philippines",
        items:
          r.items ||
          getItems(r.category || "Food & Dining").map((name) => ({
            name,
            qty: 1,
            price: 0,
          })),
      }));
    } catch {
      localReceipts.value = [];
    } finally {
      sessionStorage.removeItem("serms_forwarded_liquidation_receipts");
    }
  }
});

onBeforeUnmount(() => {
  localReceipts.value.forEach((receipt) => {
    if (receipt.thumbnail?.startsWith("blob:")) {
      URL.revokeObjectURL(receipt.thumbnail);
    }
  });
});

//  Dismiss ─
function dismiss() {
  dismissWithConfirm(() => {
    emit("close");
    // If opened standalone (via route), go back
    if (!props.forwardedReceipts.length) router.back();
  });
}
</script>

<template>
  <div class="max-w-5xl mx-auto flex flex-col gap-6 pb-12 animate-fade-up">
    <input
      ref="receiptInput"
      type="file"
      class="hidden"
      accept=".jpg,.jpeg,.png,.pdf"
      multiple
      @change="handleReceiptSelect"
    />

    <!--  Page Header (standalone route mode only)  -->
    <ReimbursementFormHeader
      v-if="!forwardedReceipts.length"
      :is-edit="isEditMode"
      @dismiss="dismiss"
    />

    <!--  Loading State  -->
    <div
      v-if="fetching"
      class="border border-slate-200 bg-white rounded-xl flex min-h-[400px] flex-col items-center justify-center gap-4 p-16 text-center"
    >
      <Activity class="h-10 w-10 text-accent animate-spin" />
      <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">
        Loading request details...
      </p>
    </div>

    <template v-else>
      <!--  Success State  -->
      <!--  Alert Banner (forwarded mode)  -->
      <div
        v-if="forwardedReceipts.length"
        class="flex items-center gap-3 px-4 py-3 bg-accent-50 border border-accent/15 rounded-xl"
      >
        <Send class="w-4 h-4 text-accent flex-shrink-0" />
        <p class="text-sm font-semibold text-accent">
          {{ forwardedReceipts.length }} receipt{{
            forwardedReceipts.length !== 1 ? "s" : ""
          }}
          forwarded from My Expense and pre-filled below.
        </p>
      </div>

      <!--  Empty State (standalone + no upload yet)  -->
      <ReimbursementFormEmptyState
        v-if="receipts.length === 0 && !isEditMode"
        :receiptDrag="receiptDrag"
        @dragover="receiptDrag = true"
        @dragleave="receiptDrag = false"
        @drop="handleReceiptDrop"
        @click="receiptInput?.click()"
      />

      <template v-else>
        <!--  CARD 1: Upload Receipt Management  -->
        <ReceiptsManagementHeader
          :receipt-count="receipts.length"
          @add-receipts="receiptInput?.click()"
        />

        <!--  CARD 2: One Scanned Receipt Block Per Receipt  -->
        <ScannedReceiptsList
          :receipts="receipts"
          @remove-receipt="removeReceipt"
        />

        <!--  CARD 3: Meta & Attachments  -->
        <MetaAndAttachments
          v-model:cutoff-period="cutoffPeriod"
          v-model:report-file="reportFile"
        />

        <ReimbursementSummaryPanel
          :receipt-count="receipts.length"
          :report-file="reportFile"
          :cutoff-period="cutoffPeriod"
          :total-amount="totalAmount"
        />

        <!--  Footer Actions  -->
        <div class="flex justify-end gap-4 pb-4">
          <button
            class="btn p-3 !px-10 transition-all duration-200"
            :class="
              canProceed
                ? 'btn-primary'
                : 'bg-slate-200 text-slate-400 cursor-not-allowed opacity-70'
            "
            :disabled="!canProceed || submitting"
            @click="handleSubmit"
          >
            <Activity v-if="submitting" class="w-4 h-4 animate-spin" />
            <Save v-else class="w-4 h-4" />
            {{ submitting ? (isEditMode ? "Updating..." : "Submitting...") : (isEditMode ? "Update Reimbursement" : "Submit Reimbursement") }}
          </button>
        </div>
      </template>
    </template>

    <ConfirmModal
      :is-open="showConfirmModal"
      title="Unsaved Changes"
      message="You have unsaved changes. Are you sure you want to leave? All progress will be lost."
      confirm-text="Leave Page"
      cancel-text="Stay"
      :danger="true"
      @confirm="handleConfirmLeave"
      @close="handleCancelLeave"
    />
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s linear;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
