<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount, onMounted, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { usePolicyStore } from "@/stores/policy";
import { useReceiptStore } from "@/stores/receipts";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useToast } from "@/composables/useToast";
import { Save, Send } from "lucide-vue-next";

// Components
import ReceiptsManagementHeader from "@/components/reimbursements/ReceiptsManagementHeader.vue";
import ScannedReceiptsList from "@/components/reimbursements/ScannedReceiptsList.vue";
import MetaAndAttachments from "@/components/reimbursements/MetaAndAttachments.vue";
import ReimbursementSummaryPanel from "@/components/reimbursements/ReimbursementSummaryPanel.vue";
import ReimbursementFormHeader from "@/components/reimbursements/ReimbursementFormHeader.vue";
import ReceiptQualityRejectionModal from "@/components/reimbursements/ReceiptQualityRejectionModal.vue";
import SegmentedReceiptUpload from "@/components/reimbursements/SegmentedReceiptUpload.vue";
import ConfirmModal from "@/components/base/ConfirmModal.vue";

// Composables
import { useReceiptUploads } from "@/composables/reimbursements/useReceiptUploads";
import { useReimbursementSubmit } from "@/composables/reimbursements/useReimbursementSubmit";
import { useUnsavedChanges } from "@/composables/useUnsavedChanges";

import {
  tinFor,
  cleanName,
  normalizeVatClassification,
  receiptFinancials,
  getItems,
  formatDateForInput,
} from "@/utils/receiptUtils";
import { getFileUrl } from "@/utils/fileUtils";

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
const receiptsStore = useReceiptStore();
const reimbursementStore = useReimbursementStore();
const { addToast } = useToast();
const router = useRouter();

// State
const cutoffPeriod = ref("");
const reportFile = ref(null);
const fetching = ref(false);
const storedForwardedReceipts = ref([]);
const forwardedSource = ref("My Expense");
const FORWARDED_RECEIPTS_KEY = "serms_forwarded_reimbursement_receipts";
const LEGACY_LIQUIDATION_RECEIPTS_KEY = "serms_forwarded_liquidation_receipts";
const summaryCurrency = ref("PHP");

// Form uploads and file management
const {
  localReceipts,
  receiptDrag,
  receiptInput,
  handleReceiptDrop,
  handleReceiptSelect,
  addReceiptFiles,
  removeReceipt,
  clearDraftReceipts,
  qualityRejection,
  clearQualityRejection,
  showSegmentedUpload,
  continueAnyway,
  submitWithForce,
  submitSegments,
} = useReceiptUploads();

const uploadMode = ref('single');

function handleRetake() {
  if (qualityRejection.value?.receiptId) {
    removeReceipt({ id: qualityRejection.value.receiptId });
  }
  clearQualityRejection();
  setTimeout(() => {
    receiptInput.value?.click();
  }, 100);
}

function triggerUpload(mode = 'single') {
  uploadMode.value = mode;
  receiptInput.value?.click();
}

function onReceiptSelect(e) {
  if (uploadMode.value === 'multi') {
    const files = Array.from(e.target.files || []);
    if (files.length > 0) {
      submitSegments(files);
    }
    if (e.target) e.target.value = '';
  } else {
    handleReceiptSelect(e);
  }
}

const receipts = computed(() => [
  ...props.forwardedReceipts,
  ...localReceipts.value,
]);

// Auto-sync summary currency if any receipt has a detected/selected currency
watch(
  receipts,
  (newReceipts) => {
    const foundCurrency = (newReceipts || []).find((r) => r && r.currency)?.currency;
    if (foundCurrency) {
      summaryCurrency.value = foundCurrency;
    }
  },
  { immediate: true, deep: true },
);

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
    (isEditMode.value || reportFile.value) &&
    receipts.value.every(
      (r) => !r.isUploading && !r.isProcessing && r.date && (isEditMode.value || r.categoryId),
    ),
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
const forwardedReceiptCount = computed(
  () => props.forwardedReceipts.length || storedForwardedReceipts.value.length,
);
const isForwardedMode = computed(() => forwardedReceiptCount.value > 0);
const isEmbeddedForwardedMode = computed(() => props.forwardedReceipts.length > 0);

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
    } else {
      clearDraftReceipts();
    }
  } catch (error) {
    isSubmitted.value = false;
    throw error;
  }
}

// Lifecycle
const handleOpenReceiptUpload = () => { receiptInput.value?.click(); };

