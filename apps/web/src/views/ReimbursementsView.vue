<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import { apiFetch } from "@/utils/apiFetch";
import BaseTable from "@/components/base/BaseTable.vue";
import StatusBadge from "@/components/base/StatusBadge.vue";
import BaseButton from "@/components/base/BaseButton.vue";
import BaseModal from "@/components/base/BaseModal.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BasePagination from "@/components/base/BasePagination.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import ReimbursementDetailsModal from "@/components/reimbursements/ReimbursementDetailsModal.vue";
import ReceiptDetailsModal from "@/components/reimbursements/ReceiptDetailsModal.vue";
import { formatPeso } from "@/utils/formatters";
import {
  Plus,
  FileText,
  Activity,
  ShieldCheck,
  X,
  CheckCircle,
  XCircle,
  Clock,
  Wallet,
  Send,
  CreditCard,
  Eye,
  EyeOff,
  Download,
  ArrowLeft,
  CalendarDays,
  Sparkles,
  MapPin,
  ChevronUp,
  ChevronDown,
  ChevronsUpDown,
} from "lucide-vue-next";

const store = useReimbursementStore();
const auth = useAuthStore();
const router = useRouter();
const { addToast } = useToast();

const rejectingId = ref(null);
const rejectionComment = ref("");
const viewingRecord = ref(null);
const approvingId = ref(null);
const receiptDetailsOpen = ref(false);
const selectedReceipt = ref(null);
const reviewerNotes = ref("");
const pendingReceiptDecision = ref(null);
const isReviewSubmitting = ref(false);
const modalLoading = ref(false);
const confirmPassword = ref("");
const showConfirmPassword = ref(false);
const searchQuery = ref("");
const activeStatus = ref("All");
const activeCategory = ref("All");
const sortKey = ref("");
const sortDirection = ref("asc");
const pageSize = 10;
const currentPage = ref(1);

const statusFilters = computed(() =>
  auth.isAdmin
    ? ["All", "Pending", "Approved", "Rejected", "Granted"]
    : ["All", "Pending", "Approved", "Rejected", "Granted"],
);
const employeeReimbursementColumns = [
  { key: "id", label: "Id" },
  { key: "reportDescription", label: "Report Description" },
  { key: "cutoffPeriod", label: "Cutoff Period" },
  { key: "category", label: "Category" },
  { key: "receiptQuantity", label: "Receipt Quantity", align: "center" },
  { key: "quantityReport", label: "Quantity Report", align: "center" },
  { key: "amount", label: "Amount", align: "right" },
  { key: "dateSubmitted", label: "Date Submitted" },
  { key: "displayStatus", label: "Status", align: "center" },
  { key: "action", sortKey: "id", label: "Action", align: "center" },
];

const adminReimbursementColumns = [
  { key: "id", label: "Id" },
  { key: "reportDescription", label: "Report Description" },
  { key: "cutoffPeriod", label: "Cutoff Period" },
  { key: "category", label: "Category" },
  { key: "dateSubmitted", label: "Date Submitted" },
  { key: "submittedBy", label: "Submitted By" },
  { key: "displayStatus", label: "Status", align: "center" },
  { key: "action", sortKey: "id", label: "Action", align: "center" },
];

const reimbursementColumns = computed(() =>
  auth.isAdmin ? adminReimbursementColumns : employeeReimbursementColumns,
);
const reimbursementColumnCount = computed(
  () => reimbursementColumns.value.length,
);
const reimbursementTableMinWidth = computed(() =>
  auth.isAdmin ? "min-w-[1040px]" : "min-w-[1320px]",
);

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

function normalizeStatus(status) {
  const normalized = String(status || "").toLowerCase();
  const statusMap = {
    submitted: "pending",
    review: "pending",
    draft: "pending",
    reject: "rejected",
    rejected: "rejected",
    paid: "granted",
  };

  return statusMap[normalized] || normalized;
}

