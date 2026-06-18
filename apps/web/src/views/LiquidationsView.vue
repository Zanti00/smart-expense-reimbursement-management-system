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
import { useUnsavedChanges } from "@/composables/useUnsavedChanges";
import { formatPeso } from "@/utils/formatters";
import { vatOf } from "@/utils/receiptUtils";
import {
  Activity,
  AlertTriangle,
  ArchiveRestore,
  ArrowLeft,
  CalendarDays,
  CheckCircle,
  ChevronDown,
  ChevronsUpDown,
  ChevronUp,
  Download,
  Eye,
  FilePieChart,
  FileText,
  Calculator,
  ClipboardList,
  MapPin,
  ShieldCheck,
  Sparkles,
  Upload,
  Wallet,
  X,
  XCircle,
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
const submitting = ref(false);
const submitted = ref(false);
const shortfallExplanation = ref("");
const showAdminRequestForm = ref(false);

// Audit state variables
const approvingId = ref(null);
const rejectingId = ref(null);
const confirmPassword = ref("");
const rejectionComment = ref("");
const isReviewSubmitting = ref(false);

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

function numberOrZero(value) {
  const amount = Number(value);
  return Number.isFinite(amount) ? amount : 0;
}

const totalExpenseAmount = computed(() =>
  receipts.value.reduce(
    (sum, receipt) => sum + (Number(receipt.ocrData?.amount) || 0),
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

const getFileUrl = (filePath) => {
  if (!filePath) return "";
  if (filePath.startsWith("http://") || filePath.startsWith("https://"))
    return filePath;
  return `https://vbabvrcfqcmvvjwmzuwx.supabase.co/storage/v1/object/public/cash_advances/${filePath}`;
};

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
        formatDateOnly(row.dueDate),
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
  if (key === "amount") return Number(advance.amount) || 0;
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
  submitting.value = true;

  const payload = {
    items: receipts.value.map((receipt, index) => ({
      id: `${selectedAdvance.value.id}-receipt-${index + 1}`,
      category: "Receipt",
      description: receipt.name || receipt.file?.name || `Receipt ${index + 1}`,
      amount: Number(receipt.ocrData?.amount) || 0,
    })),
    receipts: receipts.value,
    reportAttachment: reportAttachment.value,
    totalExpenses: totalExpenseAmount.value,
    variance: variance.value,
    shortfall_explanation: shortfallExplanation.value,
  };

  try {
    if (existingLiquidation.value) {
      await liqStore.updateSettlement(existingLiquidation.value.id, payload);
    } else {
      await liqStore.submitSettlement(selectedAdvance.value.id, payload);
    }

    const item = store.items.find((i) => i.id === selectedAdvance.value.id);
    if (item) {
      item.status = "under-review"; // matches backend lock transition
      item.balance = Math.max(variance.value, 0);
    }

    submitting.value = false;
    submitted.value = true;
  } catch (err) {
    addToast({
      title: "Submission Failed",
      message: err.message || "Failed to submit liquidation settlement.",
      type: "danger",
    });
    submitting.value = false;
  }
}

function openApproveModal() {
  if (isReviewingOwnLiquidation.value) {
    addToast({
      title: "Action Not Allowed",
      message: "You cannot process your own liquidation settlement.",
      type: "danger",
    });
    return;
  }

  confirmPassword.value = "";
  rejectionComment.value = "";
  approvingId.value = reviewingCase.value.databaseId;
  rejectingId.value = null;
}

function openRejectModal() {
  if (isReviewingOwnLiquidation.value) {
    addToast({
      title: "Action Not Allowed",
      message: "You cannot process your own liquidation settlement.",
      type: "danger",
    });
    return;
  }

  confirmPassword.value = "";
  rejectionComment.value = "";
  rejectingId.value = reviewingCase.value.databaseId;
  approvingId.value = null;
}

function cancelApprove() {
  approvingId.value = null;
  confirmPassword.value = "";
}

function cancelReject() {
  rejectingId.value = null;
  confirmPassword.value = "";
  rejectionComment.value = "";
}

async function confirmApprove() {
  if (!approvingId.value || !confirmPassword.value) return;
  if (
    rejectionComment.value.trim() &&
    rejectionComment.value.trim().length < 10
  ) {
    addToast({
      title: "Validation Error",
      message: "Admin note must be at least 10 characters.",
      type: "danger",
    });
    return;
  }
  isReviewSubmitting.value = true;
  try {
    await liqStore.auditSettlement(approvingId.value, {
      status: "approved",
      password: confirmPassword.value,
      admin_note: rejectionComment.value.trim() || null,
    });
    addToast({
      title: "Settlement Approved",
      message: "The liquidation settlement was successfully approved.",
      type: "success",
    });

    await refreshAll();

    closeReview();
    cancelApprove();
  } catch (err) {
    addToast({
      title: "Audit Failed",
      message: err.message || "Failed to approve liquidation settlement.",
      type: "danger",
    });
  } finally {
    isReviewSubmitting.value = false;
  }
}

async function confirmReject() {
  if (!rejectingId.value || !confirmPassword.value) return;
  if (rejectionComment.value.length < 5) {
    addToast({
      title: "Validation Error",
      message: "Rejection comment must be at least 5 characters.",
      type: "danger",
    });
    return;
  }
  isReviewSubmitting.value = true;
  try {
    await liqStore.auditSettlement(rejectingId.value, {
      status: "rejected",
      password: confirmPassword.value,
      admin_note: rejectionComment.value,
    });
    addToast({
      title: "Settlement Rejected",
      message: "The liquidation settlement was successfully rejected.",
      type: "success",
    });

    await refreshAll();

    closeReview();
    cancelReject();
  } catch (err) {
    addToast({
      title: "Audit Failed",
      message: err.message || "Failed to reject liquidation settlement.",
      type: "danger",
    });
  } finally {
    isReviewSubmitting.value = false;
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

function handleAmountChange(receipt) {
  if (receipt && receipt.ocrData) {
    const amt = Number(receipt.ocrData.amount) || 0;
    receipt.ocrData.vat = Number(vatOf(amt).toFixed(2));
  }
}

function formatTinValue(value, { padLastBlock = false } = {}) {
  let digits = String(value || "")
    .replace(/\D/g, "")
    .slice(0, 12);
  if (padLastBlock && digits.length === 9) {
    digits = `${digits}000`;
  }

  const parts = [];
  if (digits.length > 0) parts.push(digits.slice(0, 3));
  if (digits.length > 3) parts.push(digits.slice(3, 6));
  if (digits.length > 6) parts.push(digits.slice(6, 9));
  if (digits.length > 9) parts.push(digits.slice(9, 12));
  return parts.join("-");
}

function handleTinInput(receipt) {
  if (!receipt?.ocrData) return;
  receipt.ocrData.tin = formatTinValue(receipt.ocrData.tin);
}

function handleTinBlur(receipt) {
  if (!receipt?.ocrData) return;
  receipt.ocrData.tin = formatTinValue(receipt.ocrData.tin, {
    padLastBlock: true,
  });
}

function clearReportAttachment() {
  reportAttachment.value = null;
}

function forwardOverpaymentToReimbursement() {
  if (!selectedAdvance.value || overpaymentAmount.value <= 0) return;

  const forwardedReceipts = receipts.value.map((receipt, index) => ({
    id: `LIQ-${selectedAdvance.value.id}-${index + 1}`,
    fileName:
      receipt.name || receipt.file?.name || `Liquidation Receipt ${index + 1}`,
    fileType: receipt.file?.type || "application/pdf",
    thumbnail: receipt.preview || "",
    amount: Number(receipt.ocrData?.amount ?? receipt.amount ?? 0),
    date: receipt.ocrData?.date || new Date().toISOString().slice(0, 10),
    category: receipt.category || "Other",
    categoryId: receipt.categoryId || null,
    source: "liquidation-receipt",
    cashAdvanceId: selectedAdvance.value.id,
  }));

  const cashAdvanceAmount = Number(selectedAdvance.value.amount || 0);
  if (cashAdvanceAmount > 0) {
    forwardedReceipts.push({
      id: `LIQ-${selectedAdvance.value.id}-deduction`,
      fileName: `Cash Advance Deduction (CA-${selectedAdvance.value.id})`,
      fileType: "application/pdf",
      thumbnail: "",
      amount: -cashAdvanceAmount,
      date: new Date().toISOString().slice(0, 10),
      category: "Other",
      categoryId: defaultReceiptCategoryId.value,
      source: "liquidation-deduction",
      cashAdvanceId: selectedAdvance.value.id,
      vatClassification: "non-vat",
      subtotal: (-cashAdvanceAmount).toFixed(2),
      tax: "0.00",
    });
  }

  sessionStorage.setItem(
    "serms_forwarded_liquidation_receipts",
    JSON.stringify(forwardedReceipts),
  );
  router.push("/reimbursements/new");
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

function formatDateOnly(value) {
  if (!value) return "--";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(date);
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

    <div class="grid justify-between grid-cols-3">
      <div class="col-span-2">
        <BaseUtilityToolbar
          v-if="auth.isAdmin && !showAdminRequestForm"
          v-model:search="searchQuery"
          v-model:status-value="activeStatus"
          :statuses="statusFilters"
          searchPlaceholder="Search employee, status, or amount..."
        />
      </div>
      <div v-if="auth.isAdmin" class="flex justify-end col-span-1">
        <button
          v-if="!showAdminRequestForm"
          id="admin-new-liquidation-request-btn"
          class="inline-flex min-h-[42px] w-full items-center justify-center gap-2 rounded-lg bg-accent px-5 font-heading text-sm font-bold text-white shadow-sm transition-all duration-200 ease-out hover:bg-accent-600 hover:shadow-xl hover:scale-[1.01] active:scale-[0.98] sm:w-fit"
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

      <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                v-for="column in tableColumns"
                :key="column.key"
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
                :class="
                  column.align === 'right'
                    ? 'text-right'
                    : column.align === 'center'
                      ? 'text-center'
                      : 'text-left'
                "
              >
                <button
                  class="inline-flex items-center w-full gap-2 transition-colors hover:text-accent"
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
                    v-if="sortKey === column.key && sortDirection === 'asc'"
                    class="h-3.5 w-3.5 text-accent"
                  />
                  <ChevronDown
                    v-else-if="sortKey === column.key"
                    class="h-3.5 w-3.5 text-accent"
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
                :key="`liquidation-skeleton-${i}`"
                class="whitespace-nowrap"
              >
                <td
                  v-for="col in tableColumns.length"
                  :key="col"
                  class="px-5 py-5"
                >
                  <div
                    v-if="col === tableColumns.length"
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
                      col === 5 ? 'mx-auto h-5 w-20 rounded-full sm:w-24' : '',
                      col === 1 ? 'w-24 sm:w-32' : '',
                      col === 2 ? 'w-24 sm:w-32' : '',
                      [3, 4].includes(col) ? 'ml-auto w-20 sm:w-24' : '',
                      ![1, 2, 3, 4, 5, 6].includes(col) ? 'w-20 sm:w-28' : '',
                    ]"
                  ></div>
                </td>
              </tr>
            </template>
            <tr v-else-if="sortedRows.length === 0">
              <td
                :colspan="tableColumns.length"
                class="px-5 py-10 text-sm font-semibold text-center text-slate-400"
              >
                No liquidation reports found.
              </td>
            </tr>
            <template v-else>
              <tr
                v-for="row in paginatedRows"
                :key="row.id"
                class="transition-colors duration-200 ease-out whitespace-nowrap hover:bg-slate-50/80"
              >
                <td class="px-5 py-5 text-sm font-semibold text-slate-700">
                  {{ row.requestorName }}
                </td>
                <td class="px-5 py-5 text-sm text-slate-500">
                  {{ formatDateOnly(row.dueDate) }}
                </td>
                <td class="px-5 py-5 text-sm font-bold text-right text-primary">
                  {{ formatPeso(row.cashAdvanceAmount) }}
                </td>
                <td
                  class="px-5 py-5 text-sm font-semibold text-right text-slate-700"
                >
                  {{ formatPeso(row.outstandingBalance) }}
                </td>
                <td class="px-5 py-5 text-center">
                  <span
                    :class="[
                      'inline-flex rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                      statusBadgeClass(row.status),
                    ]"
                  >
                    {{ row.status }}
                  </span>
                </td>
                <td class="px-5 py-5 text-center">
                  <ActionDropdownMenu :actions="getActions(row)" />
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <BasePagination
        v-if="!store.isLoading && sortedRows.length > pageSize"
        v-model:page="currentPage"
        :page-size="pageSize"
        :total="sortedRows.length"
        label="reports"
      />
    </section>

    <div
      v-if="auth.isAdmin && !showAdminRequestForm && reviewingCase"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
    >
      <div
        class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      >
        <header
          class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50/80"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <FileText class="w-4 h-4 text-accent" />
              <span class="section-label">Liquidation Review</span>
            </div>
            <!-- <h2 class="text-xl font-bold font-heading text-primary">
              {{ reviewingCase.id }} / {{ reviewingCase.advanceId }}
            </h2> -->
          </div>
          <button
            class="inline-flex items-center justify-center w-10 h-10 transition-colors rounded-full text-slate-500 hover:bg-slate-100 hover:text-danger"
            type="button"
            title="Close review"
            @click="closeReview"
          >
            <X class="h-5 w-5 stroke-[1.75]" />
          </button>
        </header>

        <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto bg-slate-50/40">
          <section
            class="grid grid-cols-1 gap-4 p-4 bg-white border rounded-lg border-slate-200 md:grid-cols-4"
          >
            <!-- <div>
              <p class="mb-1 section-label">ID Code</p>
              <p class="font-mono text-sm font-bold text-slate-900">
                {{ reviewingCase.id }}
              </p>
            </div>
            <div>
              <p class="mb-1 section-label">Date</p>
              <p class="text-sm font-bold text-slate-800">
                {{ formatDateOnly(reviewingCase.dateOfAdvances) }}
              </p>
            </div> -->
            <div>
              <p class="mb-1 section-label">Name of Employee</p>
              <p class="text-sm font-bold text-slate-800">
                {{ reviewingCase.requestorName }}
              </p>
            </div>
            <div>
              <p class="mb-1 section-label">Settlement Due Date</p>
              <p class="text-sm font-bold text-slate-800">
                {{ formatDateOnly(reviewingCase.dueDate) }}
              </p>
            </div>
          </section>

          <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="p-5 bg-white border rounded-lg border-accent/20">
              <p class="mb-2 section-label">Original Cash Advance Amount</p>
              <p class="text-3xl font-bold font-heading text-primary">
                {{ formatPeso(reviewingCase.cashAdvanceAmount) }}
              </p>
            </div>
            <div class="p-5 bg-white border rounded-lg border-slate-200">
              <div class="flex items-center justify-between gap-3">
                <p class="section-label">Ending Balance</p>
                <span
                  :class="[
                    'rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                    statusBadgeClass(reviewStatus),
                  ]"
                >
                  {{ reviewStatus }}
                </span>
              </div>
              <p class="mt-2 text-3xl font-bold font-heading text-primary">
                {{ formatPeso(reviewOutstandingBalance) }}
              </p>
            </div>
          </section>

          <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-base font-bold font-heading text-slate-800">
                  Submitted Receipt Audit
                </h3>
                <p class="text-xs text-slate-400">
                  Accept or reject each receipt before finalizing the
                  liquidation balance.
                </p>
              </div>
              <span class="kpi-label text-slate-400"
                >{{ reviewReceipts.length }} receipts</span
              >
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <article
                v-for="receipt in reviewReceipts"
                :key="receipt.id"
                class="overflow-hidden transition-shadow bg-white border rounded-xl border-slate-200 hover:shadow-md"
              >
                <div class="aspect-[4/5] overflow-hidden bg-slate-100">
                  <img
                    :src="getFileUrl(receipt.filePath)"
                    alt="Scanned receipt"
                    class="object-cover object-top w-full h-full transition-transform duration-500 hover:scale-105"
                  />
                </div>
                <div class="flex flex-col gap-3 p-5">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <h4
                        class="text-sm font-bold truncate font-heading text-slate-900"
                      >
                        {{ receipt.merchantName }}
                      </h4>
                      <p class="text-xs truncate text-slate-400">
                        {{ receipt.location }}
                      </p>
                    </div>
                    <span
                      :class="[
                        'shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                        receipt.decision === 'accepted'
                          ? 'border-accent/20 bg-accent-50 text-accent'
                          : receipt.decision === 'rejected'
                            ? 'border-red-200 bg-red-50 text-red-700'
                            : 'border-slate-200 bg-slate-50 text-slate-500',
                      ]"
                    >
                      {{ receipt.decision }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between gap-3">
                    <span
                      class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent"
                      >{{ receipt.category }}</span
                    >
                    <span class="text-sm font-bold font-heading text-primary">{{
                      formatPeso(receipt.amount || 0)
                    }}</span>
                  </div>
                  <button
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-accent-50 px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-100"
                    type="button"
                    @click="viewReceiptDetails(receipt)"
                  >
                    <Eye class="w-4 h-4" />
                    View Receipt Details
                  </button>
                </div>
              </article>
            </div>
          </section>

          <section
            v-if="reviewingCase.adminNote"
            class="p-5 space-y-2 bg-white border rounded-xl border-slate-200"
          >
            <p class="section-label">Admin Notes</p>
            <p class="text-sm leading-relaxed text-slate-700">
              {{ reviewingCase.adminNote }}
            </p>
          </section>

          <section
            v-if="reviewingCase.reportFilePath"
            class="p-5 space-y-4 bg-white border rounded-xl border-slate-200"
          >
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-accent/10 text-accent"
              >
                <FileText class="w-5 h-5" />
              </span>
              <div>
                <h3 class="text-base font-bold font-heading text-slate-800">
                  Report Letter Attachment
                </h3>
                <p class="text-xs text-slate-400">
                  Supporting documentation for this liquidation.
                </p>
              </div>
            </div>
            <a
              :href="getFileUrl(reviewingCase.reportFilePath)"
              target="_blank"
              class="inline-flex w-fit items-center justify-center gap-2 rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200"
            >
              <Download class="w-4 h-4" />
              View / Download Report Letter
            </a>
          </section>
        </div>

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
                @click="openRejectModal"
              >
                <XCircle class="w-4 h-4" />
                Reject Settlement
              </button>
              <button
                class="inline-flex items-center justify-center gap-2 px-5 text-sm font-bold text-white transition-colors rounded-lg shadow-sm min-h-11 bg-emerald-800 hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-60"
                type="button"
                :disabled="isReviewingOwnLiquidation"
                @click="openApproveModal"
              >
                <ShieldCheck class="w-4 h-4" />
                Accept as Liquidation
              </button>
            </div>
          </div>
          <div
            v-else
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="text-sm font-semibold text-slate-500">
              Liquidation status:
              <span
                :class="[
                  'inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                  statusBadgeClass(reviewingCase.status),
                ]"
              >
                {{ reviewingCase.status }}
              </span>
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

    <div
      v-if="reviewingCase && receiptDetailsOpen && selectedReceipt"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
    >
      <div
        class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      >
        <header
          class="flex items-center justify-between px-5 py-4 text-white border-b border-primary/10 bg-primary"
        >
          <div class="flex items-center min-w-0 gap-4">
            <button
              class="inline-flex items-center gap-2 px-2 py-1 text-xs font-bold transition-colors rounded-md text-white/90 hover:bg-white/10"
              type="button"
              @click="closeReceiptDetails"
            >
              <ArrowLeft class="w-4 h-4" />
              Back
            </button>
            <div class="w-px h-6 bg-white/20" />
            <div class="flex items-center min-w-0 gap-2">
              <span
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg shrink-0 bg-white/10"
              >
                <CalendarDays class="w-4 h-4" />
              </span>
              <div class="min-w-0">
                <h3 class="text-lg font-bold text-white truncate font-heading">
                  Receipt Details
                </h3>
                <p class="text-xs font-semibold truncate text-white/65">
                  AI-scanned liquidation receipt extraction
                </p>
              </div>
            </div>
          </div>
          <button
            class="inline-flex items-center justify-center transition-colors rounded-full h-9 w-9 text-white/85 hover:bg-white/10 hover:text-white"
            type="button"
            title="Close receipt details"
            @click="closeReview"
          >
            <X class="w-5 h-5" />
          </button>
        </header>

        <div class="flex-1 p-5 overflow-y-auto bg-slate-50 scrollbar-thin">
          <div
            class="flex flex-col gap-3 px-4 py-3 mb-4 border rounded-lg border-accent/20 bg-accent-50 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex items-center gap-3">
              <span
                class="inline-flex items-center justify-center bg-white rounded-lg shadow-sm h-9 w-9 shrink-0 text-accent"
              >
                <Sparkles class="w-4 h-4" />
              </span>
              <div>
                <p
                  class="text-xs font-bold uppercase tracking-[0.12em] text-accent"
                >
                  AI Scanned
                </p>
                <p class="text-sm font-semibold text-primary">
                  Details automatically extracted from the submitted liquidation
                  receipt.
                </p>
              </div>
            </div>
            <span
              class="inline-flex w-fit items-center gap-1 rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent shadow-sm"
            >
              <CheckCircle class="h-3.5 w-3.5" />
              Verified fields
            </span>
          </div>

          <div
            class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
          >
            <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)]">
              <aside
                class="p-5 border-b border-slate-200 bg-slate-100/70 lg:border-b-0 lg:border-r"
              >
                <div class="flex items-center justify-between gap-3 mb-4">
                  <div class="min-w-0">
                    <p class="kpi-label text-slate-400">Receipt Preview</p>
                    <h4
                      class="mt-1 text-base font-bold truncate font-heading text-slate-900"
                    >
                      {{ selectedReceipt.merchantName }}
                    </h4>
                  </div>
                  <span
                    class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent"
                  >
                    {{ selectedReceipt.category }}
                  </span>
                </div>
                <div
                  class="overflow-hidden bg-white border rounded-lg shadow-sm border-slate-200"
                >
                  <img
                    :src="getFileUrl(selectedReceipt.filePath)"
                    alt="Scanned receipt"
                    class="h-full max-h-[520px] w-full object-cover object-top"
                  />
                </div>
                <button
                  class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-accent/20 bg-white px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-50"
                  type="button"
                >
                  <Download class="w-4 h-4" />
                  Download Receipt
                </button>
              </aside>

              <section class="p-5 space-y-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <label class="space-y-1">
                    <span class="input-label">Invoice Number</span>
                    <input
                      class="input"
                      readonly
                      :value="selectedReceipt.invoiceNumber"
                    />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Transaction Date</span>
                    <span class="relative block">
                      <input
                        class="pr-10 input"
                        readonly
                        :value="selectedReceipt.transactionDate"
                      />
                      <CalendarDays
                        class="absolute w-4 h-4 -translate-y-1/2 pointer-events-none right-3 top-1/2 text-slate-400"
                      />
                    </span>
                  </label>
                  <label class="space-y-1">
                    <span class="flex items-center justify-between gap-2">
                      <span class="input-label">TIN Number</span>
                      <span
                        class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent"
                      >
                        <Sparkles class="w-3 h-3" />
                        AI Read
                      </span>
                    </span>
                    <input
                      class="input"
                      readonly
                      :value="selectedReceipt.tinNumber"
                    />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Merchant Name</span>
                    <input
                      class="input"
                      readonly
                      :value="selectedReceipt.merchantName"
                    />
                  </label>
                  <label class="space-y-1 md:col-span-2">
                    <span class="input-label">Location</span>
                    <span class="relative block">
                      <input
                        class="input pl-9"
                        readonly
                        :value="selectedReceipt.location"
                      />
                      <MapPin
                        class="absolute w-4 h-4 -translate-y-1/2 pointer-events-none left-3 top-1/2 text-accent"
                      />
                    </span>
                  </label>
                  <label class="space-y-1 md:col-span-2">
                    <span class="flex items-center justify-between gap-2">
                      <span class="input-label"
                        >Category (AI Auto-Detected)</span
                      >
                      <span
                        class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent"
                      >
                        <Sparkles class="w-3 h-3" />
                        AI Detected
                      </span>
                    </span>
                    <select class="input" disabled>
                      <option selected>{{ selectedReceipt.category }}</option>
                    </select>
                  </label>
                </div>

                <div
                  class="overflow-hidden bg-white border rounded-lg border-slate-200"
                >
                  <div class="px-4 py-3 border-b border-slate-200 bg-slate-50">
                    <h4
                      class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500"
                    >
                      Order Items
                    </h4>
                  </div>
                  <table class="w-full text-sm text-left border-collapse">
                    <thead>
                      <tr
                        class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400"
                      >
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-right">Price</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr
                        v-for="item in selectedReceipt.items"
                        :key="item.name"
                      >
                        <td class="px-4 py-3 font-semibold text-slate-700">
                          {{ item.name }}
                        </td>
                        <td class="px-4 py-3 text-center text-slate-500">
                          {{ item.quantity }}
                        </td>
                        <td
                          class="px-4 py-3 font-semibold text-right text-slate-700"
                        >
                          {{ formatPeso(item.price) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div
                  class="grid grid-cols-1 gap-3 pt-4 border-t border-slate-200 sm:grid-cols-3"
                >
                  <label class="space-y-1">
                    <span class="input-label">Subtotal</span>
                    <input
                      class="font-semibold input"
                      readonly
                      :value="formatPeso(selectedReceipt.subtotal)"
                    />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Tax (VAT)</span>
                    <input
                      class="font-semibold input"
                      readonly
                      :value="formatPeso(selectedReceipt.vat)"
                    />
                  </label>
                  <div
                    class="p-3 border rounded-lg border-accent/20 bg-accent-50"
                  >
                    <p class="input-label text-accent">Orders Total</p>
                    <p class="mt-1 text-xl font-bold font-heading text-primary">
                      {{ formatPeso(selectedReceipt.amount || 0) }}
                    </p>
                  </div>
                </div>

                <footer
                  class="flex items-center gap-2 text-xs font-semibold text-slate-400"
                >
                  <FileText class="w-4 h-4" />
                  Uploaded with receipt {{ selectedReceipt.id }}
                </footer>

                <div
                  class="p-4 border rounded-xl border-slate-200 bg-slate-50/70"
                >
                  <label class="space-y-2">
                    <span class="input-label"
                      >Admin Notes for this Receipt</span
                    >
                    <textarea
                      v-model="selectedReceipt.notes"
                      class="bg-white resize-none input min-h-24"
                      placeholder="Leave comments or auditor feedback for this receipt..."
                    />
                  </label>
                </div>
              </section>
            </div>
          </div>
        </div>

        <div class="px-5 py-4 bg-white border-t border-slate-200">
          <div
            v-if="pendingReceiptDecision"
            class="flex flex-col gap-3 p-4 border rounded-lg border-accent/20 bg-accent-50 sm:flex-row sm:items-center sm:justify-between"
          >
            <p class="text-sm font-semibold text-primary">
              Are you sure you want to {{ pendingReceiptDecision }} this
              receipt?
            </p>
            <div class="flex items-center gap-2 shrink-0">
              <button
                class="inline-flex items-center justify-center px-4 text-xs font-bold transition-colors bg-white border rounded-lg min-h-9 border-slate-200 text-slate-600 hover:bg-slate-50"
                type="button"
                @click="cancelReceiptDecision"
              >
                Cancel
              </button>
              <button
                class="inline-flex items-center justify-center px-4 text-xs font-bold text-white transition-colors rounded-lg min-h-9 bg-accent hover:bg-accent/90"
                type="button"
                @click="confirmReceiptDecision"
              >
                Confirm
              </button>
            </div>
          </div>
          <div v-else class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button
              class="inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-red-700 transition-colors border border-red-200 rounded-lg min-h-10 bg-red-50 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="isReviewingOwnLiquidation"
              @click="requestReceiptDecision('rejected')"
            >
              <XCircle class="w-4 h-4" />
              Reject
            </button>
            <button
              class="inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-white transition-colors rounded-lg min-h-10 bg-accent hover:bg-accent/90 disabled:cursor-not-allowed disabled:opacity-60"
              type="button"
              :disabled="isReviewingOwnLiquidation"
              @click="requestReceiptDecision('accepted')"
            >
              <CheckCircle class="w-4 h-4" />
              Accept
            </button>
          </div>
        </div>
      </div>
    </div>

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
      class="grid grid-cols-1 gap-6 lg:grid-cols-5"
    >
      <div class="flex flex-col gap-4 lg:col-span-2">
        <h3
          class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400"
        >
          <ClipboardList class="h-3.5 w-3.5" />
          OUTSTANDING ADVANCES
        </h3>

        <div
          class="overflow-hidden bg-white border shadow-sm rounded-xl border-slate-200"
        >
          <div
            class="grid grid-cols-[minmax(0,1fr)_6.5rem_6rem] border-b border-slate-200 bg-slate-50"
          >
            <button
              v-for="option in employeeSortOptions"
              :key="option.value"
              class="flex min-h-11 items-center gap-1.5 px-4 text-[10px] font-bold uppercase tracking-[0.08em] transition-colors hover:text-accent"
              :class="[
                employeeSortKey === option.value
                  ? 'text-accent'
                  : 'text-slate-500',
                option.value === 'amount'
                  ? 'justify-end text-right'
                  : option.value === 'status'
                    ? 'justify-center text-center'
                    : 'justify-start text-left',
              ]"
              type="button"
              @click="
                employeeSortKey === option.value
                  ? (employeeSortDirection =
                      employeeSortDirection === 'asc' ? 'desc' : 'asc')
                  : ((employeeSortKey = option.value),
                    (employeeSortDirection = 'asc'))
              "
            >
              <span>{{ option.label }}</span>
              <ChevronUp
                v-if="
                  employeeSortKey === option.value &&
                  employeeSortDirection === 'asc'
                "
                class="h-3.5 w-3.5"
              />
              <ChevronDown
                v-else-if="employeeSortKey === option.value"
                class="h-3.5 w-3.5"
              />
              <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
            </button>
          </div>
        </div>

        <div
          v-if="!store.isLoading && employeeFilteredAdvances.length === 0"
          class="flex items-center justify-center p-6 text-center border-dashed card min-h-32"
        >
          <p
            class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400"
          >
            No advances match the current search or filter.
          </p>
        </div>

        <template v-if="store.isLoading">
          <div
            v-for="i in 4"
            :key="`employee-advance-skeleton-${i}`"
            class="p-4 card"
          >
            <SkeletonLoader variant="card" />
          </div>
        </template>

        <template v-else>
          <div
            v-for="adv in employeeFilteredAdvances"
            :key="adv.id"
            :class="[
              'card p-4 cursor-pointer transition-none group border-2',
              selectedAdvance?.id === adv.id
                ? 'border-primary bg-primary/[0.02]'
                : 'border-slate-100',
            ]"
            @click="selectAdvance(adv)"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1 min-w-0">
                <p
                  class="text-xs font-bold tracking-tight uppercase truncate text-slate-900"
                >
                  {{ adv.purpose }}
                </p>
              </div>
              <StatusBadge :status="employeeAdvanceBadgeStatus(adv)" />
            </div>

            <div class="flex items-end justify-between mt-4">
              <div>
                <p
                  class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400"
                >
                  OUTSTANDING BALANCE
                </p>
                <p
                  class="font-mono text-lg font-bold tracking-tighter text-primary"
                >
                  {{ formatPeso(adv.balance || 0) }}
                </p>
              </div>
              <div class="text-right">
                <p
                  class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400"
                >
                  AGE STATUS
                </p>
                <div class="flex flex-col items-end">
                  <span
                    :class="[
                      'text-[10px] font-bold uppercase',
                      liqStore.calculateAging(adv).isOverdue
                        ? 'text-danger'
                        : 'text-slate-500',
                    ]"
                  >
                    Day {{ liqStore.calculateAging(adv).daysSinceIssue }} of 7
                  </span>
                  <span
                    v-if="liqStore.calculateAging(adv).isOverdue"
                    class="font-mono text-[9px] font-bold text-danger"
                  >
                    PENALTY:
                    {{ formatPeso(liqStore.calculateAging(adv).penalty) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div class="lg:col-span-3">
        <div
          v-if="!selectedAdvance"
          class="flex flex-col items-center justify-center h-full gap-4 p-16 text-center border-2 border-dashed card bg-clinical/20"
        >
          <FilePieChart class="w-10 h-10 text-slate-200" />
          <p
            class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400"
          >
            Select an active advance to clear debt
          </p>
        </div>

        <div
          v-else-if="submitted"
          class="flex flex-col items-center gap-6 p-12 text-center border-t-2 card border-t-emerald-600"
        >
          <CheckCircle class="w-12 h-12 text-emerald-600" />
          <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-primary">
            Submission Received
          </h3>
          <p
            class="text-[11px] font-bold uppercase tracking-widest text-slate-500"
          >
            Technician report for {{ selectedAdvance.id }} sent to audit.
          </p>
          <BaseButton
            variant="secondary"
            @click="
              selectedAdvance = null;
              submitted = false;
            "
          >
            RELOAD CONSOLE
          </BaseButton>
        </div>

        <div
          v-else
          class="flex flex-col gap-6 p-6 border-t-2 shadow-sm card border-t-primary"
        >
          <div
            class="flex items-start justify-between pb-4 border-b border-slate-100"
          >
            <div>
              <p
                class="mb-1 text-[9px] font-bold uppercase tracking-tighter text-slate-700"
              >
                RECONCILING REF: {{ selectedAdvance.id }}
              </p>
              <h3
                class="text-xs font-bold tracking-widest uppercase text-primary"
              >
                {{ selectedAdvance.purpose }}
              </h3>
            </div>
            <div class="text-right">
              <p
                class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400"
              >
                OUTSTANDING BALANCE
              </p>
              <p
                class="font-mono text-2xl font-bold tracking-tighter text-primary"
              >
                {{ formatPeso(selectedAdvance.balance || 0) }}
              </p>
            </div>
          </div>

          <div class="pt-4 border-t input-wrapper border-slate-100">
            <div
              class="flex flex-col gap-2 mb-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <label class="input-label !mb-0"
                >DIGITAL RECEIPT ATTACHMENTS *</label
              >
              <span
                class="inline-flex w-fit items-center gap-1 rounded-full bg-accent-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent"
              >
                <Sparkles class="h-3.5 w-3.5" />
                OCR assisted verification
              </span>
            </div>
            <FileUpload
              v-model="receipts"
              :max-size-mb="2"
              empty-action-label="Upload Receipt"
              add-action-label="Upload Receipt"
              @upload-error="handleReceiptUploadError"
            />
          </div>

          <section
            v-if="receipts.length > 0"
            class="p-4 space-y-4 bg-white border rounded-xl border-slate-200"
          >
            <div
              class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p class="text-base font-bold font-heading text-primary">
                  Receipt Scanning & Extraction
                </p>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">
                  Verify the scanned receipt text and figures before submitting.
                </p>
              </div>
            </div>

            <article
              v-for="(receipt, index) in receipts"
              :key="receipt.name + index"
              class="overflow-hidden border rounded-xl border-slate-200 bg-slate-50"
            >
              <div class="grid grid-cols-1 lg:grid-cols-[180px_minmax(0,1fr)]">
                <aside
                  class="p-4 border-b border-slate-200 bg-slate-100/70 lg:border-b-0 lg:border-r"
                >
                  <p class="kpi-label text-slate-400">Receipt Preview</p>
                  <div
                    class="flex items-center justify-center mt-2 overflow-hidden bg-white border rounded-lg shadow-sm h-44 border-slate-200"
                  >
                    <img
                      v-if="receipt.preview"
                      :src="receipt.preview"
                      alt="Uploaded receipt preview"
                      class="object-cover object-top w-full h-full"
                    />
                    <FileText v-else class="w-8 h-8 text-slate-300" />
                  </div>
                </aside>

                <div class="p-4 space-y-4 bg-white">
                  <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                  >
                    <div class="min-w-0">
                      <p
                        class="text-sm font-bold truncate font-heading text-slate-900"
                      >
                        {{ receipt.ocrData?.vendor || receipt.name }}
                      </p>
                      <p
                        class="mt-0.5 truncate text-xs font-semibold text-slate-400"
                      >
                        {{ receipt.name }}
                      </p>
                    </div>
                    <span
                      :class="[
                        'w-fit rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                        receipt.ocrStatus === 'done'
                          ? 'border-accent/20 bg-accent-50 text-accent'
                          : 'border-amber-200 bg-amber-50 text-amber-700',
                      ]"
                    >
                      {{
                        receipt.ocrStatus === "done" ? "AI Scanned" : "Scanning"
                      }}
                    </span>
                  </div>

                  <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="space-y-1">
                      <span class="input-label">Merchant Name</span>
                      <input
                        class="bg-white input"
                        v-model="receipt.ocrData.vendor"
                      />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Date</span>
                      <span class="relative block">
                        <input
                          type="date"
                          class="pr-10 bg-white input"
                          v-model="receipt.ocrData.date"
                        />
                        <CalendarDays
                          class="absolute w-4 h-4 -translate-y-1/2 pointer-events-none right-3 top-1/2 text-slate-400"
                        />
                      </span>
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">TIN Number</span>
                      <input
                        class="bg-white input"
                        v-model="receipt.ocrData.tin"
                        inputmode="numeric"
                        maxlength="15"
                        placeholder="000-000-000-000"
                        @input="handleTinInput(receipt)"
                        @blur="handleTinBlur(receipt)"
                      />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Expense Category</span>
                      <select
                        v-model.number="receipt.categoryId"
                        class="bg-white input"
                      >
                        <option
                          v-for="category in receiptCategoryOptions"
                          :key="category.id"
                          :value="Number(category.id)"
                        >
                          {{ category.name }}
                        </option>
                      </select>
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Invoice Number</span>
                      <input
                        class="bg-white input"
                        v-model="receipt.ocrData.invoiceNumber"
                      />
                    </label>
                  </div>

                  <div
                    class="grid grid-cols-1 gap-3 pt-4 border-t border-slate-200 sm:grid-cols-3"
                  >
                    <label class="space-y-1">
                      <span class="input-label">Subtotal (Auto-Calc)</span>
                      <input
                        class="font-semibold cursor-not-allowed input bg-slate-100 text-slate-500"
                        disabled
                        :value="
                          formatPeso(
                            Math.max(
                              Number(receipt.ocrData?.amount || 0) -
                                Number(receipt.ocrData?.vat || 0),
                              0,
                            ),
                          )
                        "
                      />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Tax (VAT)</span>
                      <input
                        type="number"
                        step="0.01"
                        class="font-semibold bg-white input"
                        v-model.number="receipt.ocrData.vat"
                      />
                    </label>
                    <div
                      class="p-3 border rounded-lg border-accent/20 bg-accent-50"
                    >
                      <p class="input-label text-accent">Receipt Total</p>
                      <input
                        type="number"
                        step="0.01"
                        class="input font-semibold !bg-white !text-primary font-heading text-lg"
                        v-model.number="receipt.ocrData.amount"
                        @input="handleAmountChange(receipt)"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </article>
          </section>

          <section class="p-4 bg-white border rounded-xl border-slate-200">
            <input
              ref="reportAttachmentInput"
              type="file"
              accept="image/*,.pdf,.docx"
              class="hidden"
              @change="selectReportAttachment"
            />
            <div
              class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div class="min-w-0">
                <p class="text-base font-bold font-heading text-primary">
                  Cash Advance Report Letter
                </p>
                <p class="mt-1 text-sm text-slate-500">
                  Attach your report letter for liquidation documentation.
                </p>
                <p
                  v-if="reportAttachment"
                  class="mt-2 text-xs font-bold truncate text-accent"
                >
                  Attached: {{ reportAttachment.name }}
                </p>
              </div>
              <div class="flex gap-2 shrink-0">
                <button
                  v-if="reportAttachment"
                  class="inline-flex items-center justify-center px-4 text-xs font-bold transition-colors bg-white border rounded-lg min-h-10 border-slate-200 text-slate-600 hover:bg-slate-50"
                  type="button"
                  @click="clearReportAttachment"
                >
                  Clear
                </button>
                <button
                  class="inline-flex items-center justify-center gap-2 px-4 text-xs font-bold text-white transition-colors rounded-lg min-h-10 bg-accent hover:bg-accent/90"
                  type="button"
                  @click="reportAttachmentInput?.click()"
                >
                  <Upload class="w-4 h-4" />
                  Attachment
                </button>
              </div>
            </div>
          </section>

          <section
            v-if="
              existingLiquidation?.admin_note || existingLiquidation?.adminNote
            "
            class="p-4 bg-white border rounded-xl border-slate-200"
          >
            <p class="text-base font-bold font-heading text-primary">
              Admin Notes / Rejection Feedback
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
              {{
                existingLiquidation.admin_note || existingLiquidation.adminNote
              }}
            </p>
          </section>

          <section
            v-if="needsReportAttachmentReminder"
            class="p-4 border rounded-xl border-amber-200 bg-amber-50"
          >
            <div class="flex items-start gap-3">
              <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
              <p class="text-sm font-semibold text-amber-800">
                Don't forget to attach your report for overpayment.
              </p>
            </div>
          </section>

          <!-- Shortfall Explanation -->
          <section
            v-if="variance > 0"
            class="p-4 space-y-2 border rounded-xl border-amber-200 bg-amber-50/50"
          >
            <label class="block space-y-1">
              <span class="font-bold input-label text-amber-800"
                >Shortfall Explanation <span class="text-danger">*</span></span
              >
              <textarea
                v-model="shortfallExplanation"
                rows="3"
                class="bg-white resize-none input"
                placeholder="Explain why the total expense is less than the advanced amount (required)..."
              />
            </label>
          </section>

          <div class="p-5 mt-2 border border-slate-200 bg-clinical/20">
            <div class="flex items-center gap-2 mb-4">
              <Calculator class="w-4 h-4 opacity-50 text-primary" />
              <h4
                class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400"
              >
                The Reconciliation (Settlement)
              </h4>
            </div>

            <div class="flex">
              <div class="w-full space-y-4">
                <div class="flex items-center justify-between text-[11px]">
                  <span
                    class="font-bold tracking-tight uppercase text-slate-400"
                    >Total Balance:</span
                  >
                  <span class="font-mono font-bold text-primary">{{
                    formatPeso(
                      selectedAdvance.balance ?? selectedAdvance.amount ?? 0,
                    )
                  }}</span>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-bold tracking-tight uppercase text-danger"
                    >Total Expenses:</span
                  >
                  <span class="font-mono font-bold text-danger"
                    >-{{ formatPeso(totalExpenseAmount) }}</span
                  >
                </div>
                <div
                  class="flex items-center justify-between pt-2 border-t border-slate-200"
                >
                  <span
                    class="text-[10px] font-black uppercase tracking-widest text-slate-600"
                    >Outstanding Balance:</span
                  >
                  <span
                    :class="[
                      'font-mono text-lg font-black tracking-tighter',
                      liquidationStatus === 'Liquidated'
                        ? 'text-emerald-600'
                        : 'text-primary',
                    ]"
                  >
                    {{ formatPeso(liquidationOutstandingBalance) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <section
            v-if="overpaymentAmount > 0"
            class="p-4 border rounded-xl border-accent/20 bg-accent-50"
          >
            <div
              class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
              <div>
                <p class="text-base font-bold font-heading text-primary">
                  Overpayment Can Be Reimbursed
                </p>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                  Any excess amount spent beyond the cash advance can be filed
                  as a reimbursement. Current excess amount:
                  <span class="font-bold text-primary">{{
                    formatPeso(overpaymentAmount)
                  }}</span
                  >.
                </p>
              </div>
              <button
                class="inline-flex items-center justify-center gap-2 px-4 text-xs font-bold text-white transition-colors rounded-lg min-h-10 w-fit shrink-0 bg-accent hover:bg-accent/90"
                type="button"
                @click="forwardOverpaymentToReimbursement"
              >
                <Upload class="w-4 h-4" />
                Reimbursement Filing
              </button>
            </div>
          </section>

          <div class="flex justify-end gap-3 mt-4">
            <BaseButton
              v-if="
                existingLiquidation && existingLiquidation.status === 'pending'
              "
              id="delete-liquidation-btn"
              variant="danger"
              class="w-fit px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white"
              :disabled="submitting"
              @click="handleDeleteLiquidation"
            >
              DELETE SETTLEMENT
            </BaseButton>

            <BaseButton
              id="submit-liquidation-btn"
              variant="primary"
              class="w-fit px-4 py-2.5"
              :disabled="
                receipts.length === 0 ||
                hasIncompleteReceiptFields ||
                receipts.some((r) => r.ocrStatus === 'processing') ||
                totalExpenseAmount === 0 ||
                submitting ||
                (variance > 0 && !shortfallExplanation.trim())
              "
              @click="submitLiquidation"
            >
              <div v-if="submitting" class="flex items-center gap-2">
                <Activity class="w-4 h-4 animate-spin" />
                <span>{{
                  existingLiquidation
                    ? "UPDATING SETTLEMENT..."
                    : "ENCODING SETTLEMENT..."
                }}</span>
              </div>
              <div v-else class="flex items-center gap-2">
                <Upload class="w-4 h-4" />
                <span>{{
                  existingLiquidation ? "UPDATE SETTLEMENT" : "SUBMIT FOR AUDIT"
                }}</span>
              </div>
            </BaseButton>
          </div>
        </div>
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
