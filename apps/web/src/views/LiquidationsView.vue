<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useLiquidationStore } from "@/stores/liquidation";
import { useReceiptStore } from "@/stores/receipts";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import StatusBadge from "@/components/base/StatusBadge.vue";
import FileUpload from "@/components/base/FileUpload.vue";
import BaseButton from "@/components/base/BaseButton.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BasePagination from "@/components/base/BasePagination.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import SkeletonLoader from "@/components/base/SkeletonLoader.vue";
import DecisionConfirmationModal from "@/components/reimbursements/DecisionConfirmationModal.vue";
import ConfirmModal from "@/components/base/ConfirmModal.vue";
import DeleteConfirmModal from "@/components/base/DeleteConfirmModal.vue";
import ActionDropdownMenu from "@/components/base/ActionDropdownMenu.vue";
import LiquidationTable from "@/components/liquidations/LiquidationTable.vue";
import LiquidationReceiptModal from "@/components/liquidations/LiquidationReceiptModal.vue";
import LiquidationReviewModal from "@/components/liquidations/LiquidationReviewModal.vue";
import LiquidationSettlementForm from "@/components/liquidations/LiquidationSettlementForm.vue";
import LiquidationAdvancesList from "@/components/liquidations/LiquidationAdvancesList.vue";
import { useLiquidationDecisions } from "@/composables/liquidations/useLiquidationDecisions";
import { useLiquidationForwarding } from "@/composables/liquidations/useLiquidationForwarding";
import { useLiquidationSubmit } from "@/composables/liquidations/useLiquidationSubmit";
import { useUnsavedChanges } from "@/composables/useUnsavedChanges";
import { formatPeso, formatDate } from "@/utils/formatters";
import { numberOrZero } from "@/utils/numbers";
import { getFileUrl } from "@/utils/fileUtils";
import {
  Activity,
  AlertTriangle,
  ArchiveRestore,
  ArrowLeft,
  CheckCircle,
  Eye,
  FilePieChart,
  Upload,
  Wallet,
} from "lucide-vue-next";

const store = useCashAdvanceStore();
const liqStore = useLiquidationStore();
const receiptStore = useReceiptStore();
const auth = useAuthStore();
const router = useRouter();
const { addToast } = useToast();

/** Refresh both stores in parallel — balance is now authoritative in the DB */
async function refreshAll() {
  await Promise.all([
    liqStore.fetchSettlements(),
    store.fetchAll(),
    receiptStore.fetchCategories(),
  ]);
}

onMounted(() => refreshAll());

const selectedAdvance = ref(null);
const receipts = ref([]);
const reportAttachment = ref(null);
const reportAttachmentInput = ref(null);
const { submitting, submitLiquidation: performSubmitLiquidation } = useLiquidationSubmit();
const submitted = ref(false);
const shortfallExplanation = ref("");
const showAdminRequestForm = ref(false);

const isDirty = computed(() => {
  return (
    receipts.value.length > 0 ||
    reportAttachment.value !== null ||
    shortfallExplanation.value !== ""
  );
});

const {
  showConfirmModal,
  handleConfirmLeave,
  handleCancelLeave,
  dismissWithConfirm,
} = useUnsavedChanges(isDirty, submitted);

const sortKey = ref("id");
const sortDirection = ref("asc");
const reviewingCase = ref(null);

const {
  approvingId,
  rejectingId,
  confirmPassword,
  rejectionComment,
  isReviewSubmitting,
  openApproveModal,
  openRejectModal,
  cancelApprove,
  cancelReject,
  confirmApprove,
  confirmReject,
} = useLiquidationDecisions(liqStore, addToast, reviewingCase, refreshAll, closeReview);

const reviewDrafts = ref({});
const confirmFinalizeOpen = ref(false);
const receiptDetailsOpen = ref(false);
const selectedReceipt = ref(null);
const pendingReceiptDecision = ref("");
const searchQuery = ref("");
const activeStatus = ref("All");
const pageSize = 10;
const currentPage = ref(1);
const employeeSearchQuery = ref("");
const employeeActiveStatus = ref("All");
const employeeSortKey = ref("status");
const employeeSortDirection = ref("asc");
const VALID_REPORT_ATTACHMENT_MIME_TYPES = [
  "application/pdf",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
];
const VALID_REPORT_ATTACHMENT_EXTENSIONS = [".pdf", ".docx"];

const statusFilters = [
  "All",
  "Pending",
  "Rejected",
  "Incomplete",
  "Overpayment",
  "Liquidated",
  "Overdue",
];
const employeeStatusFilters = [
  "All",
  "Pending",
  "Rejected",
  "Approved",
  "Disbursed",
  "Signed",
  "Incomplete",
  "Under Review",
  "Overdue",
];
const employeeSortOptions = [
  { value: "status", label: "Status" },
  { value: "date", label: "Date" },
  { value: "amount", label: "Total Amount" },
];
const receiptCategoryOptions = computed(() => receiptStore.categories || []);