function statusLabel(status) {
  const labels = {
    pending: "Pending",
    approved: "Approved",
    rejected: "Rejected",
    granted: "Granted",
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

const tableRows = computed(() =>
  store.items.map((item) => ({
    ...item,
    originalStatus: item.status,
    reportDescription: item.description,
    cutoffPeriod: getCutoffPeriod(item.date),
    receiptQuantity: Array.isArray(item.receipts)
      ? item.receipts.length
      : Number(item.receipts) || 0,
    quantityReport: 1,
    dateSubmitted: item.date,
    submittedBy: item.user?.name || item.submitted_by_name || "Employee",
    displayStatus: normalizeStatus(item.status),
    displayStatusLabel: statusLabel(item.status),
  })),
);

const categoryFilters = computed(() => [
  "All",
  ...new Set(tableRows.value.map((row) => row.category).filter(Boolean)),
]);

const receiptTemplates = [
  {
    merchantName: "Vikings Luxury Buffet",
    location: "SM Megamall, Mandaluyong City",
    category: "Food",
    invoicePrefix: "VIK",
    transactionDate: "January 5, 2025",
    items: [
      { name: "Buffet Dinner - 2 pax", quantity: 1, price: 2816 },
      { name: "Beverages", quantity: 2, price: 384 },
    ],
  },
  {
    merchantName: "Grab Transport",
    location: "Makati City",
    category: "Transportation",
    invoicePrefix: "GRB",
    transactionDate: "January 6, 2025",
    items: [
      { name: "Ride fare", quantity: 1, price: 640 },
      { name: "Platform fee", quantity: 1, price: 25 },
    ],
  },
  {
    merchantName: "National Book Store",
    location: "BGC, Taguig City",
    category: "Office Supplies",
    invoicePrefix: "NBS",
    transactionDate: "January 7, 2025",
    items: [
      { name: "Paper ream", quantity: 3, price: 780 },
      { name: "Pens and markers", quantity: 1, price: 420 },
    ],
  },
  {
    merchantName: "Ace Hardware",
    location: "Pasig City",
    category: "Equipment",
    invoicePrefix: "ACE",
    transactionDate: "January 8, 2025",
    items: [
      { name: "Tool kit", quantity: 1, price: 1850 },
      { name: "Safety gloves", quantity: 2, price: 520 },
    ],
  },
];

const activeReceiptItems = computed(() => viewingRecord.value?.receipts || []);

const filteredTableRows = computed(() => {
  let rows = tableRows.value;
  if (activeStatus.value !== "All") {
    rows = rows.filter(
      (row) => row.displayStatus === normalizeStatus(activeStatus.value),
    );
  }
  if (activeCategory.value !== "All") {
    rows = rows.filter((row) => row.category === activeCategory.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    rows = rows.filter((row) =>
      [
        row.id,
        row.reportDescription,
        row.cutoffPeriod,
        row.category,
        row.submittedBy,
        row.amount,
        row.dateSubmitted,
        row.displayStatus,
        row.displayStatusLabel,
      ].some((value) =>
        String(value || "")
          .toLowerCase()
          .includes(q),
      ),
    );
  }
  return rows;
});

const sortedTableRows = computed(() => {
  const rows = [...filteredTableRows.value];
  if (!sortKey.value) return rows;

  return rows.sort((a, b) => {
    const aValue = getSortValue(a, sortKey.value);
    const bValue = getSortValue(b, sortKey.value);
    if (aValue === bValue) return 0;
    const result = aValue > bValue ? 1 : -1;
    return sortDirection.value === "asc" ? result : -result;
  });
});

const totalPages = computed(() =>
  Math.max(1, Math.ceil(sortedTableRows.value.length / pageSize)),
);
const paginatedTableRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return sortedTableRows.value.slice(start, start + pageSize);
});

watch([searchQuery, activeStatus, activeCategory], () => {
  currentPage.value = 1;
});

watch(totalPages, (pages) => {
  if (currentPage.value > pages) currentPage.value = pages;
});

function getSortValue(row, key) {
  const value = row[key];
  if (["amount", "receiptQuantity", "quantityReport"].includes(key)) {
    return Number(value || 0);
  }
  if (["dateSubmitted"].includes(key)) {
    const timestamp = new Date(value).getTime();
    return Number.isNaN(timestamp)
      ? String(value || "").toLowerCase()
      : timestamp;
  }
  return String(value || "").toLowerCase();
}

function toggleSort(column) {
  const key = column.sortKey || column.key;
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    currentPage.value = 1;
    return;
  }
  sortKey.value = key;
  sortDirection.value = "asc";
  currentPage.value = 1;
}

function isSorted(column) {
  return sortKey.value === (column.sortKey || column.key);
}

