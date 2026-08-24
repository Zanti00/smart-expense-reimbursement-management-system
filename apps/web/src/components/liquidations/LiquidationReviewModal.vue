<script setup>
import { X, XCircle, ShieldCheck, Eye, FileText, Download } from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";
import StatusBadge from "@/components/base/StatusBadge.vue";

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  reviewingCase: {
    type: Object,
    default: null,
  },
  reviewStatus: {
    type: String,
    required: true,
  },
  reviewOutstandingBalance: {
    type: [Number, String],
    required: true,
  },
  reviewReceipts: {
    type: Array,
    default: () => [],
  },
  acceptedReviewTotal: {
    type: [Number, String],
    default: 0,
  },
  isReviewingOwnLiquidation: {
    type: Boolean,
    default: false,
  },
  getFileUrl: {
    type: Function,
    required: true,
  },
  formatDateOnly: {
    type: Function,
    required: true,
  },
});

defineEmits(["close", "view-receipt", "reject", "approve"]);

function normalizeStatus(status) {
  const normalized = String(status || "").toLowerCase();
  const statusMap = {
    submitted: "submitted",
    "under review": "under_review",
    "in review": "under_review",
    pending: "under_review",
    liquidated: "settled",
    settled: "settled",
    rejected: "rejected",
    approved: "decision",
  };
  return statusMap[normalized] || normalized;
}

function isStepCompleted(stepIndex) {
  const status = normalizeStatus(props.reviewStatus);
  const order = ["submitted", "under_review", "decision", "settled"];
  const currentIndex = order.indexOf(status);
  if (status === "rejected" && stepIndex <= 2) return true;
  return stepIndex <= currentIndex;
}