const defaultReceiptCategoryId = computed(() => {
  const categories = receiptCategoryOptions.value;
  if (categories.length === 0) return null;

  const preferredCategory = categories.find(
    (category) =>
      String(category.name || "")
        .trim()
        .toLowerCase() === "meals",
  );

  return Number(preferredCategory?.id ?? categories[0]?.id ?? null) || null;
});

const totalExpenseAmount = computed(() =>
  receipts.value.reduce(
    (sum, receipt) => sum + numberOrZero(receipt.ocrData?.amount),
    0,
  ),
);

const hasIncompleteReceiptFields = computed(() =>
  receipts.value.some((receipt) => {
    const ocrData = receipt.ocrData || {};
    const amount = Number(ocrData.amount);

    return (
      !String(ocrData.vendor || "").trim() ||
      !String(ocrData.date || "").trim() ||
      !String(ocrData.tin || "").trim() ||
      !String(ocrData.invoiceNumber || "").trim() ||
      !receipt.categoryId ||
      !Number.isFinite(amount) ||
      amount <= 0
    );
  }),
);

const agingInfo = computed(() => {
  if (!selectedAdvance.value) return null;
  return liqStore.calculateAging(selectedAdvance.value);
});

const variance = computed(() => {
  if (!selectedAdvance.value) return 0;
  const currentBalance = numberOrZero(
    selectedAdvance.value.balance ?? selectedAdvance.value.amount,
  );
  return currentBalance - totalExpenseAmount.value;
});

const liquidationOutstandingBalance = computed(() =>
  Math.max(variance.value, 0),
);
const liquidationStatus = computed(() => {
  if (!selectedAdvance.value) return "Incomplete";
  if (variance.value < 0) return "Overpayment";
  if (variance.value > 0) return "Incomplete";
  return "Liquidated";
});
const calculatedOutstandingBalance = computed(() => {
  const currentBalance = numberOrZero(selectedAdvance.value?.balance);
  return Math.max(totalExpenseAmount.value - currentBalance, 0);
});
const overpaymentAmount = computed(() =>
  Math.max(
    totalExpenseAmount.value - numberOrZero(selectedAdvance.value?.balance),
    0,
  ),
);

const { forwardOverpaymentToReimbursement } = useLiquidationForwarding(
  selectedAdvance,
  overpaymentAmount,
  receipts,
  defaultReceiptCategoryId
);

const needsReportAttachmentReminder = computed(
  () =>
    selectedAdvance.value &&
    (liquidationStatus.value === "Incomplete" ||
      (overpaymentAmount.value > 0 && !reportAttachment.value)),
);

const currentUserId = computed(() =>
  auth.user?.id !== null && auth.user?.id !== undefined
    ? String(auth.user.id)
    : null,
);

function isOwnedByCurrentUser(item) {
  const ownerId = item?.userId ?? item?.user_id ?? item?.user?.id;
  return (
    currentUserId.value !== null &&
    ownerId !== null &&
    ownerId !== undefined &&
    String(ownerId) === currentUserId.value
  );
}

const employeeOutstandingAdvances = computed(() =>
  store.items.filter((item) => {
    const status = String(item.status || "").toLowerCase();
    const isEligibleAdvance =
      ["signed", "overdue", "incomplete"].includes(status) &&
      item.acknowledgedAt;

    if (!isEligibleAdvance) return false;
    if (!auth.isAdmin) return true;

    return isOwnedByCurrentUser(item);
  }),
);

const employeeFilteredAdvances = computed(() => {
  const query = employeeSearchQuery.value.trim().toLowerCase();

  const rows = employeeOutstandingAdvances.value.filter((advance) => {
    const status = employeeAdvanceStatus(advance);
    const matchesStatus =
      employeeActiveStatus.value === "All" ||
      status === employeeActiveStatus.value;
    const matchesSearch =
      !query ||
      [
        advance.id,
        advance.purpose,
        advance.status,
        status,
        formatPeso(advance.amount || 0),
      ].some((value) =>
        String(value || "")
          .toLowerCase()
          .includes(query),
      );

    return matchesStatus && matchesSearch;
  });

  const direction = employeeSortDirection.value === "asc" ? 1 : -1;
  return [...rows].sort((a, b) => {
    const aValue = employeeSortValue(a, employeeSortKey.value);
    const bValue = employeeSortValue(b, employeeSortKey.value);

    if (typeof aValue === "number" && typeof bValue === "number") {
      return (aValue - bValue) * direction;
    }

    return (
      String(aValue).localeCompare(String(bValue), undefined, {
        numeric: true,
        sensitivity: "base",
      }) * direction
    );
  });
});

const tableColumns = [
  { key: "requestorName", label: "Requestor Name" },
  { key: "dueDate", label: "Due Date" },
  { key: "cashAdvanceAmount", label: "Cash Advance Amount", align: "right" },
  { key: "outstandingBalance", label: "Outstanding Balance", align: "right" },
  { key: "status", label: "Status", align: "center" },
  { key: "actions", label: "Actions", align: "center" },
];

function categoryName(record, fallback = "Expense") {
  return (
    record?.category?.name ||
    record?.expense_category?.name ||
    record?.category ||
    fallback
  );
}