function statusClass(status) {
  const classes = {
    approved: "bg-success text-white border border-success",
    pending: "bg-yellow-100 text-yellow-800 border border-yellow-200",
    rejected: "bg-[#FEF2F2] text-[#B91C1C] border border-red-200",
    granted: "bg-[#F0FDFA] text-[#0D9488] border border-teal-100",
  };

  return (
    classes[normalizeStatus(status)] ||
    "bg-slate-100 text-slate-600 border border-slate-200"
  );
}

function closeDetails() {
  viewingRecord.value = null;
  receiptDetailsOpen.value = false;
  selectedReceipt.value = null;
  reviewerNotes.value = "";
  pendingReceiptDecision.value = null;
  isReviewSubmitting.value = false;
}

function viewReceiptDetails(receipt) {
  selectedReceipt.value = receipt;
  reviewerNotes.value = receipt.admin_notes || "";
  pendingReceiptDecision.value = null;
  receiptDetailsOpen.value = true;
}

async function openDetails(row) {
  viewingRecord.value = { ...row, receipts: row.receipts || [] };
  selectedReceipt.value = null;
  reviewerNotes.value = "";
  pendingReceiptDecision.value = null;
  isReviewSubmitting.value = false;
  receiptDetailsOpen.value = false;
  modalLoading.value = true;

  try {
    const response = await apiFetch(`/api/serms/reimbursements/${row.id}`);
    if (!response.ok) throw new Error("Failed to fetch reimbursement details");
    const fullRecord = await response.json();
    viewingRecord.value = fullRecord;
    reviewerNotes.value = fullRecord.admin_notes || "";
  } catch (error) {
    addToast({
      message: "Failed to load reimbursement details",
      type: "error",
    });
    console.error("Failed to load reimbursement details:", error);
    viewingRecord.value = null;
  } finally {
    modalLoading.value = false;
  }
}

function requestReceiptDecision(receipt, action) {
  pendingReceiptDecision.value = {
    receiptId: receipt.id,
    action,
  };
}

function cancelReceiptDecision() {
  pendingReceiptDecision.value = null;
}

function isReceiptDecisionPending(receipt) {
  if (!receipt) return false;
  return pendingReceiptDecision.value?.receiptId === receipt.id;
}

async function confirmReceiptDecision() {
  if (!viewingRecord.value || !pendingReceiptDecision.value) return;

  isReviewSubmitting.value = true;
  const { receiptId, action } = pendingReceiptDecision.value;
  const status = action === "Approve" ? "approved" : "rejected";

  try {
    const res = await apiFetch(
      `/api/serms/reimbursements/receipts/${receiptId}`,
      {
        method: "PATCH",
        body: JSON.stringify({
          status,
          admin_notes: reviewerNotes.value,
        }),
      },
    );

    if (!res.ok) throw new Error("Failed to update receipt decision");
    const json = await res.json();
    const updatedReceipt = json.data;

    // Update the receipt in viewingRecord
    const rIndex = viewingRecord.value.receipts.findIndex(
      (r) => r.id === receiptId,
    );
    if (rIndex > -1) {
      viewingRecord.value.receipts[rIndex] = updatedReceipt;
    }

    // If the selected receipt is the one that got updated, update it too
    if (selectedReceipt.value?.id === receiptId) {
      selectedReceipt.value = {
        ...selectedReceipt.value,
        ...updatedReceipt,
      };
    }

    // Refetch reimbursement to reflect automatic status updates
    const refetchRes = await apiFetch(
      `/api/serms/reimbursements/${viewingRecord.value.id}`,
    );
    if (refetchRes.ok) {
      const fullRecord = await refetchRes.json();
      viewingRecord.value = fullRecord;

      // Update in store.items
      const itemIndex = store.items.findIndex((i) => i.id === fullRecord.id);
      if (itemIndex > -1) {
        store.items[itemIndex] = fullRecord;
      }
    }

    addToast({
      message: `Receipt ${status === "approved" ? "approved" : "rejected"} successfully`,
      type: "success",
    });
    pendingReceiptDecision.value = null;
  } catch (error) {
    addToast({ message: "Failed to update receipt decision", type: "error" });
    console.error(error);
  } finally {
    isReviewSubmitting.value = false;
  }
}

onMounted(() => store.fetchAll());

function openApproveModal(id) {
  approvingId.value = id;
  confirmPassword.value = "";
  showConfirmPassword.value = false;
}

function cancelApprove() {
  approvingId.value = null;
  confirmPassword.value = "";
  showConfirmPassword.value = false;
}

