<script setup>
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { formatPeso, formatAmount } from "@/utils/formatters";
import StatusBadge from "@/components/base/StatusBadge.vue";
import {
  X,
  FileText,
  Download,
  Eye,
  XCircle,
  CheckCircle,
} from "lucide-vue-next";

const props = defineProps({
  viewingRecord: {
    type: Object,
    default: null,
  },
  receiptDetailsOpen: {
    type: Boolean,
    default: false,
  },
  modalLoading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "close",
  "view-receipt-details",
  "reject",
  "approve",
]);

const auth = useAuthStore();

const activeReceiptItems = computed(() => props.viewingRecord?.receipts || []);
const isOwnSubmission = computed(() => {
  const currentUserId = auth.user?.id;
  const ownerId =
    props.viewingRecord?.user?.id ??
    props.viewingRecord?.user_id ??
    props.viewingRecord?.userId ??
    props.viewingRecord?.submitted_by;

  return (
    currentUserId !== null &&
    currentUserId !== undefined &&
    ownerId !== null &&
    ownerId !== undefined &&
    String(currentUserId) === String(ownerId)
  );
});

function normalizeStatus(status) {
  const normalized = String(status || "").toLowerCase();
  const statusMap = {
    submitted: "pending",
    review: "pending",
    draft: "pending",
    reject: "rejected",
    rejected: "rejected",
    paid: "granted",
    processing: "processing",
  };
  return statusMap[normalized] || normalized;
}

function statusLabel(status) {
  const labels = {
    pending: "Pending",
    approved: "Approved",
    rejected: "Rejected",
    granted: "Granted",
    processing: "Processing",
  };
  return labels[normalizeStatus(status)] || "Pending";
}

function getCutoffPeriod(date) {
  const submittedDate = new Date(date);
  if (Number.isNaN(submittedDate.getTime())) return date || "--";

  return submittedDate.toLocaleDateString("en-US", {
    month: "short",
    year: "numeric",
  });
}