const mapBackendStatusToDisplayStatus = (backendStatus, row, acceptedTotal) => {
  if (backendStatus === "pending") return "Pending";
  if (backendStatus === "liquidated") return "Liquidated";
  if (backendStatus === "rejected") return "Rejected";
  if (backendStatus === "incomplete") return "Incomplete";
  return calculateLiquidationStatus(row, acceptedTotal);
};

const sourceCases = computed(() => {
  const rows = liqStore.settlements.map((item) => {
    const mappedReceipts = (item.receipts || []).map((r, rIdx) => {
      const subtotal = Math.max(
        Number(r.total_amount || 0) - Number(r.vat_amount || 0),
        0,
      );
      return {
        id: r.id,
        fileName: r.file_path
          ? r.file_path.split("/").pop()
          : `receipt_${rIdx + 1}.jpg`,
        merchantName: r.vendor_name || "Unknown Vendor",
        location: r.location || "N/A",
        category: categoryName(r),
        categoryId:
          Number(r.expense_category_id ?? defaultReceiptCategoryId.value) ||
          null,
        invoiceNumber: r.invoice_number || "N/A",
        transactionDate: r.transaction_date || r.created_at,
        tinNumber: r.tin || "N/A",
        items: r.items || [],
        amount: Number(r.total_amount || 0),
        subtotal,
        vat: Number(r.vat_amount || 0),
        decision: r.status === "rejected" ? "rejected" : "accepted",
        notes: r.admin_notes || "",
        filePath: r.file_path,
      };
    });

    const statusContext = {
      cashAdvanceAmount: Number(item.cash_advance?.amount || 0),
      dueDate:
        item.cash_advance?.expected_liquidation_date ||
        item.cash_advance?.dueDate,
    };

    const displayStatus = mapBackendStatusToDisplayStatus(
      item.status,
      statusContext,
      Number(item.total_expense_amount || 0),
    );

    return {
      id: `LIQ-${String(item.id).padStart(3, "0")}`,
      databaseId: item.id,
      advanceId: `CA-${String(item.cash_advance_id).padStart(3, "0")}`,
      cashAdvanceId: item.cash_advance_id,
      requestorId:
        item.user?.id ??
        item.user_id ??
        item.cash_advance?.user_id ??
        item.cash_advance?.userId,
      requestorName: item.user?.name || item.cash_advance?.user?.name || "",
      dueDate:
        item.cash_advance?.expected_liquidation_date ||
        item.cash_advance?.dueDate,
      cashAdvanceAmount: Number(item.cash_advance?.amount || 0),
      outstandingBalance: numberOrZero(
        item.outstanding_balance ?? item.cash_advance?.amount,
      ),
      receipts: mappedReceipts,
      submittedReceiptTotal: Number(item.total_expense_amount || 0),
      shortfallExplanation: item.shortfall_explanation || "",
      adminNote: item.admin_note || "",
      reportFilePath: item.report_file_path || null,
      status: displayStatus,
    };
  });

  return rows;
});

const liquidationRows = computed(() =>
  sourceCases.value.map((row) => {
    const draft = reviewDrafts.value[row.id];
    const acceptedTotal = draft
      ? acceptedReceiptTotal(row, draft.receipts)
      : row.submittedReceiptTotal;

    let status = row.status;
    if (
      status !== "Liquidated" &&
      status !== "Rejected" &&
      status !== "Pending"
    ) {
      status =
        draft?.finalizedStatus ||
        calculateLiquidationStatus(row, acceptedTotal);
    }

    // Outstanding balance reflects the snapshot balance of the cash advance
    // at the time the liquidation was submitted.
    const outstandingBalance = numberOrZero(row.outstandingBalance);

    return {
      ...row,
      acceptedTotal,
      outstandingBalance,
      status,
    };
  }),
);

const filteredRows = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  return liquidationRows.value.filter((row) => {
    const matchesStatus =
      activeStatus.value === "All" || row.status === activeStatus.value;
    const matchesSearch =
      !query ||
      [
        row.advanceId,
        row.requestorName,
        row.dueDate,
        row.status,
        formatDate(row.dueDate),
        formatPeso(row.cashAdvanceAmount),
        formatPeso(row.outstandingBalance),
      ].some((value) =>
        String(value || "")
          .toLowerCase()
          .includes(query),
      );

    return matchesStatus && matchesSearch;
  });
});

const sortedRows = computed(() => {
  const rows = [...filteredRows.value];
  return rows.sort((a, b) => {
    const aValue = getSortValue(a, sortKey.value);
    const bValue = getSortValue(b, sortKey.value);
    if (aValue === bValue) return 0;
    const result = aValue > bValue ? 1 : -1;
    return sortDirection.value === "asc" ? result : -result;
  });
});

const totalPages = computed(() =>
  Math.max(1, Math.ceil(sortedRows.value.length / pageSize)),
);
const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return sortedRows.value.slice(start, start + pageSize);
});

watch([searchQuery, activeStatus], () => {
  currentPage.value = 1;
});

watch(totalPages, (pages) => {
  if (currentPage.value > pages) currentPage.value = pages;
});

const activeDraft = computed(() =>
  reviewingCase.value ? reviewDrafts.value[reviewingCase.value.id] : null,
);

