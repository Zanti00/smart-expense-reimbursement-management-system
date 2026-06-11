<script setup>
import { ref, computed, onBeforeUnmount, onMounted } from "vue";
import { useRouter } from "vue-router";
import { usePolicyStore } from "@/stores/policy";

// Components
import ReceiptsManagementHeader from "@/components/reimbursements/ReceiptsManagementHeader.vue";
import ScannedReceiptsList from "@/components/reimbursements/ScannedReceiptsList.vue";
import MetaAndAttachments from "@/components/reimbursements/MetaAndAttachments.vue";
import ReimbursementSummaryPanel from "@/components/reimbursements/ReimbursementSummaryPanel.vue";
import ReimbursementFormHeader from "@/components/reimbursements/ReimbursementFormHeader.vue";
import ReimbursementFormEmptyState from "@/components/reimbursements/ReimbursementFormEmptyState.vue";

// Composables
import { useReceiptUploads } from "@/composables/reimbursements/useReceiptUploads";
import { useReimbursementSubmit } from "@/composables/reimbursements/useReimbursementSubmit";

// Utils
import { tinFor, cleanName, vatOf, subtotalOf, getItems } from "@/utils/receiptUtils";

const props = defineProps({
  forwardedReceipts: {
    type: Array,
    default: () => [],
  },
});
const emit = defineEmits(["submitted", "close"]);

const policyStore = usePolicyStore();
const router = useRouter();

// State
const cutoffPeriod = ref("");
const reportFile = ref(null);

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
  receipts.value.reduce((sum, r) => sum + (Number(r.amount) || 0), 0)
);
const totalVat = computed(() =>
  receipts.value.reduce((sum, r) => sum + (Number(r.tax) || 0), 0)
);
const totalSubtotal = computed(() => totalAmount.value - totalVat.value);

const canProceed = computed(
  () =>
    receipts.value.length >= 1 &&
    cutoffPeriod.value &&
    reportFile.value &&
    receipts.value.every((r) => !r.isUploading)
);

// Form submission
const { submitting, submitReimbursement } = useReimbursementSubmit(emit, router);

async function handleSubmit() {
  await submitReimbursement({
    receipts: receipts.value,
    cutoffPeriod: cutoffPeriod.value,
    reportFile: reportFile.value,
    totalAmount: totalAmount.value,
  });
}

// Lifecycle
onMounted(() => {
  policyStore.fetchAll();
  const forwarded = sessionStorage.getItem("serms_forwarded_liquidation_receipts");
  if (forwarded) {
    try {
      const parsed = JSON.parse(forwarded);
      localReceipts.value = parsed.map((r) => ({
        ...r,
        invoiceNumber: r.invoiceNumber || r.id,
        tin: r.tin || tinFor(r),
        merchantName: r.merchantName || cleanName(r.fileName),
        location: r.location || "Metro Manila, Philippines",
        subtotal: r.subtotal || subtotalOf(r.amount || 0).toFixed(2),
        tax: r.tax || vatOf(r.amount || 0).toFixed(2),
        vatClassification: r.vatClassification || "vat",
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

//  Dismiss â”€
function dismiss() {
  emit("close");
  // If opened standalone (via route), go back
  if (!props.forwardedReceipts.length) router.back();
}

function viewMyClaims() {
  emit("close");
  router.push({ name: "Reimbursements" });
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
      @dismiss="dismiss"
    />

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
      v-if="receipts.length === 0"
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
        <button class="btn btn-secondary !px-8" @click="dismiss">Cancel</button>
        <button
          class="btn !px-10 transition-all duration-200"
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
          {{ submitting ? "Submitting..." : "Submit Reimbursement" }}
        </button>
      </div>
    </template>
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
