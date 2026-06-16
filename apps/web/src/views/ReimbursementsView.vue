<script setup>
import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import BaseButton from "@/components/base/BaseButton.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import ReimbursementDetailsModal from "@/components/reimbursements/ReimbursementDetailsModal.vue";
import ReceiptDetailsModal from "@/components/reimbursements/ReceiptDetailsModal.vue";
import DecisionConfirmationModal from "@/components/reimbursements/DecisionConfirmationModal.vue";
import ReimbursementsTable from "@/components/reimbursements/ReimbursementsTable.vue";
import { formatPeso } from "@/utils/formatters";
import { useReimbursementFilters, normalizeStatus } from "@/composables/reimbursements/useReimbursementFilters";
import { useReimbursementDetails } from "@/composables/reimbursements/useReimbursementDetails";
import { useReimbursementDecisions } from "@/composables/reimbursements/useReimbursementDecisions";
import {
  Plus,
  Activity,
  ShieldCheck,
  XCircle,
  Clock,
  Wallet,
  Send,
  CreditCard,
} from "lucide-vue-next";

const store = useReimbursementStore();
const auth = useAuthStore();
const router = useRouter();
const { addToast } = useToast();

const statusFilters = computed(() =>
  auth.isAdmin
    ? ["All", "Pending", "Approved", "Rejected", "Granted"]
    : ["All", "Pending", "Approved", "Rejected", "Granted"],
);

const employeeReimbursementColumns = [
  { key: "reportDescription", label: "Report Description" },
  { key: "category", label: "Category" },
  { key: "amount", label: "Amount", align: "right" },
  { key: "dateSubmitted", label: "Date Submitted" },
  { key: "displayStatus", label: "Status", align: "center" },
  { key: "action", sortKey: "id", label: "Action", align: "center" },
];

const adminReimbursementColumns = [
  { key: "reportDescription", label: "Report Description" },
  { key: "category", label: "Category" },
  { key: "dateSubmitted", label: "Date Submitted" },
  { key: "submittedBy", label: "Submitted By" },
  { key: "displayStatus", label: "Status", align: "center" },
  { key: "action", sortKey: "id", label: "Action", align: "center" },
];

const reimbursementColumns = computed(() =>
  auth.isAdmin ? adminReimbursementColumns : employeeReimbursementColumns,
);

const {
  searchQuery,
  activeStatus,
  activeCategory,
  sortKey,
  sortDirection,
  pageSize,
  currentPage,
  categoryFilters,
  paginatedTableRows,
  sortedTableRows,
  toggleSort,
} = useReimbursementFilters(store);

const reimbursementKpis = computed(() => [
  {
    label: "Pending",
    value: store.items.filter(
      (item) => normalizeStatus(item.status) === "pending",
    ).length,
    sub: "Awaiting review",
    icon: Clock,
    iconBg: "bg-amber-500/10",
    iconColor: "text-amber-500",
    accent: "bg-amber-500",
  },
  {
    label: "Approved",
    value: store.items.filter((item) => item.status === "approved").length,
    sub: "Ready for payment",
    icon: ShieldCheck,
    iconBg: "bg-emerald-500/10",
    iconColor: "text-emerald-500",
    accent: "bg-emerald-500",
  },
  {
    label: "Rejected",
    value: store.items.filter(
      (item) => normalizeStatus(item.status) === "rejected",
    ).length,
    sub: "Denied claims",
    icon: XCircle,
    iconBg: "bg-red-500/10",
    iconColor: "text-red-500",
    accent: "bg-red-500",
  },
  {
    label: "Granted",
    value: store.items.filter(
      (item) => normalizeStatus(item.status) === "granted",
    ).length,
    sub: "Settled claims",
    icon: CreditCard,
    iconBg: "bg-blue-900/10",
    iconColor: "text-blue-900",
    accent: "bg-blue-900",
  },
  {
    label: "Total Amount",
    value: formatPeso(store.totalAmount),
    sub: "All claims",
    icon: Wallet,
    iconBg: "bg-accent/10",
    iconColor: "text-accent",
    accent: "bg-accent",
  },
  {
    label: "Total Submitted",
    value: store.items.length,
    sub: "Claim records",
    icon: Send,
    iconBg: "bg-slate-500/10",
    iconColor: "text-slate-500",
    accent: "bg-slate-500",
  },
]);