const reviewReceipts = computed(() => activeDraft.value?.receipts || []);

const acceptedReviewTotal = computed(() =>
  reviewingCase.value
    ? acceptedReceiptTotal(reviewingCase.value, reviewReceipts.value)
    : 0,
);

const reviewOutstandingBalance = computed(() =>
  reviewingCase.value
    ? Math.max(
        numberOrZero(reviewingCase.value.outstandingBalance) -
          numberOrZero(acceptedReviewTotal.value),
        0,
      )
    : 0,
);

const reviewStatus = computed(() =>
  reviewingCase.value
    ? reviewReceipts.value.some((receipt) => receipt.decision === "rejected")
      ? "Rejected"
      : calculateLiquidationStatus(
          reviewingCase.value,
          acceptedReviewTotal.value,
        )
    : "Incomplete",
);

watch(
  receipts,
  (items) => {
    items.forEach((item) => {
      if (!item.categoryId && defaultReceiptCategoryId.value) {
        item.categoryId = defaultReceiptCategoryId.value;
      }
    });
  },
  { deep: true },
);

const isReviewingOwnLiquidation = computed(() => {
  const currentUserId = auth.user?.id;
  const ownerId =
    reviewingCase.value?.requestorId ??
    reviewingCase.value?.userId ??
    reviewingCase.value?.user_id;

  return (
    currentUserId !== null &&
    currentUserId !== undefined &&
    ownerId !== null &&
    ownerId !== undefined &&
    String(currentUserId) === String(ownerId)
  );
});

const liquidationKpis = computed(() => {
  const rows = liquidationRows.value;
  const incomplete = rows.filter((item) => item.status === "Incomplete").length;
  const liquidated = rows.filter((item) => item.status === "Liquidated").length;
  const outstanding = rows.reduce(
    (sum, item) => sum + numberOrZero(item.outstandingBalance),
    0,
  );

  return [
    {
      label: "Total Reports",
      value: rows.length,
      sub: "Liquidation cases",
      icon: FilePieChart,
      iconBg: "bg-accent/10",
      iconColor: "text-accent",
      accent: "bg-accent",
    },
    {
      label: "Incomplete",
      value: incomplete,
      sub: "Pending clearance",
      icon: AlertTriangle,
      iconBg: "bg-amber-500/10",
      iconColor: "text-amber-500",
      accent: "bg-amber-500",
    },
    {
      label: "Liquidated",
      value: liquidated,
      sub: "Settled advances",
      icon: CheckCircle,
      iconBg: "bg-emerald-500/10",
      iconColor: "text-emerald-500",
      accent: "bg-emerald-500",
    },
    {
      label: "Outstanding Balance",
      value: formatPeso(outstanding),
      sub: "Open balances",
      icon: Wallet,
      iconBg: "bg-blue-900/10",
      iconColor: "text-blue-900",
      accent: "bg-blue-900",
    },
  ];
});

const employeeLiquidationKpis = computed(() => {
  const rows = employeeOutstandingAdvances.value;
  const overdue = rows.filter(
    (item) => employeeAdvanceStatus(item) === "Overdue",
  ).length;
  const readyForLiquidation = rows.filter((item) =>
    ["Approved", "Signed"].includes(employeeAdvanceStatus(item)),
  ).length;
  const outstanding = rows.reduce(
    (sum, item) => sum + numberOrZero(item.balance),
    0,
  );

  const activeAdvances = store.items.filter((item) =>
    ["pending", "approved", "disbursed", "signed"].includes(item.status),
  ).length;

  return [
    {
      label: "Active Advances",
      value: activeAdvances,
      sub: "To reconcile",
      icon: Activity,
      iconBg: "bg-amber-500/10",
      iconColor: "text-amber-500",
      accent: "bg-amber-500",
    },
    {
      label: "Ready to Liquidate",
      value: readyForLiquidation,
      sub: "Available reports",
      icon: CheckCircle,
      iconBg: "bg-emerald-500/10",
      iconColor: "text-emerald-500",
      accent: "bg-emerald-500",
    },
    {
      label: "Overdue",
      value: overdue,
      sub: "Past due window",
      icon: AlertTriangle,
      iconBg: "bg-red-500/10",
      iconColor: "text-red-500",
      accent: "bg-red-500",
    },
    {
      label: "Outstanding Balance",
      value: formatPeso(outstanding),
      sub: "Open amount",
      icon: Wallet,
      iconBg: "bg-blue-900/10",
      iconColor: "text-blue-900",
      accent: "bg-blue-900",
    },
  ];
});