function categoryName(record) {
  return record?.expense_category?.name || record?.category?.name || record?.category || "Uncategorized";
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="viewingRecord && !receiptDetailsOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
    >
      <div
        class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      >
        <div
          class="flex items-center justify-between border-b border-slate-200 px-6 py-4"
        >
          <div>
            <h3 class="font-heading text-base font-bold text-slate-900">
              Reimbursement Details
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              Ref #{{ viewingRecord.id }}
            </p>
          </div>
          <button
            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
            title="Close details"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 scrollbar-thin">
          <div
            v-if="modalLoading"
            class="flex flex-col items-center justify-center py-20"
          >
            <p class="text-xs text-slate-400 mt-2">Loading details...</p>
          </div>
          <div v-else>
            <div class="mb-8 grid grid-cols-1 gap-x-5 gap-y-6 sm:grid-cols-2">
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Status</span>
                <div>
                  <StatusBadge :status="viewingRecord.status" />
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Submitted By</span>
                <span class="text-sm font-semibold text-slate-700">{{
                  viewingRecord.user?.name ||
                  viewingRecord.submitted_by_name ||
                  "Employee"
                }}</span>
              </div>
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Cutoff Period</span>
                <span class="text-sm font-semibold text-slate-700">{{
                  viewingRecord.cutoff_period ||
                  getCutoffPeriod(viewingRecord.date)
                }}</span>
              </div>
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Total Amount</span>
                <span class="font-heading text-xl font-bold text-primary">{{
                  formatAmount(viewingRecord.amount || 0, viewingRecord.currency || 'PHP')
                }}</span>
              </div>
              <div class="flex flex-col gap-1 sm:col-span-2">
                <span class="kpi-label text-slate-400">Description</span>
                <span
                  class="text-sm font-semibold text-slate-700 leading-relaxed"
                  >{{ viewingRecord.description }}</span
                >
              </div>

              <!-- Admin Notes for Rejected Requests (Employee Side) -->
              <div
                v-if="
                  !auth.isAdmin &&
                  normalizeStatus(viewingRecord.status) === 'rejected' &&
                  viewingRecord.admin_notes
                "
                class="flex flex-col gap-1.5 sm:col-span-2 mt-2"
              >
                <span
                  class="kpi-label text-slate-400 flex items-center gap-1.5"
                >
                  Rejection Reason
                </span>
                <div class="rounded-lg border p-3.5 shadow-sm">
                  <p class="text-sm font-semibold leading-relaxed">
                    {{ viewingRecord.admin_notes }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Report File Attachment -->
            <div
              v-if="
                viewingRecord.report_url || viewingRecord.report_file_path
              "
              class="mb-8"
            >
              <h4 class="mb-2 text-xs font-semibold text-slate-500">
                Report Attachment
              </h4>
              <div
                class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 transition-colors hover:border-slate-300"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <span
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-red-50 text-red-600"
                  >
                    <FileText class="h-5 w-5" />
                  </span>
                  <span class="truncate text-sm font-semibold text-slate-700"
                    >Reimbursement_Report.pdf</span
                  >
                </div>
                <a
                  :href="viewingRecord.report_url"
                  target="_blank"
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-accent transition-colors hover:bg-accent-50"
                  title="Download report"
                >
                  <Download class="h-4 w-4" />
                </a>
              </div>
            </div>

            <!-- Receipts Section -->
            <div>
              <div
                class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-2"
              >
                <h4 class="font-heading text-base font-bold text-slate-900">
                  Receipts ({{ activeReceiptItems.length }})
                </h4>
                <span class="text-xs font-medium text-slate-400"
                  >Each receipt is reviewed and decided individually</span
                >
              </div>

              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                  v-for="receipt in activeReceiptItems"
                  :key="receipt.id"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white transition-shadow hover:shadow-md flex flex-col"
                >
                  <div
                    class="aspect-[4/3] overflow-hidden bg-slate-100 flex items-center justify-center relative group"
                  >
                    <a
                      v-if="receipt.file_url"
                      :href="receipt.file_url"
                      target="_blank"
                      class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/50 text-white font-bold transition-all z-10 text-xs"
                      >View Full Size</a
                    >
                    <img
                      v-if="receipt.file_url"
                      :src="receipt.file_url"
                      alt="Scanned receipt"
                      class="h-full w-full object-cover object-top transition-transform duration-500 hover:scale-105"
                    />
                    <div
                      v-else
                      class="flex flex-col items-center justify-center text-slate-400 py-10 text-center px-4"
                    >
                      <FileText class="w-8 h-8 mb-2 opacity-50" />
                      <span class="text-xs font-medium">No Image Provided</span>
                    </div>
                  </div>
                  <div class="flex flex-col gap-3 p-4 flex-1 justify-between">
                    <div>
                      <div
                        class="flex items-start justify-between gap-3 mb-1"
                      >
                        <h5
                          class="truncate font-heading text-sm font-bold text-slate-900"
                        >
                          {{ receipt.vendor_name || "Receipt" }}
                        </h5>
                        <StatusBadge :status="receipt.status" />
                      </div>
                      <div
                        class="flex items-center gap-1.5 text-xs text-slate-400 mb-2"
                      >
                        <span
                          >Invoice: {{ receipt.invoice_number || "--" }}</span
                        >
                      </div>
                    </div>
                    <div
                      class="flex items-center justify-between gap-3 pt-2 border-t border-slate-50"
                    >
                      <span
                        class="inline-flex rounded-md bg-accent-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-accent"
                        >{{ categoryName(receipt) }}</span
                      >
                      <span
                        class="font-heading text-sm font-bold text-primary"
                        >{{ formatAmount(receipt.total_amount || 0, receipt.currency || "PHP") }}</span
                      >
                    </div>
                    <button
                      class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold transition-colors"
                      :class="receipt.status === 'processing' ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-accent-50 text-accent hover:bg-accent-100'"
                      :disabled="receipt.status === 'processing'"
                      @click="emit('view-receipt-details', receipt)"
                    >
                      <Eye v-if="receipt.status !== 'processing'" class="h-3.5 w-3.5" />
                      {{ receipt.status === 'processing' ? 'Processing...' : 'Details' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Admin Final Actions Footer -->
        <div
          v-if="
            auth.isAdmin &&
            viewingRecord &&
            normalizeStatus(viewingRecord.status) === 'pending'
          "
          class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3"
        >
          <p
            v-if="isOwnSubmission"
            class="mr-auto text-sm font-semibold text-danger"
          >
            You cannot process your own request.
          </p>
          <button
            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-bold text-red-600 transition-colors hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isOwnSubmission"
            @click="emit('reject', viewingRecord.id)"
          >
            <XCircle class="w-4 h-4" /> Reject Claim
          </button>
          <button
            class="btn btn-cta min-h-[42px] disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="isOwnSubmission"
            @click="emit('approve', viewingRecord.id)"
          >
            <CheckCircle class="w-4 h-4" /> Approve Claim
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
