<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import BaseButton from "@/components/base/BaseButton.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import ReimbursementDetailsModal from "@/components/reimbursements/ReimbursementDetailsModal.vue";
import ReceiptDetailsModal from "@/components/reimbursements/ReceiptDetailsModal.vue";
import DecisionConfirmationModal from "@/components/base/DecisionConfirmationModal.vue";
import DeleteConfirmModal from "@/components/base/DeleteConfirmModal.vue";
import ReimbursementsTable from "@/components/reimbursements/ReimbursementsTable.vue";
import { formatPeso } from "@/utils/formatters";
import {
  useReimbursementFilters,
  normalizeStatus,
} from "@/composables/reimbursements/useReimbursementFilters";
import { useReimbursementDetails } from "@/composables/reimbursements/useReimbursementDetails";
import { useReimbursementDecisions } from "@/composables/reimbursements/useReimbursementDecisions";
import {
  Plus,
  ShieldCheck,
  XCircle,
  Clock,
  Wallet,
  Send,
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
  { key: "amount", label: "Amount", align: "right" },
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

const isDeleteModalOpen = ref(false);
const deletingRequestId = ref(null);
const newRequestFileInput = ref(null);

function handleNewRequest() {
  newRequestFileInput.value?.click();
}

function handleNewRequestFiles(e) {
  const files = e.target.files;
  if (files && files.length > 0) {
    // Store File objects in a module-level holder accessible by the form view
    window.__serms_pending_files = Array.from(files);
    router.push('/reimbursements/new');
  }
  if (e.target) e.target.value = "";
}

function handleEdit(row) {
  router.push({ name: "ReimbursementEdit", params: { id: row.id } });
}

function handleDelete(row) {
  deletingRequestId.value = row.id;
  isDeleteModalOpen.value = true;
}

async function confirmDelete(password) {
  if (!deletingRequestId.value) return;
  try {
    await store.deleteRequest(deletingRequestId.value, password);
    addToast({
      message: "Reimbursement request deleted successfully.",
      type: "success",
    });
    isDeleteModalOpen.value = false;
    deletingRequestId.value = null;
  } catch (error) {
    addToast({
      message: error.message || "Failed to delete request.",
      type: "error",
    });
  }
}

onMounted(() => store.fetchAll());
</script>

<template>
  <div class="flex flex-col gap-6 font-sans animate-fade-up">
    <!-- Hidden file input for New Request -->
    <input
      ref="newRequestFileInput"
      type="file"
      class="hidden"
      accept=".jpg,.jpeg,.png,.pdf"
      multiple
      @change="handleNewRequestFiles"
    />

    <!-- ── Page Header ── -->
    <div
      class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
      <div class="min-w-0">
        <h1
          class="text-2xl font-bold leading-tight font-heading text-slate-800"
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
      gridClasses="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="5"
    />
    <BaseUtilityToolbar
      v-model:search="searchQuery"
      v-model:status-value="activeStatus"
      v-model:category-value="activeCategory"
      :statuses="statusFilters"
      :categories="categoryFilters"
    >
      <template #actions>
        <BaseButton
          id="new-reimbursement-btn"
          variant="cta"
          class="min-h-[42px] w-full sm:w-fit"
          @click="handleNewRequest"
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
      @edit-request="handleEdit"
      @delete-request="handleDelete"
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
      :pending-decision-action="
        isReceiptDecisionPending(selectedReceipt)
          ? pendingReceiptDecision?.action
          : null
      "
      :is-submitting="isReceiptReviewSubmitting"
      @close="receiptDetailsOpen = false"
      @close-all="closeDetails"
      @request-decision="
        (action) => requestReceiptDecision(selectedReceipt, action)
      "
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

    <!-- Delete Confirmation Modal -->
    <DeleteConfirmModal
      v-model="isDeleteModalOpen"
      title="Delete Reimbursement Request"
      message="Are you sure you want to delete this pending reimbursement request? This action cannot be undone."
      @confirm="confirmDelete"
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