onMounted(async () => {
  policyStore.fetchAll();
  receiptsStore.fetchCategories();
  
  window.addEventListener('open-receipt-upload', handleOpenReceiptUpload);

  // Pick up files passed from the Reimbursements page via global
  if (!props.id && !props.forwardedReceipts.length && window.__serms_pending_files) {
    const pendingFiles = window.__serms_pending_files;
    delete window.__serms_pending_files;
    nextTick(() => {
      addReceiptFiles(pendingFiles);
    });
  }

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
            fileType: r.file_type,
            merchantName: r.vendor_name,
            date: formatDateForInput(r.transaction_date),
            amount: amounts.gross,
            subtotal: amounts.subtotal.toFixed(2),
            tax: amounts.vat.toFixed(2),
            vatClassification: amounts.vatClassification,
            tin: r.tin,
            invoiceNumber: r.invoice_number,
            location: r.location || "",
            category: r.category?.name || data.expense_category?.name || "",
            categoryId: r.expense_category_id || data.expense_category_id || null,
            thumbnail: getFileUrl(r.file_url || r.file_path) || null,
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

  const forwarded =
    sessionStorage.getItem(FORWARDED_RECEIPTS_KEY) ||
    sessionStorage.getItem(LEGACY_LIQUIDATION_RECEIPTS_KEY);

  if (!isEditMode.value && forwarded) {
    try {
      const parsed = JSON.parse(forwarded);
      storedForwardedReceipts.value = parsed;
      forwardedSource.value = sessionStorage.getItem(FORWARDED_RECEIPTS_KEY)
        ? "My Expense"
        : "Liquidation";
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
        location: r.location || "",
        items:
          r.items ||
          getItems(r.category || "Expense").map((name) => ({
            name,
            qty: 1,
            price: 0,
          })),
      }));
    } catch {
      localReceipts.value = [];
    } finally {
      sessionStorage.removeItem(FORWARDED_RECEIPTS_KEY);
      sessionStorage.removeItem(LEGACY_LIQUIDATION_RECEIPTS_KEY);
    }
  }
});

onBeforeUnmount(() => {
  localReceipts.value.forEach((receipt) => {
    if (receipt.thumbnail?.startsWith("blob:")) {
      URL.revokeObjectURL(receipt.thumbnail);
    }
  });
  
  if (!isEditMode.value) {
    clearDraftReceipts();
  }
  
  window.removeEventListener('open-receipt-upload', handleOpenReceiptUpload);
});

//  Dismiss ─
function dismiss() {
  dismissWithConfirm(() => {
    emit("close");
    // If opened standalone (via route), go back
    router.back();
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
      @change="onReceiptSelect"
    />

    <!--  Page Header (standalone route mode only)  -->
    <ReimbursementFormHeader
      v-if="!isEmbeddedForwardedMode"
      :is-edit="isEditMode"
      @dismiss="dismiss"
    />

    <!--  Loading State  -->
    <div
      v-if="fetching"
      class="border border-slate-200 bg-white rounded-xl flex min-h-[400px] flex-col items-center justify-center gap-4 p-16 text-center"
    >
      <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">
        Loading request details...
      </p>
    </div>

    <template v-else>
      <!--  Success State  -->
      <!--  Alert Banner (forwarded mode)  -->
      <div
        v-if="isForwardedMode"
        class="flex items-center gap-3 px-4 py-3 bg-accent-50 border border-accent/15 rounded-xl"
      >
        <Send class="w-4 h-4 text-accent flex-shrink-0" />
        <p class="text-sm font-semibold text-accent">
          {{ forwardedReceiptCount }} receipt{{
            forwardedReceiptCount !== 1 ? "s" : ""
          }}
          forwarded from {{ forwardedSource }} and pre-filled below.
        </p>
      </div>

      <template v-if="receipts.length > 0 || isEditMode">
        <!--  CARD 1: Upload Receipt Management  -->
        <ReceiptsManagementHeader
          v-if="!isForwardedMode"
          :receipt-count="receipts.length"
          @add-receipts="triggerUpload('single')"
          @open-segmented-upload="triggerUpload('multi')"
        />

        <!-- Segmented Upload Panel -->
        <SegmentedReceiptUpload
          v-if="showSegmentedUpload"
          @submit-segments="
            (files) => {
              submitSegments(files);
              showSegmentedUpload = false;
            }
          "
          @cancel="showSegmentedUpload = false"
        />

        <!--  CARD 2: One Scanned Receipt Block Per Receipt  -->
        <ScannedReceiptsList
          :receipts="receipts"
          :categories="receiptsStore.categories"
          :allow-remove="!isForwardedMode"
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
          :currency="summaryCurrency"
        />

        <!--  Footer Actions  -->
        <div class="flex justify-end gap-4 pb-4">
          <button
            class="btn btn-cta min-h-[42px] w-full sm:w-fit disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!canProceed || submitting"
            @click="handleSubmit"
          >
            <span
              v-if="submitting"
              class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin"
              aria-hidden="true"
            />
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

    <ReceiptQualityRejectionModal
      :is-open="!!qualityRejection"
      :rejected-file="qualityRejection?.file ?? null"
      :rejection-code="qualityRejection?.rejectionCode ?? ''"
      :rejection-reason="qualityRejection?.rejectionReason ?? ''"
      :show-segmented-option="qualityRejection?.rejectionCode === 'too_small'"
      @retake="handleRetake"
      @upload-segmented="
        showSegmentedUpload = true;
        clearQualityRejection();
      "
      @continue-anyway="
        continueAnyway(qualityRejection?.file);
      "
      @close="clearQualityRejection"
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