const {
  viewingRecord,
  receiptDetailsOpen,
  selectedReceipt,
  reviewerNotes,
  pendingReceiptDecision,
  isReceiptReviewSubmitting,
  modalLoading,
  closeDetails,
  viewReceiptDetails,
  openDetails,
  requestReceiptDecision,
  cancelReceiptDecision,
  isReceiptDecisionPending,
  confirmReceiptDecision,
} = useReimbursementDetails(store, addToast);

const {
  approvingId,
  rejectingId,
  rejectionComment,
  confirmPassword,
  isReviewSubmitting,
  openApproveModal,
  cancelApprove,
  confirmApprove,
  openRejectModal,
  cancelReject,
  confirmReject,
} = useReimbursementDecisions(store, addToast, viewingRecord);

onMounted(() => store.fetchAll());
</script>

<template>
  <div class="flex flex-col gap-6 font-sans animate-fade-up">
    <!-- ── Page Header ── -->
    <div
      class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Activity class="w-3.5 h-3.5 text-accent" />
          <span class="section-label">Claim Records</span>
        </div>
        <h1
          class="font-heading text-2xl font-bold leading-tight text-slate-800"
        >
          Reimbursements
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Manage and track all submitted expense claims.
        </p>
      </div>
    </div>

    <!-- ── KPI Cards ── -->
    <BaseKpiGrid
      :kpis="reimbursementKpis"
      gridClasses="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="6"
    />
    <BaseUtilityToolbar
      v-model:search="searchQuery"
      v-model:status-value="activeStatus"
      v-model:category-value="activeCategory"
      :statuses="statusFilters"
      :categories="categoryFilters"
    >
      <template v-if="!auth.isAdmin" #actions>
        <BaseButton
          id="new-reimbursement-btn"
          variant="cta"
          class="min-h-[42px] w-full sm:w-fit"
          @click="router.push('/reimbursements/new')"
        >
          <Plus class="w-4 h-4" /> New Request
        </BaseButton>
      </template>
    </BaseUtilityToolbar>

    <!-- ── Main Table ── -->
    <ReimbursementsTable
      :is-loading="store.isLoading"
      :rows="paginatedTableRows"
      :total-rows="sortedTableRows.length"
      :columns="reimbursementColumns"
      :sort-key="sortKey"
      :sort-direction="sortDirection"
      :is-admin="auth.isAdmin"
      v-model:current-page="currentPage"
      :page-size="pageSize"
      @toggle-sort="toggleSort"
      @view-details="openDetails"
    />

    <!-- ── Record Detail Panel (Modal) ── -->
    <ReimbursementDetailsModal
      :viewing-record="viewingRecord"
      :receipt-details-open="receiptDetailsOpen"
      :modal-loading="modalLoading"
      @close="closeDetails"
      @view-receipt-details="viewReceiptDetails"
      @reject="openRejectModal"
      @approve="openApproveModal"
    />

    <!-- ── Single Receipt Details Modal ── -->
    <ReceiptDetailsModal
      :is-open="!!viewingRecord && receiptDetailsOpen"
      :receipt="selectedReceipt"
      v-model:reviewer-notes="reviewerNotes"
      :pending-decision-action="isReceiptDecisionPending(selectedReceipt) ? pendingReceiptDecision?.action : null"
      :is-submitting="isReceiptReviewSubmitting"
      @close="receiptDetailsOpen = false"
      @close-all="closeDetails"
      @request-decision="action => requestReceiptDecision(selectedReceipt, action)"
      @cancel-decision="cancelReceiptDecision"
      @confirm-decision="confirmReceiptDecision"
    />

    <!-- Approve and Reject Confirmation Modal -->
    <DecisionConfirmationModal
      :is-open="!!approvingId || !!rejectingId"
      :mode="approvingId ? 'approve' : 'reject'"
      :is-submitting="isReviewSubmitting"
      v-model:password="confirmPassword"
      v-model:comment="rejectionComment"
      @close="approvingId ? cancelApprove() : cancelReject()"
      @confirm="approvingId ? confirmApprove() : confirmReject()"
    />
  </div>
</template>

<style scoped>
.modal-enter-active {
  transition: opacity 0.2s ease-out;
}
.modal-leave-active {
  transition: opacity 0.15s ease-in;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active > div {
  animation: modal-pop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes modal-pop {
  from {
    transform: scale(0.95) translateY(8px);
    opacity: 0;
  }
  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}
</style>