function isStepCurrent(stepIndex) {
  const status = normalizeStatus(props.reviewStatus);
  const order = ["submitted", "under_review", "decision", "settled"];
  const currentIndex = order.indexOf(status);
  if (status === "rejected") return stepIndex === 2;
  return stepIndex === currentIndex;
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="isOpen && reviewingCase"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <div
        class="relative bg-white w-full max-w-[960px] rounded-3xl shadow-[0_20px_40px_-10px_rgba(0,0,0,0.1)] flex flex-col overflow-hidden max-h-[96vh]"
        @click.stop
      >
        <!-- Close Button -->
        <button
          @click="$emit('close')"
          class="absolute top-2 right-2 text-slate-400 hover:bg-slate-100 transition-colors p-1.5 rounded-full flex items-center justify-center z-10"
        >
          <X class="w-4 h-4" />
        </button>

        <!-- Header -->
        <div class="px-6 pt-8 pb-4 border-b border-slate-100 shrink-0">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">
                Liquidation {{ reviewingCase.id }}
              </p>
              <h2 class="text-2xl font-bold text-slate-800">
                {{ formatPeso(reviewingCase.cashAdvanceAmount) }}
              </h2>
            </div>
            <StatusBadge :status="reviewStatus" />
          </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
          <!-- Timeline -->
          <section class="relative">
            <div class="absolute top-4 left-4 right-4 h-0.5 bg-slate-100"></div>

            <div class="flex justify-between gap-2">
              <!-- Step 1: Submitted -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div class="h-8 w-8 rounded-full bg-emerald-500 text-white flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Submitted</p>
                <p class="text-xs text-slate-700 font-medium truncate max-w-full px-1">
                  {{ reviewingCase.requestorName || "Employee" }}
                </p>
              </div>

              <!-- Step 2: Under Review -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isStepCompleted(1)
                      ? 'bg-emerald-500 text-white'
                      : isStepCurrent(1)
                        ? 'bg-amber-400 text-white animate-pulse'
                        : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isStepCompleted(1)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="isStepCurrent(1)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none" /></svg>
                  <span v-else class="text-[10px] font-bold">2</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Under Review</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="isStepCurrent(1) ? 'text-amber-600' : 'text-slate-700'"
                >
                  {{ isStepCurrent(1) ? 'In Progress' : isStepCompleted(1) ? 'Reviewed' : 'Awaiting' }}
                </p>
              </div>

              <!-- Step 3: Decision -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    normalizeStatus(reviewStatus) === 'rejected'
                      ? 'bg-red-500 text-white'
                      : isStepCompleted(2)
                        ? 'bg-emerald-500 text-white'
                        : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isStepCompleted(2) && normalizeStatus(reviewStatus) !== 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="normalizeStatus(reviewStatus) === 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  <span v-else class="text-[10px] font-bold">3</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Decision</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="[
                    normalizeStatus(reviewStatus) === 'rejected' ? 'text-red-600' : '',
                    isStepCompleted(2) && normalizeStatus(reviewStatus) !== 'rejected' ? 'text-emerald-600' : '',
                    !isStepCompleted(2) && normalizeStatus(reviewStatus) !== 'rejected' ? 'text-slate-400' : '',
                  ]"
                >
                  {{ normalizeStatus(reviewStatus) === 'rejected' ? 'Rejected' : isStepCompleted(2) ? 'Approved' : 'Awaiting' }}
                </p>
              </div>

              <!-- Step 4: Settled -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isStepCompleted(3)
                      ? 'bg-emerald-500 text-white'
                      : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isStepCompleted(3)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="text-[10px] font-bold">4</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Settled</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="isStepCompleted(3) ? 'text-emerald-600' : 'text-slate-400'"
                >
                  {{ isStepCompleted(3) ? 'Complete' : 'Pending' }}
                </p>
              </div>
            </div>
          </section>

          <!-- Details Grid -->
          <section class="grid grid-cols-2 gap-x-6 gap-y-3 pt-4 border-t border-slate-100">
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Employee Name</p>
              <p class="text-sm text-slate-700">{{ reviewingCase.requestorName || "--" }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Due Date</p>
              <p class="text-sm text-slate-700">{{ formatDateOnly(reviewingCase.dueDate) }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Outstanding Balance</p>
              <p class="text-sm font-semibold text-primary">{{ formatPeso(reviewOutstandingBalance) }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Advance Date</p>
              <p class="text-sm text-slate-700">{{ formatDateOnly(reviewingCase.dateOfAdvances) }}</p>
            </div>
          </section>

          <!-- Receipts -->
          <div>
            <h3 class="text-sm font-medium text-slate-700 mb-3">
              Receipts ({{ reviewReceipts.length }})
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <article
                v-for="receipt in reviewReceipts"
                :key="receipt.id"
                class="rounded-xl overflow-hidden border border-slate-200"
              >
                <!-- Receipt Image -->
                <div class="bg-slate-50 aspect-[4/3] flex items-center justify-center border-b border-slate-100 overflow-hidden">
                  <img
                    v-if="receipt.filePath"
                    :src="getFileUrl(receipt.filePath)"
                    class="h-full w-full object-cover object-top"
                    alt="Receipt"
                  />
                  <div v-else class="flex items-center gap-2 text-slate-300">
                    <FileText class="w-4 h-4" />
                    <span class="text-xs">No image</span>
                  </div>
                </div>

                <!-- Receipt Info -->
                <div class="p-3 space-y-2.5">
                  <div class="flex justify-between items-start gap-2">
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-slate-800 truncate">
                        {{ receipt.merchantName || "Receipt" }}
                      </p>
                      <p class="text-xs text-slate-400 mt-0.5">
                        {{ receipt.location || "--" }}
                      </p>
                    </div>
                    <StatusBadge :status="receipt.decision === 'accepted' ? 'approved' : receipt.decision" />
                  </div>

                  <div class="flex justify-between items-center">
                    <span class="text-[10px] font-medium uppercase tracking-wide text-primary bg-primary/5 px-2 py-0.5 rounded">
                      {{ receipt.category || "General" }}
                    </span>
                    <span class="text-sm font-semibold text-slate-800">
                      {{ formatPeso(receipt.amount || 0) }}
                    </span>
                  </div>

                  <button
                    class="w-full py-2 bg-slate-50 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-100 transition-colors flex items-center justify-center gap-1.5"
                    type="button"
                    @click="$emit('view-receipt', receipt)"
                  >
                    <Eye class="w-3.5 h-3.5" />
                    Details
                  </button>
                </div>
              </article>
            </div>
          </div>

          <!-- Admin Notes -->
          <div
            v-if="reviewingCase.adminNote"
            class="pt-2 border-t border-slate-100"
          >
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">Admin Notes</p>
            <p class="text-sm text-slate-700">{{ reviewingCase.adminNote }}</p>
          </div>

          <!-- Report Attachment -->
          <div v-if="reviewingCase.reportFilePath" class="pt-2">
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">Report Attachment</p>
            <a
              :href="getFileUrl(reviewingCase.reportFilePath)"
              target="_blank"
              class="inline-flex items-center gap-2 text-sm text-primary hover:underline"
            >
              <FileText class="w-3.5 h-3.5" />
              <span>View / Download Report</span>
              <Download class="w-3 h-3" />
            </a>
          </div>
        </div>

        <!-- Footer -->
        <footer class="px-6 py-4 border-t border-slate-100 shrink-0">
          <div
            v-if="reviewingCase.status !== 'Liquidated' && reviewingCase.status !== 'Rejected'"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="text-sm font-semibold text-slate-500">
              Accepted receipts total:
              <span class="font-bold text-primary">{{ formatPeso(acceptedReviewTotal) }}</span>
            </div>
            <p
              v-if="isReviewingOwnLiquidation"
              class="text-sm font-semibold text-danger"
            >
              You cannot process your own liquidation settlement.
            </p>
            <div class="flex gap-2">
              <button
                class="flex-1 sm:flex-none py-2.5 px-5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                type="button"
                :disabled="isReviewingOwnLiquidation"
                @click="$emit('reject')"
              >
                Reject
              </button>
              <button
                class="flex-1 sm:flex-none py-2.5 px-5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors shadow-sm disabled:cursor-not-allowed disabled:opacity-60"
                type="button"
                :disabled="isReviewingOwnLiquidation"
                @click="$emit('approve')"
              >
                <ShieldCheck class="w-4 h-4 inline-block mr-1" />
                Approve
              </button>
            </div>
          </div>
          <div
            v-else
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="text-sm font-semibold text-slate-500">
              Liquidation status:
              <StatusBadge :status="reviewingCase.status" />
            </div>
          </div>
        </footer>
      </div>
    </div>
  </Transition>
</template>
