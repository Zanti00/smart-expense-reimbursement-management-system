<script setup>
import { X, XCircle, ShieldCheck } from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";
import StatusBadge from "@/components/base/StatusBadge.vue";
import LiquidationReviewDetails from "@/components/liquidations/LiquidationReviewDetails.vue";

defineProps({
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
</script>

<template>
  <div
    v-if="isOpen && reviewingCase"
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-2 backdrop-blur-[1px] sm:p-4"
  >
    <div
      class="flex max-h-[calc(100dvh-1rem)] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl sm:max-h-[90vh]"
    >
      <header
        class="flex items-center justify-between px-4 py-4 border-b border-slate-200 bg-slate-50/80 sm:px-6"
      >
        <h2 class="text-base font-semibold text-slate-800">Liquidation Review</h2>
        <button
          class="inline-flex items-center justify-center w-10 h-10 transition-colors rounded-full text-slate-500 hover:bg-slate-100 hover:text-danger"
          type="button"
          title="Close review"
          @click="$emit('close')"
        >
          <X class="h-5 w-5 stroke-[1.75]" />
        </button>
      </header>

      <LiquidationReviewDetails
        :reviewing-case="reviewingCase"
        :review-status="reviewStatus"
        :review-outstanding-balance="reviewOutstandingBalance"
        :review-receipts="reviewReceipts"
        :get-file-url="getFileUrl"
        :format-date-only="formatDateOnly"
        @view-receipt="$emit('view-receipt', $event)"
      />

      <footer class="relative px-6 py-4 bg-white border-t border-slate-200">
        <div
          v-if="
            reviewingCase.status !== 'Liquidated' &&
            reviewingCase.status !== 'Rejected'
          "
          class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="text-sm font-semibold text-slate-500">
            Accepted receipts total:
            <span class="font-bold text-primary">{{
              formatPeso(acceptedReviewTotal)
            }}</span>
          </div>
          <p
            v-if="isReviewingOwnLiquidation"
            class="text-sm font-semibold text-danger"
          >
            You cannot process your own liquidation settlement.
          </p>
          <div class="flex gap-2">
            <button
              class="inline-flex items-center justify-center gap-2 px-5 text-sm font-bold text-red-700 transition-colors border border-red-200 rounded-lg min-h-11 bg-red-50 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="isReviewingOwnLiquidation"
              @click="$emit('reject')"
            >
              <XCircle class="w-4 h-4" />
              Reject
            </button>
            <button
              class="btn btn-cta min-h-[42px] disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="isReviewingOwnLiquidation"
              @click="$emit('approve')"
            >
              <ShieldCheck class="w-4 h-4" />
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
          <div
            v-if="reviewingCase.adminNote"
            class="max-w-md text-xs italic text-slate-500"
          >
            Note: "{{ reviewingCase.adminNote }}"
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>