function employeeAdvanceStatus(advance) {
  const linkedLiquidation = liqStore.settlements.find(
    (settlement) => settlement.cash_advance_id === advance.id,
  );
  const linkedLiquidationStatus = String(
    linkedLiquidation?.status || "",
  ).toLowerCase();

  if (linkedLiquidationStatus === "rejected") return "Rejected";
  if (linkedLiquidationStatus === "pending") return "Under Review";
  if (linkedLiquidationStatus === "liquidated") return "Liquidated";

  if (liqStore.calculateAging(advance).isOverdue) return "Overdue";
  const status = String(advance.status || "pending").toLowerCase();
  if (status === "approved") return "Approved";
  if (status === "disbursed") return "Disbursed";
  if (status === "signed") return "Signed";
  if (status === "incomplete") return "Incomplete";
  if (status === "pending") return "Pending";
  if (status === "under-review") return "Under Review";
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function employeeAdvanceBadgeStatus(advance) {
  const derivedStatus = employeeAdvanceStatus(advance);
  return derivedStatus === "Overdue"
    ? "overdue"
    : derivedStatus.toLowerCase().replace(/\s+/g, "-");
}

function employeeSortValue(advance, key) {
  if (key === "amount") return numberOrZero(advance.amount);
  if (key === "date") {
    const timestamp = new Date(
      advance.date ||
        advance.submitted_at ||
        advance.created_at ||
        advance.dueDate ||
        0,
    ).getTime();
    return Number.isFinite(timestamp) ? timestamp : 0;
  }
  return employeeAdvanceStatus(advance);
}

async function submitLiquidation() {
  const success = await performSubmitLiquidation({
    receipts: receipts.value,
    selectedAdvance: selectedAdvance.value,
    reportAttachment: reportAttachment.value,
    totalExpenses: totalExpenseAmount.value,
    variance: variance.value,
    shortfallExplanation: shortfallExplanation.value,
    existingLiquidation: existingLiquidation.value,
  });

  if (success) {
    submitted.value = true;
  }
}



const existingLiquidation = computed(() => {
  if (!selectedAdvance.value) return null;
  const status = String(selectedAdvance.value.status || "").toLowerCase();
  // If the cash advance is under-review or incomplete, look for a pending/rejected liquidation
  if (status === "under-review" || status === "incomplete") {
    return liqStore.settlements.find(
      (s) =>
        s.cash_advance_id === selectedAdvance.value.id &&
        ["pending", "rejected"].includes(s.status),
    );
  }
  return null;
});

const isDeleteLiqModalOpen = ref(false);
const deletingLiqId = ref(null);

function handleDeleteLiquidation() {
  if (!existingLiquidation.value) return;
  deletingLiqId.value = existingLiquidation.value.id;
  isDeleteLiqModalOpen.value = true;
}

async function confirmDeleteLiquidation(password) {
  if (!deletingLiqId.value) return;
  try {
    submitting.value = true;
    await liqStore.deleteSettlement(deletingLiqId.value, password);
    addToast({
      title: "Deleted",
      message: "Liquidation settlement deleted successfully.",
      type: "success",
    });
    isDeleteLiqModalOpen.value = false;
    deletingLiqId.value = null;
    selectedAdvance.value = null; // Close form
    await refreshAll();
  } catch (error) {
    addToast({
      title: "Failed",
      message: error.message || "Failed to delete liquidation.",
      type: "danger",
    });
  } finally {
    submitting.value = false;
  }
}

function getActions(row) {
  return [
    {
      label: "View",
      icon: Eye,
      visible: true,
      handler: () => openReview(row),
    },
  ];
}

function selectAdvance(adv) {
  dismissWithConfirm(() => {
    selectedAdvance.value = adv;
    submitted.value = false;

    // Find if there is an existing pending or rejected liquidation in liqStore.settlements
    const existingLiq = liqStore.settlements.find(
      (s) =>
        s.cash_advance_id === adv.id &&
        ["pending", "rejected"].includes(s.status),
    );

    if (existingLiq) {
      shortfallExplanation.value = existingLiq.shortfall_explanation || "";
      // If it's a string filepath from backend, reportAttachment can be set to it
      reportAttachment.value = existingLiq.report_file_path;

      if (existingLiq.receipts && Array.isArray(existingLiq.receipts)) {
        receipts.value = existingLiq.receipts.map((r) => ({
          id: r.id,
          name: r.vendor_name || `Receipt-${r.id}`,
          ocrStatus: "done",
          category: categoryName(r, "General"),
          categoryId:
            Number(r.expense_category_id ?? defaultReceiptCategoryId.value) ||
            null,
          amount: r.total_amount,
          ocrData: {
            id: r.id,
            vendor: r.vendor_name,
            date: r.transaction_date,
            amount: r.total_amount,
            vat: r.vat_amount || 0,
            tin: r.tin,
            invoiceNumber: r.invoice_number,
          },
        }));
      } else {
        receipts.value = [];
      }
    } else {
      receipts.value = [];
      reportAttachment.value = null;
      shortfallExplanation.value = "";
    }
  });
}

function resetLiquidationComposer() {
  selectedAdvance.value = null;
  receipts.value = [];
  reportAttachment.value = null;
  shortfallExplanation.value = "";
  submitted.value = false;
}

function openAdminRequestForm() {
  dismissWithConfirm(() => {
    resetLiquidationComposer();
    showAdminRequestForm.value = true;
  });
}

function closeAdminRequestForm() {
  dismissWithConfirm(() => {
    resetLiquidationComposer();
    showAdminRequestForm.value = false;
  });
}

function isValidReportAttachment(file) {
  if (!file) return false;

  const normalizedName = String(file.name || "").toLowerCase();
  const isValidImage = String(file.type || "").startsWith("image/");
  const hasValidMimeType = VALID_REPORT_ATTACHMENT_MIME_TYPES.includes(
    file.type,
  );
  const hasValidExtension = VALID_REPORT_ATTACHMENT_EXTENSIONS.some((ext) =>
    normalizedName.endsWith(ext),
  );

  if (isValidImage || hasValidMimeType || hasValidExtension) {
    return true;
  }

  addToast({
    message:
      "Invalid file type. Only image files, PDF, and DOCX are allowed for the report attachment.",
    type: "error",
  });
  return false;
}

function selectReportAttachment(event) {
  const file = event.target.files?.[0];
  if (file && isValidReportAttachment(file)) reportAttachment.value = file;
  event.target.value = "";
}

function clearReportAttachment() {
  reportAttachment.value = null;
}



function acceptedReceiptTotal(row, receipts) {
  return receipts.reduce((sum, receipt) => {
    if (receipt.decision !== "accepted") return sum;
    return sum + Number(receipt.amount || 0);
  }, 0);
}

function calculateLiquidationStatus(row, acceptedTotal) {
  const outstanding = Math.max(row.cashAdvanceAmount - acceptedTotal, 0);
  if (acceptedTotal > row.cashAdvanceAmount) return "Overpayment";
  if (outstanding === 0) return "Liquidated";
  if (isPastDue(row.dueDate)) return "Overdue";
  return "Incomplete";
}

function isPastDue(value) {
  if (!value) return false;
  const dueDate = new Date(value);
  if (Number.isNaN(dueDate.getTime())) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  dueDate.setHours(0, 0, 0, 0);
  return dueDate < today;
}

function statusBadgeClass(status) {
  const classes = {
    Pending: "bg-purple-50 text-purple-700 border-purple-200",
    Incomplete: "bg-amber-50 text-amber-700 border-amber-200",
    Overpayment: "bg-blue-50 text-blue-700 border-blue-200",
    Liquidated: "bg-emerald-600 text-white border-emerald-600",
    Overdue: "bg-red-50 text-red-700 border-red-200",
    Rejected: "bg-rose-50 text-rose-700 border-rose-200",
  };
  return classes[status] || "bg-slate-100 text-slate-600 border-slate-200";
}



function toggleSort(column) {
  if (sortKey.value === column.key) {
    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    currentPage.value = 1;
    return;
  }
  sortKey.value = column.key;
  sortDirection.value = "asc";
  currentPage.value = 1;
}

function getSortValue(row, key) {
  if (["cashAdvanceAmount", "outstandingBalance"].includes(key))
    return Number(row[key] || 0);
  if (["dueDate"].includes(key)) return new Date(row[key] || 0).getTime();
  if (key === "actions") return row.id;
  return String(row[key] || "").toLowerCase();
}

function openReview(row) {
  if (!reviewDrafts.value[row.id]) {
    reviewDrafts.value[row.id] = {
      receipts: row.receipts.map((receipt) => ({
        ...receipt,
        decision: receipt.decision || "accepted",
        notes: receipt.notes || "",
        detailsOpen: false,
      })),
      finalizedStatus: "",
    };
  }

  reviewingCase.value = row;
  confirmFinalizeOpen.value = false;
  receiptDetailsOpen.value = false;
  selectedReceipt.value = null;
  pendingReceiptDecision.value = "";
}

function closeReview() {
  reviewingCase.value = null;
  confirmFinalizeOpen.value = false;
  receiptDetailsOpen.value = false;
  selectedReceipt.value = null;
  pendingReceiptDecision.value = "";
}

function viewReceiptDetails(receipt) {
  selectedReceipt.value = receipt;
  pendingReceiptDecision.value = "";
  receiptDetailsOpen.value = true;
}

function closeReceiptDetails() {
  receiptDetailsOpen.value = false;
  selectedReceipt.value = null;
  pendingReceiptDecision.value = "";
}

function setReceiptDecision(receiptId, decision) {
  const receipt = reviewReceipts.value.find((item) => item.id === receiptId);
  if (receipt) receipt.decision = decision;
}

function requestReceiptDecision(decision) {
  if (isReviewingOwnLiquidation.value) {
    addToast({
      title: "Action Not Allowed",
      message:
        "You cannot process receipts from your own liquidation settlement.",
      type: "danger",
    });
    return;
  }

  pendingReceiptDecision.value = decision;
}

function cancelReceiptDecision() {
  pendingReceiptDecision.value = "";
}

function confirmReceiptDecision() {
  if (!selectedReceipt.value || !pendingReceiptDecision.value) return;
  setReceiptDecision(selectedReceipt.value.id, pendingReceiptDecision.value);
  if (pendingReceiptDecision.value === "rejected") {
    pendingReceiptDecision.value = "";
    receiptDetailsOpen.value = false;
    addToast({
      title: "Settlement Rejection Required",
      message:
        "Rejecting a receipt also rejects the entire liquidation settlement.",
      type: "danger",
    });
    openRejectModal();
    return;
  }
  pendingReceiptDecision.value = "";
}

function handleReceiptUploadError(payload) {
  addToast({
    title: "Upload Failed",
    message:
      payload?.message || "One or more receipt files could not be uploaded.",
    type: "danger",
  });
}

function finalizeLiquidation() {
  if (!reviewingCase.value || !activeDraft.value) return;
  activeDraft.value.finalizedStatus = reviewStatus.value;
  confirmFinalizeOpen.value = false;
  closeReview();
}
</script>

<template>
  <div class="flex flex-col w-full gap-6 mx-auto font-sans max-w-7xl">
    <section
      class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
      <div class="min-w-0">
        <div class="flex items-center gap-2 mb-2">
          <component
            :is="
              auth.isAdmin && !showAdminRequestForm
                ? ArchiveRestore
                : FilePieChart
            "
            class="h-3.5 w-3.5 text-accent"
          />
          <span class="section-label">{{
            auth.isAdmin && !showAdminRequestForm
              ? "Settlement Operations"
              : "Liquidation Workflow"
          }}</span>
        </div>
        <h1
          class="text-2xl font-bold leading-tight font-heading text-slate-800"
        >
          {{
            auth.isAdmin && !showAdminRequestForm
              ? "Liquidation Console"
              : "Liquidation"
          }}
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          {{
            auth.isAdmin && !showAdminRequestForm
              ? "Review employee liquidation reports, receipts, and settlement balances"
              : "Reconcile outstanding advances and settle balances"
          }}
        </p>
      </div>
    </section>

    <BaseKpiGrid
      v-if="auth.isAdmin && !showAdminRequestForm"
      :kpis="liquidationKpis"
      gridClasses="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="4"
    />

    <div
      class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
    >
      <div class="min-w-0 flex-1">
        <BaseUtilityToolbar
          v-if="auth.isAdmin && !showAdminRequestForm"
          v-model:search="searchQuery"
          v-model:status-value="activeStatus"
          :statuses="statusFilters"
          searchPlaceholder="Search employee, status, or amount..."
        />
      </div>
      <div v-if="auth.isAdmin" class="flex w-full justify-end lg:w-auto">
        <button
          v-if="!showAdminRequestForm"
          id="admin-new-liquidation-request-btn"
          class="btn btn-cta min-h-[42px] w-full sm:w-fit"
          type="button"
          @click="openAdminRequestForm"
        >
          <Upload class="w-4 h-4" />
          New Request
        </button>
        <button
          v-else
          class="inline-flex min-h-[42px] w-full items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 font-heading text-sm font-bold text-slate-700 shadow-sm transition-all duration-200 ease-out hover:bg-slate-50 hover:shadow-md sm:w-fit"
          type="button"
          @click="closeAdminRequestForm"
        >
          <ArrowLeft class="w-4 h-4" />
          Back to Queue
        </button>
      </div>
    </div>

    <section
      v-if="auth.isAdmin && !showAdminRequestForm"
      class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
    >
      <div
        class="flex flex-col gap-1 px-5 py-4 bg-white border-b border-slate-200 sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <h2
            class="text-base font-bold leading-tight font-heading text-slate-800"
          >
            Liquidation Management
          </h2>
          <p class="mt-0.5 text-xs text-slate-400">
            Administrative audit queue
          </p>
        </div>
        <span class="kpi-label text-slate-400">
          <template v-if="store.isLoading">Loading reports</template>
          <template v-else>Showing {{ sortedRows.length }} reports</template>
        </span>
      </div>

      <LiquidationTable
        :columns="tableColumns"
        :rows="paginatedRows"
        :is-loading="store.isLoading"
        :page-size="pageSize"
        :sort-key="sortKey"
        :sort-direction="sortDirection"
        @sort="toggleSort"
      >
        <template #cell-requestorName="{ row }">
          <span class="text-sm font-semibold text-slate-700">{{
            row.requestorName
          }}</span>
        </template>
        <template #cell-dueDate="{ row }">
          <span class="text-sm text-slate-500">{{
            formatDate(row.dueDate)
          }}</span>
        </template>
        <template #cell-cashAdvanceAmount="{ row }">
          <span class="text-sm font-bold text-primary">{{
            formatPeso(row.cashAdvanceAmount)
          }}</span>
        </template>
        <template #cell-outstandingBalance="{ row }">
          <span class="text-sm font-semibold text-slate-700">{{
            formatPeso(row.outstandingBalance)
          }}</span>
        </template>
        <template #cell-status="{ row }">
          <span
            :class="[
              'inline-flex rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
              statusBadgeClass(row.status),
            ]"
          >
            {{ row.status }}
          </span>
        </template>
        <template #cell-actions="{ row }">
          <ActionDropdownMenu :actions="getActions(row)" />
        </template>
      </LiquidationTable>
      <BasePagination
        v-if="!store.isLoading && sortedRows.length > pageSize"
        v-model:page="currentPage"
        :page-size="pageSize"
        :total="sortedRows.length"
        label="reports"
      />
    </section>

    <LiquidationReviewModal
      :is-open="!!(auth.isAdmin && !showAdminRequestForm && reviewingCase)"
      :reviewing-case="reviewingCase"
      :review-status="reviewStatus"
      :review-outstanding-balance="reviewOutstandingBalance"
      :review-receipts="reviewReceipts"
      :accepted-review-total="acceptedReviewTotal"
      :is-reviewing-own-liquidation="isReviewingOwnLiquidation"
      :get-file-url="getFileUrl"
      :format-date-only="formatDate"
      :status-badge-class="statusBadgeClass"
      @close="closeReview"
      @view-receipt="viewReceiptDetails"
      @reject="openRejectModal"
      @approve="openApproveModal"
    />

    <LiquidationReceiptModal
      :is-open="!!(reviewingCase && receiptDetailsOpen && selectedReceipt)"
      :receipt="selectedReceipt"
      :pending-decision="pendingReceiptDecision"
      :is-reviewing-own-liquidation="isReviewingOwnLiquidation"
      :get-file-url="getFileUrl"
      @close="closeReceiptDetails"
      @close-review="closeReview"
      @request-decision="requestReceiptDecision"
      @cancel-decision="cancelReceiptDecision"
      @confirm-decision="confirmReceiptDecision"
    />

    <!-- Audit Confirmation Modal -->
    <DecisionConfirmationModal
      v-if="auth.isAdmin && !showAdminRequestForm"
      :is-open="!!approvingId || !!rejectingId"
      :mode="approvingId ? 'approve' : 'reject'"
      :is-submitting="isReviewSubmitting"
      :min-comment-length="10"
      title="Liquidation Settlement Audit"
      :description="
        approvingId
          ? 'Are you sure you want to approve this liquidation settlement? This will mark the cash advance as settled. Please enter your password to confirm.'
          : 'Please enter your password and a comment to authorize rejecting this liquidation settlement.'
      "
      v-model:password="confirmPassword"
      v-model:comment="rejectionComment"
      @close="approvingId ? cancelApprove() : cancelReject()"
      @confirm="approvingId ? confirmApprove() : confirmReject()"
    />
    <BaseKpiGrid
      v-if="!auth.isAdmin || showAdminRequestForm"
      :kpis="employeeLiquidationKpis"
      gridClasses="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="4"
    />

    <BaseUtilityToolbar
      v-if="!auth.isAdmin || showAdminRequestForm"
      v-model:search="employeeSearchQuery"
      v-model:status-value="employeeActiveStatus"
      :statuses="employeeStatusFilters"
      searchPlaceholder="Search purpose, status, or amount..."
    />

    <div
      v-if="!auth.isAdmin || showAdminRequestForm"
      class="grid grid-cols-1 gap-6 xl:grid-cols-5"
    >
      <LiquidationAdvancesList
        :sort-options="employeeSortOptions"
        :sort-key="employeeSortKey"
        :sort-direction="employeeSortDirection"
        @update:sort-key="employeeSortKey = $event"
        @update:sort-direction="employeeSortDirection = $event"
        :is-loading="store.isLoading"
        :advances="employeeFilteredAdvances"
        :selected-advance="selectedAdvance"
        :get-badge-status="employeeAdvanceBadgeStatus"
        :calculate-aging="liqStore.calculateAging"
        @select="selectAdvance"
      />

      <div class="xl:col-span-3">
        <LiquidationSettlementForm
          :selected-advance="selectedAdvance"
          :submitted="submitted"
          :receipts="receipts"
          @update:receipts="receipts = $event"
          :receipt-category-options="receiptCategoryOptions"
          :report-attachment="reportAttachment"
          @file-selected="selectReportAttachment"
          @file-cleared="clearReportAttachment"
          :existing-liquidation="existingLiquidation"
          :needs-report-attachment-reminder="needsReportAttachmentReminder"
          :variance="variance"
          :shortfall-explanation="shortfallExplanation"
          @update:shortfall-explanation="shortfallExplanation = $event"
          :total-expense-amount="totalExpenseAmount"
          :liquidation-status="liquidationStatus"
          :liquidation-outstanding-balance="liquidationOutstandingBalance"
          :overpayment-amount="overpaymentAmount"
          :submitting="submitting"
          :has-incomplete-receipt-fields="hasIncompleteReceiptFields"
          @reload-console="
            selectedAdvance = null;
            submitted = false;
          "
          @upload-error="handleReceiptUploadError"
          @forward-overpayment="forwardOverpaymentToReimbursement"
          @delete-liquidation="handleDeleteLiquidation"
          @submit-liquidation="submitLiquidation"
        />
      </div>
    </div>

    <ConfirmModal
      :is-open="showConfirmModal"
      title="Unsaved Changes"
      message="You have unsaved changes in your liquidation form. Are you sure you want to leave? All progress will be lost."
      confirm-text="Leave"
      cancel-text="Stay"
      :danger="true"
      @confirm="handleConfirmLeave"
      @close="handleCancelLeave"
    />

    <DeleteConfirmModal
      v-model="isDeleteLiqModalOpen"
      title="Delete Liquidation Settlement"
      message="Are you sure you want to delete this pending liquidation request? This will revert the parent cash advance status back to incomplete. This action cannot be undone."
      @confirm="confirmDeleteLiquidation"
    />
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