async function confirmApprove() {
  if (!approvingId.value) return;

  isReviewSubmitting.value = true;
  try {
    const updated = await store.approve(
      approvingId.value,
      confirmPassword.value,
    );
    if (viewingRecord.value?.id === approvingId.value) {
      viewingRecord.value = updated;
    }
    addToast({ message: "Reimbursement approved!", type: "success" });
    cancelApprove();
  } catch (e) {
    addToast({
      message: e.message || "Error approving reimbursement",
      type: "error",
    });
  } finally {
    isReviewSubmitting.value = false;
  }
}

function openRejectModal(id) {
  rejectingId.value = id;
  rejectionComment.value = "";
  confirmPassword.value = "";
  showConfirmPassword.value = false;
}

function cancelReject() {
  rejectingId.value = null;
  rejectionComment.value = "";
  confirmPassword.value = "";
  showConfirmPassword.value = false;
}

async function confirmReject() {
  if (rejectionComment.value.length < 10) {
    addToast({
      message: "Rejection comment must be at least 10 characters.",
      type: "error",
    });
    return;
  }

  isReviewSubmitting.value = true;
  try {
    const updated = await store.reject(
      rejectingId.value,
      rejectionComment.value,
      confirmPassword.value,
    );
    if (viewingRecord.value?.id === rejectingId.value) {
      viewingRecord.value = updated;
    }
    addToast({ message: "Reimbursement rejected.", type: "success" });
    cancelReject();
  } catch (e) {
    addToast({
      message: e.message || "Error rejecting reimbursement",
      type: "error",
    });
  } finally {
    isReviewSubmitting.value = false;
  }
}
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
    <div v-if="false" class="flex flex-wrap gap-2">
      <div
        class="flex items-center gap-2 px-3.5 py-2 bg-amber-50 border border-amber-200 rounded-full shadow-sm"
      >
        <div class="w-1.5 h-1.5 bg-amber-400 rounded-full" />
        <span
          class="text-xs font-semibold text-amber-700"
          style="font-family: &quot;Open Sans&quot;, sans-serif"
        >
          Pending: {{ store.pending.length }}
        </span>
      </div>
      <div
        class="flex items-center gap-2 px-3.5 py-2 bg-emerald-50 border border-emerald-200 rounded-full shadow-sm"
      >
        <div class="w-1.5 h-1.5 bg-emerald-400 rounded-full" />
        <span
          class="text-xs font-semibold text-emerald-700"
          style="font-family: &quot;Open Sans&quot;, sans-serif"
        >
          Approved: {{ store.approved.length }}
        </span>
      </div>
      <div
        class="flex items-center gap-2 px-3.5 py-2 bg-primary/5 border border-primary/20 rounded-full shadow-sm"
      >
        <Activity class="w-3 h-3 text-primary" />
        <span
          class="text-xs font-semibold text-primary"
          style="font-family: &quot;Open Sans&quot;, sans-serif"
        >
          Total: ₱{{ store.total.toLocaleString() }}
        </span>
      </div>
    </div>

    <!-- ── Main Table ── -->
    <section
      class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    >
      <div
        class="flex flex-col gap-1 border-b border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <h2
            class="font-heading text-base font-bold leading-tight text-slate-800"
          >
            Reimbursement Requests
          </h2>
          <p class="mt-0.5 text-xs text-slate-400">
            Your reimbursement report records
          </p>
        </div>
        <span class="kpi-label text-slate-400">
          <template v-if="store.isLoading">Loading...</template>
          <template v-else
            >Showing {{ sortedTableRows.length }} records</template
          >
        </span>
      </div>
      <div class="overflow-x-auto">
        <table
          class="w-full border-collapse text-left"
          :class="reimbursementTableMinWidth"
        >
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                v-for="column in reimbursementColumns"
                :key="column.key"
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em]"
                :class="[
                  column.align === 'right'
                    ? 'text-right'
                    : column.align === 'center'
                      ? 'text-center'
                      : 'text-left',
                  isSorted(column) ? 'text-accent' : 'text-slate-500',
                ]"
              >
                <button
                  class="inline-flex items-center gap-1.5 transition-colors hover:text-accent"
                  :class="
                    column.align === 'right'
                      ? 'justify-end'
                      : column.align === 'center'
                        ? 'justify-center'
                        : 'justify-start'
                  "
                  type="button"
                  @click="toggleSort(column)"
                >
                  <span>{{ column.label }}</span>
                  <ChevronUp
                    v-if="isSorted(column) && sortDirection === 'asc'"
                    class="h-3.5 w-3.5"
                  />
                  <ChevronDown
                    v-else-if="isSorted(column)"
                    class="h-3.5 w-3.5"
                  />
                  <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
                </button>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-if="store.isLoading">
              <tr
                v-for="i in pageSize"
                :key="`reimbursement-skeleton-${i}`"
                class="whitespace-nowrap"
              >
                <td
                  v-for="col in reimbursementColumnCount"
                  :key="col"
                  class="px-5 py-5"
                >
                  <div
                    v-if="col === reimbursementColumnCount"
                    class="mx-auto flex h-8 w-16 max-w-full animate-pulse items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 sm:h-9 sm:w-20 sm:gap-2"
                  >
                    <div
                      class="h-3 w-3 shrink-0 rounded bg-slate-200 sm:h-3.5 sm:w-3.5"
                    ></div>
                    <div class="h-2.5 w-5 rounded bg-slate-200 sm:w-7"></div>
                  </div>
                  <div
                    v-else
                    class="h-3.5 max-w-full animate-pulse rounded bg-slate-200"
                    :class="[
                      col === reimbursementColumnCount - 1
                        ? 'mx-auto h-5 w-16 rounded-full sm:w-20'
                        : '',
                      col === 1 ? 'w-12 sm:w-14' : '',
                      col === 2 ? 'w-28 sm:w-40' : '',
                      col === 5 ? 'mx-auto w-8 sm:w-10' : '',
                      col === 7 ? 'ml-auto w-20 sm:w-24' : '',
                      ![
                        1,
                        2,
                        5,
                        7,
                        reimbursementColumnCount - 1,
                        reimbursementColumnCount,
                      ].includes(col)
                        ? 'w-20 sm:w-24'
                        : '',
                    ]"
                  />
                </td>
              </tr>
            </template>
            <template v-else-if="sortedTableRows.length === 0">
              <tr>
                <td
                  :colspan="reimbursementColumnCount"
                  class="px-5 py-8 text-center text-sm text-slate-500"
                >
                  No reimbursement records found.
                </td>
              </tr>
            </template>
            <template v-else>
              <tr
                v-for="row in paginatedTableRows"
                :key="row.id"
                class="group whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
              >
                <td
                  class="px-5 py-5 font-mono text-sm font-bold text-slate-900"
                >
                  {{ row.id }}
                </td>
                <td class="max-w-[240px] px-5 py-5 text-sm text-slate-600">
                  <span class="block truncate">{{
                    row.reportDescription
                  }}</span>
                </td>
                <td class="px-5 py-5 text-sm text-slate-500">
                  {{ row.cutoffPeriod }}
                </td>
                <td class="px-5 py-5">
                  <span
                    class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500"
                  >
                    {{ row.category }}
                  </span>
                </td>
                <template v-if="!auth.isAdmin">
                  <td
                    class="px-5 py-5 text-center text-sm font-semibold text-slate-600"
                  >
                    {{ row.receiptQuantity }}
                  </td>
                  <td
                    class="px-5 py-5 text-center text-sm font-semibold text-slate-600"
                  >
                    {{ row.quantityReport }}
                  </td>
                  <td
                    class="px-5 py-5 text-right text-sm font-bold text-primary"
                  >
                    {{ formatPeso(row.amount) }}
                  </td>
                </template>
                <td class="px-5 py-5 text-sm text-slate-500">
                  {{ row.dateSubmitted }}
                </td>
                <td
                  v-if="auth.isAdmin"
                  class="px-5 py-5 text-sm font-semibold text-slate-600"
                >
                  {{ row.submittedBy }}
                </td>
                <td class="px-5 py-5 text-center">
                  <span
                    :class="[
                      'inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                      statusClass(row.displayStatus),
                    ]"
                  >
                    {{ row.displayStatusLabel }}
                  </span>
                </td>
                <td class="px-5 py-5 text-center">
                  <button
                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg border border-accent/15 bg-accent/5 px-3 text-xs font-bold text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.02] focus:outline-none"
                    title="View reimbursement"
                    @click="openDetails(row)"
                  >
                    <Eye class="h-3.5 w-3.5" />
                    <span>View</span>
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <BasePagination
        v-if="!store.isLoading && sortedTableRows.length > pageSize"
        v-model:page="currentPage"
        :page-size="pageSize"
        :total="sortedTableRows.length"
        label="records"
      />
    </section>

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
      :is-submitting="isReviewSubmitting"
      @close="receiptDetailsOpen = false"
      @close-all="closeDetails"
      @request-decision="action => requestReceiptDecision(selectedReceipt, action)"
      @cancel-decision="cancelReceiptDecision"
      @confirm-decision="confirmReceiptDecision"
    />

    <!-- Approve Confirmation Modal -->
    <BaseModal
      :isOpen="!!approvingId"
      @close="cancelApprove"
      contentClass="!p-0"
    >
      <div
        class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3"
      >
        <div
          class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"
        >
          <CheckCircle class="w-5 h-5" />
        </div>
        <h3 class="font-heading text-sm font-bold text-slate-800">
          Approve Reimbursement Claim
        </h3>
      </div>
      <div class="p-5 flex flex-col gap-4">
        <p class="text-sm font-medium text-slate-600 leading-relaxed">
          Are you sure you want to approve this reimbursement claim? This action
          will set the status to approved. Please verify your identity by
          entering your password.
        </p>
        <div class="input-wrapper">
          <label class="input-label mb-1 block"
            >Password <span class="text-danger">*</span></label
          >
          <div class="relative">
            <input
              :type="showConfirmPassword ? 'text' : 'password'"
              class="input w-full pr-10"
              v-model="confirmPassword"
              placeholder="Enter your current password"
              @keyup.enter="confirmApprove"
            />
            <button
              type="button"
              @click="showConfirmPassword = !showConfirmPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
              tabindex="-1"
            >
              <Eye v-if="!showConfirmPassword" class="w-4 h-4" />
              <EyeOff v-else class="w-4 h-4" />
            </button>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-2">
          <button
            class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
            type="button"
            @click="cancelApprove"
          >
            Cancel
          </button>
          <button
            class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="!confirmPassword || isReviewSubmitting"
            @click="confirmApprove"
          >
            <Activity
              v-if="isReviewSubmitting"
              class="w-3.5 h-3.5 animate-spin mr-1.5"
            />
            Confirm Approve
          </button>
        </div>
      </div>
    </BaseModal>

    <!-- Reject Confirmation Modal -->
    <BaseModal
      :isOpen="!!rejectingId"
      @close="cancelReject"
      contentClass="!p-0"
    >
      <div
        class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3"
      >
        <div
          class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-600"
        >
          <XCircle class="w-5 h-5" />
        </div>
        <h3 class="font-heading text-sm font-bold text-slate-800">
          Reject Reimbursement Claim
        </h3>
      </div>
      <div class="p-5 flex flex-col gap-4">
        <p class="text-sm font-medium text-slate-600 leading-relaxed">
          Please provide a reason for rejecting this claim and enter your
          current password to authorize this action.
        </p>

        <div class="input-wrapper">
          <label class="input-label mb-1 block"
            >Rejection Comment <span class="text-danger">*</span></label
          >
          <textarea
            v-model="rejectionComment"
            rows="3"
            class="input !font-sans resize-none"
            placeholder="Explain the reason for rejecting this claim (minimum 10 characters)..."
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

        <div class="input-wrapper">
          <label class="input-label mb-1 block"
            >Password <span class="text-danger">*</span></label
          >
          <div class="relative">
            <input
              :type="showConfirmPassword ? 'text' : 'password'"
              class="input w-full pr-10"
              v-model="confirmPassword"
              placeholder="Enter your current password"
              @keyup.enter="confirmReject"
            />
            <button
              type="button"
              @click="showConfirmPassword = !showConfirmPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
              tabindex="-1"
            >
              <Eye v-if="!showConfirmPassword" class="w-4 h-4" />
              <EyeOff v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-2">
          <button
            class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
            type="button"
            @click="cancelReject"
          >
            Cancel
          </button>
          <button
            class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-600 px-4 text-xs font-bold text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="
              rejectionComment.length < 10 ||
              !confirmPassword ||
              isReviewSubmitting
            "
            @click="confirmReject"
          >
            <Activity
              v-if="isReviewSubmitting"
              class="w-3.5 h-3.5 animate-spin mr-1.5"
            />
            Confirm Reject
          </button>
        </div>
      </div>
    </BaseModal>
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
