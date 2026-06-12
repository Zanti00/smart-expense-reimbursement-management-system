<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useLiquidationStore } from "@/stores/liquidation";
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
import { formatPeso } from "@/utils/formatters";
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
const auth = useAuthStore();
const router = useRouter();
const { addToast } = useToast();

onMounted(() => {
  store.fetchAll();
  liqStore.fetchSettlements();
});

const selectedAdvance = ref(null);
const receipts = ref([]);
const reportAttachment = ref(null);
const reportAttachmentInput = ref(null);
const submitting = ref(false);
const submitted = ref(false);
const shortfallExplanation = ref("");

// Audit state variables
const approvingId = ref(null);
const rejectingId = ref(null);
const confirmPassword = ref("");
const rejectionComment = ref("");
const isReviewSubmitting = ref(false);

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

const statusFilters = ["All", "Incomplete", "Overpayment", "Liquidated", "Overdue"];
const employeeStatusFilters = ["All", "Pending", "Approved", "Disbursed", "Overdue"];
const employeeSortOptions = [
  { value: "status", label: "Status" },
  { value: "date", label: "Date" },
  { value: "amount", label: "Total Amount" },
];

const totalExpenseAmount = computed(() =>
  receipts.value.reduce((sum, receipt) => sum + (Number(receipt.ocrData?.amount) || 0), 0),
);

const agingInfo = computed(() => {
  if (!selectedAdvance.value) return null;
  return liqStore.calculateAging(selectedAdvance.value);
});

const variance = computed(() => {
  if (!selectedAdvance.value) return 0;
  return selectedAdvance.value.amount - totalExpenseAmount.value;
});

const liquidationOutstandingBalance = computed(() => Math.max(variance.value, 0));
const liquidationStatus = computed(() =>
  selectedAdvance.value && liquidationOutstandingBalance.value === 0
    ? "Liquidated"
    : "Incomplete",
);
const overpaymentAmount = computed(() => Math.max(totalExpenseAmount.value - (selectedAdvance.value?.amount || 0), 0));
const needsReportAttachmentReminder = computed(() =>
  selectedAdvance.value &&
  (liquidationStatus.value === "Incomplete" || (overpaymentAmount.value > 0 && !reportAttachment.value)),
);

const employeeOutstandingAdvances = computed(() =>
  store.items.filter((item) => ["disbursed", "overdue"].includes(item.status)),
);

const employeeFilteredAdvances = computed(() => {
  const query = employeeSearchQuery.value.trim().toLowerCase();

  const rows = employeeOutstandingAdvances.value.filter((advance) => {
    const status = employeeAdvanceStatus(advance);
    const matchesStatus =
      employeeActiveStatus.value === "All" || status === employeeActiveStatus.value;
    const matchesSearch =
      !query ||
      [
        advance.id,
        advance.purpose,
        advance.status,
        status,
        formatPeso(advance.amount || 0),
      ].some((value) => String(value || "").toLowerCase().includes(query));

    return matchesStatus && matchesSearch;
  });

  const direction = employeeSortDirection.value === "asc" ? 1 : -1;
  return [...rows].sort((a, b) => {
    const aValue = employeeSortValue(a, employeeSortKey.value);
    const bValue = employeeSortValue(b, employeeSortKey.value);

    if (typeof aValue === "number" && typeof bValue === "number") {
      return (aValue - bValue) * direction;
    }

    return String(aValue).localeCompare(String(bValue), undefined, {
      numeric: true,
      sensitivity: "base",
    }) * direction;
  });
});

const tableColumns = [
  { key: "id", label: "ID" },
  { key: "requestorName", label: "Requestor Name" },
  { key: "dateOfAdvances", label: "Date of Advances" },
  { key: "dueDate", label: "Due Date" },
  { key: "cashAdvanceAmount", label: "Cash Advance Amount", align: "right" },
  { key: "outstandingBalance", label: "Outstanding Balance", align: "right" },
  { key: "status", label: "Status", align: "center" },
  { key: "actions", label: "Actions", align: "center" },
];

const receiptTemplates = [
  {
    merchantName: "Vikings Luxury Buffet",
    location: "SM Megamall, Mandaluyong City",
    category: "Meals",
    invoicePrefix: "VIK",
    transactionDate: "January 5, 2026",
    tinNumber: "009-872-551-000",
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
    transactionDate: "January 6, 2026",
    tinNumber: "214-583-097-000",
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
    transactionDate: "January 7, 2026",
    tinNumber: "000-421-190-000",
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
    transactionDate: "January 8, 2026",
    tinNumber: "104-774-203-000",
    items: [
      { name: "Tool kit", quantity: 1, price: 1850 },
      { name: "Safety gloves", quantity: 2, price: 520 },
    ],
  },
];

const fallbackCases = [
  makeCase({
    id: "LIQ-001",
    requestorName: "Ana Reyes",
    dateOfAdvances: "2026-05-28",
    dueDate: "2026-06-04",
    cashAdvanceAmount: 8000,
    receiptAmounts: [3500, 1500],
  }),
  makeCase({
    id: "LIQ-002",
    requestorName: "Marco Santos",
    dateOfAdvances: "2026-05-24",
    dueDate: "2026-06-02",
    cashAdvanceAmount: 15000,
    receiptAmounts: [7200, 5600, 4200],
  }),
  makeCase({
    id: "LIQ-003",
    requestorName: "Lia Cruz",
    dateOfAdvances: "2026-05-30",
    dueDate: "2026-06-06",
    cashAdvanceAmount: 10000,
    receiptAmounts: [4200, 3200, 2600],
  }),
  makeCase({
    id: "LIQ-004",
    requestorName: "Noel Garcia",
    dateOfAdvances: "2026-05-20",
    dueDate: "2026-05-27",
    cashAdvanceAmount: 6000,
    receiptAmounts: [1800, 1200],
  }),
];

const getFileUrl = (filePath) => {
  if (!filePath) return "/mock_receipt.png";
  if (filePath.startsWith("http://") || filePath.startsWith("https://")) return filePath;
  return `https://vbabvrcfqcmvvjwmzuwx.supabase.co/storage/v1/object/public/cash_advances/${filePath}`;
};

const mapBackendStatusToDisplayStatus = (backendStatus, row, acceptedTotal) => {
  if (backendStatus === 'liquidated') return 'Liquidated';
  if (backendStatus === 'rejected') return 'Rejected';
  return calculateLiquidationStatus(row, acceptedTotal);
};

const sourceCases = computed(() => {
  const rows = liqStore.settlements.map((item) => {
    const mappedReceipts = (item.receipts || []).map((r, rIdx) => {
      const subtotal = Math.max(Number(r.total_amount || 0) - Number(r.vat_amount || 0), 0);
      return {
        id: r.id,
        fileName: r.file_path ? r.file_path.split('/').pop() : `receipt_${rIdx + 1}.jpg`,
        merchantName: r.vendor_name || 'Unknown Vendor',
        location: r.location || 'N/A',
        category: r.category || 'Expense',
        invoiceNumber: r.invoice_number || 'N/A',
        transactionDate: r.transaction_date || r.created_at,
        tinNumber: r.tin || 'N/A',
        items: r.items || [],
        amount: Number(r.total_amount || 0),
        subtotal,
        vat: Number(r.vat_amount || 0),
        decision: r.status === 'rejected' ? 'rejected' : 'accepted',
        notes: r.admin_notes || '',
        filePath: r.file_path,
      };
    });

    const mockRow = {
      cashAdvanceAmount: Number(item.cash_advance?.amount || 0),
      dueDate: item.cash_advance?.expected_liquidation_date || item.cash_advance?.dueDate,
    };

    const displayStatus = mapBackendStatusToDisplayStatus(item.status, mockRow, Number(item.total_expense_amount || 0));

    return {
      id: `LIQ-${String(item.id).padStart(3, "0")}`,
      databaseId: item.id,
      advanceId: `CA-${String(item.cash_advance_id).padStart(3, "0")}`,
      cashAdvanceId: item.cash_advance_id,
      requestorName: item.user?.name || "Employee",
      dateOfAdvances: item.cash_advance?.date || item.created_at,
      dueDate: item.cash_advance?.expected_liquidation_date || item.cash_advance?.dueDate,
      cashAdvanceAmount: Number(item.cash_advance?.amount || 0),
      receipts: mappedReceipts,
      submittedReceiptTotal: Number(item.total_expense_amount || 0),
      shortfallExplanation: item.shortfall_explanation || '',
      adminNote: item.admin_note || '',
      reportFilePath: item.report_file_path || null,
      status: displayStatus,
    };
  });

  return rows.length ? rows : fallbackCases;
});

const liquidationRows = computed(() =>
  sourceCases.value.map((row) => {
    const draft = reviewDrafts.value[row.id];
    const acceptedTotal = draft ? acceptedReceiptTotal(row, draft.receipts) : row.submittedReceiptTotal;
    const outstandingBalance = Math.max(row.cashAdvanceAmount - acceptedTotal, 0);
    
    let status = row.status;
    if (status !== 'Liquidated' && status !== 'Rejected') {
      status = draft?.finalizedStatus || calculateLiquidationStatus(row, acceptedTotal);
    }

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
    const matchesStatus = activeStatus.value === "All" || row.status === activeStatus.value;
    const matchesSearch =
      !query ||
      [
        row.id,
        row.advanceId,
        row.requestorName,
        row.dateOfAdvances,
        row.dueDate,
        row.status,
        formatDateOnly(row.dateOfAdvances),
        formatDateOnly(row.dueDate),
        formatPeso(row.cashAdvanceAmount),
        formatPeso(row.outstandingBalance),
      ].some((value) => String(value || "").toLowerCase().includes(query));

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
  reviewingCase.value ? acceptedReceiptTotal(reviewingCase.value, reviewReceipts.value) : 0,
);

const reviewOutstandingBalance = computed(() =>
  reviewingCase.value
    ? Math.max(reviewingCase.value.cashAdvanceAmount - acceptedReviewTotal.value, 0)
    : 0,
);

const reviewStatus = computed(() =>
  reviewingCase.value
    ? calculateLiquidationStatus(reviewingCase.value, acceptedReviewTotal.value)
    : "Incomplete",
);

const liquidationKpis = computed(() => {
  const rows = liquidationRows.value;
  const incomplete = rows.filter((item) => item.status === "Incomplete").length;
  const liquidated = rows.filter((item) => item.status === "Liquidated").length;
  const outstanding = rows.reduce((sum, item) => sum + item.outstandingBalance, 0);

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
  const overdue = rows.filter((item) => employeeAdvanceStatus(item) === "Overdue").length;
  const readyForLiquidation = rows.filter((item) =>
    ["Approved", "Disbursed"].includes(employeeAdvanceStatus(item)),
  ).length;
  const outstanding = rows.reduce((sum, item) => sum + Number(item.amount || 0), 0);

  return [
    {
      label: "Active Advances",
      value: rows.length,
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
  if (liqStore.calculateAging(advance).isOverdue) return "Overdue";
  const status = String(advance.status || "pending").toLowerCase();
  if (status === "approved") return "Approved";
  if (status === "disbursed") return "Disbursed";
  if (status === "pending") return "Pending";
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function employeeSortValue(advance, key) {
  if (key === "amount") return Number(advance.amount) || 0;
  if (key === "date") {
    const timestamp = new Date(
      advance.date || advance.submitted_at || advance.created_at || advance.dueDate || 0,
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
    await liqStore.submitSettlement(selectedAdvance.value.id, payload);

    const item = store.items.find((i) => i.id === selectedAdvance.value.id);
    if (item) {
      item.status = "liquidated"; // matches backend lock transition
      item.balance = 0;
    }

    submitting.value = false;
    submitted.value = true;

    await store.fetchAll();
    await liqStore.fetchSettlements();
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
  confirmPassword.value = "";
  rejectionComment.value = "";
  approvingId.value = reviewingCase.value.databaseId;
  rejectingId.value = null;
}

function openRejectModal() {
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
  isReviewSubmitting.value = true;
  try {
    await liqStore.auditSettlement(approvingId.value, {
      status: 'approved',
      password: confirmPassword.value,
    });
    addToast({
      title: "Settlement Approved",
      message: "The liquidation settlement was successfully approved.",
      type: "success",
    });
    
    await store.fetchAll();
    await liqStore.fetchSettlements();
    
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
      status: 'rejected',
      password: confirmPassword.value,
      admin_note: rejectionComment.value,
    });
    addToast({
      title: "Settlement Rejected",
      message: "The liquidation settlement was successfully rejected.",
      type: "success",
    });
    
    await store.fetchAll();
    await liqStore.fetchSettlements();
    
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

function selectAdvance(adv) {
  selectedAdvance.value = adv;
  submitted.value = false;
  receipts.value = [];
  reportAttachment.value = null;
}

function selectReportAttachment(event) {
  const file = event.target.files?.[0];
  if (file) reportAttachment.value = file;
  event.target.value = "";
}

function clearReportAttachment() {
  reportAttachment.value = null;
}

function forwardOverpaymentToReimbursement() {
  if (!selectedAdvance.value || overpaymentAmount.value <= 0) return;

  const forwardedReceipts = receipts.value.map((receipt, index) => ({
    id: `LIQ-${selectedAdvance.value.id}-${index + 1}`,
    fileName: receipt.name || receipt.file?.name || `Liquidation Receipt ${index + 1}`,
    fileType: receipt.file?.type || "application/pdf",
    thumbnail: receipt.preview || "",
    amount: Number(receipt.ocrData?.amount ?? receipt.amount ?? 0),
    date: receipt.ocrData?.date || new Date().toISOString().slice(0, 10),
    category: "Liquidation Overpayment",
    source: "liquidation-overpayment",
    cashAdvanceId: selectedAdvance.value.id,
    cashAdvanceAmount: selectedAdvance.value.amount || 0,
    excessAmount: overpaymentAmount.value,
  }));

  sessionStorage.setItem("serms_forwarded_liquidation_receipts", JSON.stringify(forwardedReceipts));
  router.push("/reimbursements/new");
}

function makeCase({ id, advanceId, requestorName, dateOfAdvances, dueDate, cashAdvanceAmount, receiptAmounts }) {
  const receipts = receiptAmounts.map((amount, index) => makeReceipt(id, amount, index));
  return {
    id,
    advanceId: advanceId || id.replace("LIQ", "CA"),
    requestorName,
    dateOfAdvances,
    dueDate,
    cashAdvanceAmount,
    receipts,
    submittedReceiptTotal: receiptAmounts.reduce((sum, amount) => sum + amount, 0),
  };
}

function makeReceipt(caseId, amount, index) {
  const template = receiptTemplates[index % receiptTemplates.length];
  const subtotal = amount * 0.88;
  const vat = amount * 0.12;

  return {
    id: `${caseId}-R${index + 1}`,
    fileName: `liquidation_receipt_${index + 1}.jpg`,
    merchantName: template.merchantName,
    location: template.location,
    category: template.category,
    invoiceNumber: `${template.invoicePrefix}-2026-${String(index + 2381).padStart(6, "0")}`,
    transactionDate: template.transactionDate,
    tinNumber: template.tinNumber,
    items: template.items,
    amount,
    subtotal,
    vat,
  };
}

function seededReceiptAmounts(amount, index) {
  if (!amount) return [0];
  const patterns = [
    [0.45, 0.35],
    [0.5, 0.35, 0.25],
    [0.4, 0.34, 0.26],
    [0.35, 0.2],
  ];
  return patterns[index % patterns.length].map((ratio) => Math.round(amount * ratio));
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
  if (["cashAdvanceAmount", "outstandingBalance"].includes(key)) return Number(row[key] || 0);
  if (["dateOfAdvances", "dueDate"].includes(key)) return new Date(row[key] || 0).getTime();
  if (key === "actions") return row.id;
  return String(row[key] || "").toLowerCase();
}

function openReview(row) {
  if (!reviewDrafts.value[row.id]) {
    reviewDrafts.value[row.id] = {
      receipts: row.receipts.map((receipt) => ({
        ...receipt,
        decision: "accepted",
        notes: "",
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
  pendingReceiptDecision.value = decision;
}

function cancelReceiptDecision() {
  pendingReceiptDecision.value = "";
}

function confirmReceiptDecision() {
  if (!selectedReceipt.value || !pendingReceiptDecision.value) return;
  setReceiptDecision(selectedReceipt.value.id, pendingReceiptDecision.value);
  pendingReceiptDecision.value = "";
}

function finalizeLiquidation() {
  if (!reviewingCase.value || !activeDraft.value) return;
  activeDraft.value.finalizedStatus = reviewStatus.value;
  confirmFinalizeOpen.value = false;
  closeReview();
}
</script>

<template>
  <div v-if="auth.isAdmin" class="mx-auto flex w-full max-w-7xl flex-col gap-6 font-sans">
    <section class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <ArchiveRestore class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">Settlement Operations</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Liquidation Console
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Review employee liquidation reports, receipts, and settlement balances
        </p>
      </div>
    </section>

    <BaseKpiGrid
      :kpis="liquidationKpis"
      gridClasses="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="4"
    />

    <BaseUtilityToolbar
      v-model:search="searchQuery"
      v-model:status-value="activeStatus"
      :statuses="statusFilters"
      searchPlaceholder="Search liquidation ID, employee, status, or amount..."
    />

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-1 border-b border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-heading text-base font-bold leading-tight text-slate-800">
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
        <table class="w-full min-w-[1180px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                v-for="column in tableColumns"
                :key="column.key"
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
                :class="column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left'"
              >
                <button
                  class="inline-flex w-full items-center gap-2 transition-colors hover:text-accent"
                  :class="column.align === 'right' ? 'justify-end' : column.align === 'center' ? 'justify-center' : 'justify-start'"
                  type="button"
                  @click="toggleSort(column)"
                >
                  <span>{{ column.label }}</span>
                  <ChevronUp v-if="sortKey === column.key && sortDirection === 'asc'" class="h-3.5 w-3.5 text-accent" />
                  <ChevronDown v-else-if="sortKey === column.key" class="h-3.5 w-3.5 text-accent" />
                  <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
                </button>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-if="store.isLoading">
              <tr v-for="i in pageSize" :key="`liquidation-skeleton-${i}`" class="whitespace-nowrap">
                <td v-for="col in tableColumns.length" :key="col" class="px-5 py-5">
                  <div
                    v-if="col === tableColumns.length"
                    class="mx-auto flex h-8 w-16 max-w-full animate-pulse items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 sm:h-9 sm:w-20 sm:gap-2"
                  >
                    <div class="h-3 w-3 shrink-0 rounded bg-slate-200 sm:h-3.5 sm:w-3.5"></div>
                    <div class="h-2.5 w-5 rounded bg-slate-200 sm:w-7"></div>
                  </div>
                  <div
                    v-else
                    class="h-3.5 max-w-full animate-pulse rounded bg-slate-200"
                    :class="[
                      col === 7 ? 'mx-auto h-5 w-20 rounded-full sm:w-24' : '',
                      col === 1 ? 'w-12 sm:w-16' : '',
                      col === 2 ? 'w-24 sm:w-32' : '',
                      [5, 6].includes(col) ? 'ml-auto w-20 sm:w-24' : '',
                      ![1, 2, 5, 6, 7, tableColumns.length].includes(col) ? 'w-20 sm:w-28' : '',
                    ]"
                  ></div>
                </td>
              </tr>
            </template>
            <tr v-else-if="sortedRows.length === 0">
              <td :colspan="tableColumns.length" class="px-5 py-10 text-center text-sm font-semibold text-slate-400">
                No liquidation reports found.
              </td>
            </tr>
            <template v-else>
              <tr
                v-for="row in paginatedRows"
                :key="row.id"
                class="whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
              >
                <td class="px-5 py-5 font-mono text-sm font-bold text-slate-900">{{ row.id }}</td>
                <td class="px-5 py-5 text-sm font-semibold text-slate-700">{{ row.requestorName }}</td>
                <td class="px-5 py-5 text-sm text-slate-500">{{ formatDateOnly(row.dateOfAdvances) }}</td>
                <td class="px-5 py-5 text-sm text-slate-500">{{ formatDateOnly(row.dueDate) }}</td>
                <td class="px-5 py-5 text-right text-sm font-bold text-primary">{{ formatPeso(row.cashAdvanceAmount) }}</td>
                <td class="px-5 py-5 text-right text-sm font-semibold text-slate-700">{{ formatPeso(row.outstandingBalance) }}</td>
                <td class="px-5 py-5 text-center">
                  <span :class="['inline-flex rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide', statusBadgeClass(row.status)]">
                    {{ row.status }}
                  </span>
                </td>
                <td class="px-5 py-5 text-center">
                  <button
                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg border border-accent/15 bg-accent/5 px-3 text-xs font-bold text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.02] focus:outline-none"
                    type="button"
                    title="View liquidation"
                    @click="openReview(row)"
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
        v-if="!store.isLoading && sortedRows.length > pageSize"
        v-model:page="currentPage"
        :page-size="pageSize"
        :total="sortedRows.length"
        label="reports"
      />
    </section>

    <div
      v-if="reviewingCase"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
    >
      <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-6 py-4">
          <div class="min-w-0">
            <div class="mb-1 flex items-center gap-2">
              <FileText class="h-4 w-4 text-accent" />
              <span class="section-label">Liquidation Review</span>
            </div>
            <h2 class="font-heading text-xl font-bold text-primary">
              {{ reviewingCase.id }} / {{ reviewingCase.advanceId }}
            </h2>
          </div>
          <button
            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
            type="button"
            title="Close review"
            @click="closeReview"
          >
            <X class="h-5 w-5 stroke-[1.75]" />
          </button>
        </header>

        <div class="flex-1 space-y-5 overflow-y-auto bg-slate-50/40 px-6 py-5">
          <section class="grid grid-cols-1 gap-4 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            <div>
              <p class="section-label mb-1">ID Code</p>
              <p class="font-mono text-sm font-bold text-slate-900">{{ reviewingCase.id }}</p>
            </div>
            <div>
              <p class="section-label mb-1">Date</p>
              <p class="text-sm font-bold text-slate-800">{{ formatDateOnly(reviewingCase.dateOfAdvances) }}</p>
            </div>
            <div>
              <p class="section-label mb-1">Name of Employee</p>
              <p class="text-sm font-bold text-slate-800">{{ reviewingCase.requestorName }}</p>
            </div>
            <div>
              <p class="section-label mb-1">Settlement Due Date</p>
              <p class="text-sm font-bold text-slate-800">{{ formatDateOnly(reviewingCase.dueDate) }}</p>
            </div>
          </section>

          <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-accent/20 bg-white p-5">
              <p class="section-label mb-2">Original Cash Advance Amount</p>
              <p class="font-heading text-3xl font-bold text-primary">
                {{ formatPeso(reviewingCase.cashAdvanceAmount) }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5">
              <div class="flex items-center justify-between gap-3">
                <p class="section-label">Current Outstanding Balance</p>
                <span :class="['rounded-full border px-3 py-1 text-[10px] font-bold uppercase tracking-wide', statusBadgeClass(reviewStatus)]">
                  {{ reviewStatus }}
                </span>
              </div>
              <p class="mt-2 font-heading text-3xl font-bold text-primary">
                {{ formatPeso(reviewOutstandingBalance) }}
              </p>
            </div>
          </section>

          <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="font-heading text-base font-bold text-slate-800">
                  Submitted Receipt Audit
                </h3>
                <p class="text-xs text-slate-400">
                  Accept or reject each receipt before finalizing the liquidation balance.
                </p>
              </div>
              <span class="kpi-label text-slate-400">{{ reviewReceipts.length }} receipts</span>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <article
                v-for="receipt in reviewReceipts"
                :key="receipt.id"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white transition-shadow hover:shadow-md"
              >
                <div class="aspect-[4/5] overflow-hidden bg-slate-100">
                  <img
                    :src="getFileUrl(receipt.filePath)"
                    alt="Scanned receipt"
                    class="h-full w-full object-cover object-top transition-transform duration-500 hover:scale-105"
                  />
                </div>
                <div class="flex flex-col gap-3 p-5">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <h4 class="truncate font-heading text-sm font-bold text-slate-900">{{ receipt.merchantName }}</h4>
                      <p class="truncate text-xs text-slate-400">{{ receipt.location }}</p>
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
                    <span class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent">{{ receipt.category }}</span>
                    <span class="font-heading text-sm font-bold text-primary">{{ formatPeso(receipt.amount || 0) }}</span>
                  </div>
                  <button
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-accent-50 px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-100"
                    type="button"
                    @click="viewReceiptDetails(receipt)"
                  >
                    <Eye class="h-4 w-4" />
                    View Receipt Details
                  </button>
                </div>
              </article>
            </div>
          </section>

          <section v-if="reviewingCase.reportFilePath" class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <div class="flex items-center gap-3">
              <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent">
                <FileText class="h-5 w-5" />
              </span>
              <div>
                <h3 class="font-heading text-base font-bold text-slate-800">Report Letter Attachment</h3>
                <p class="text-xs text-slate-400">Supporting documentation for this liquidation.</p>
              </div>
            </div>
            <a
              :href="getFileUrl(reviewingCase.reportFilePath)"
              target="_blank"
              class="inline-flex w-fit items-center justify-center gap-2 rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-200"
            >
              <Download class="h-4 w-4" />
              View / Download Report Letter
            </a>
          </section>
        </div>

        <footer class="relative border-t border-slate-200 bg-white px-6 py-4">
          <div
            v-if="reviewingCase.status !== 'Liquidated' && reviewingCase.status !== 'Rejected'"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="text-sm font-semibold text-slate-500">
              Accepted receipts total:
              <span class="font-bold text-primary">{{ formatPeso(acceptedReviewTotal) }}</span>
            </div>
            <div class="flex gap-2">
              <button
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-5 text-sm font-bold text-red-700 transition-colors hover:bg-red-100"
                type="button"
                @click="openRejectModal"
              >
                <XCircle class="h-4 w-4" />
                Reject Settlement
              </button>
              <button
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-emerald-800 px-5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-emerald-900"
                type="button"
                @click="openApproveModal"
              >
                <ShieldCheck class="h-4 w-4" />
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
              <span :class="['inline-flex rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide', statusBadgeClass(reviewingCase.status)]">
                {{ reviewingCase.status }}
              </span>
            </div>
            <div v-if="reviewingCase.adminNote" class="text-xs text-slate-500 max-w-md italic">
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
      <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-primary/10 bg-primary px-5 py-4 text-white">
          <div class="flex min-w-0 items-center gap-4">
            <button
              class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-xs font-bold text-white/90 transition-colors hover:bg-white/10"
              type="button"
              @click="closeReceiptDetails"
            >
              <ArrowLeft class="h-4 w-4" />
              Back
            </button>
            <div class="h-6 w-px bg-white/20" />
            <div class="flex min-w-0 items-center gap-2">
              <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
                <CalendarDays class="h-4 w-4" />
              </span>
              <div class="min-w-0">
                <h3 class="truncate font-heading text-lg font-bold text-white">
                  Receipt Details
                </h3>
                <p class="truncate text-xs font-semibold text-white/65">
                  AI-scanned liquidation receipt extraction
                </p>
              </div>
            </div>
          </div>
          <button
            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white/85 transition-colors hover:bg-white/10 hover:text-white"
            type="button"
            title="Close receipt details"
            @click="closeReview"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <div class="flex-1 overflow-y-auto bg-slate-50 p-5 scrollbar-thin">
          <div class="mb-4 flex flex-col gap-3 rounded-lg border border-accent/20 bg-accent-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-accent shadow-sm">
                <Sparkles class="h-4 w-4" />
              </span>
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-accent">AI Scanned</p>
                <p class="text-sm font-semibold text-primary">Details automatically extracted from the submitted liquidation receipt.</p>
              </div>
            </div>
            <span class="inline-flex w-fit items-center gap-1 rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent shadow-sm">
              <CheckCircle class="h-3.5 w-3.5" />
              Verified fields
            </span>
          </div>

          <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)]">
              <aside class="border-b border-slate-200 bg-slate-100/70 p-5 lg:border-b-0 lg:border-r">
                <div class="mb-4 flex items-center justify-between gap-3">
                  <div class="min-w-0">
                    <p class="kpi-label text-slate-400">Receipt Preview</p>
                    <h4 class="mt-1 truncate font-heading text-base font-bold text-slate-900">{{ selectedReceipt.merchantName }}</h4>
                  </div>
                  <span class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent">
                    {{ selectedReceipt.category }}
                  </span>
                </div>
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
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
                  <Download class="h-4 w-4" />
                  Download Receipt
                </button>
              </aside>

              <section class="space-y-5 p-5">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <label class="space-y-1">
                    <span class="input-label">Invoice Number</span>
                    <input class="input" readonly :value="selectedReceipt.invoiceNumber" />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Transaction Date</span>
                    <span class="relative block">
                      <input class="input pr-10" readonly :value="selectedReceipt.transactionDate" />
                      <CalendarDays class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    </span>
                  </label>
                  <label class="space-y-1">
                    <span class="flex items-center justify-between gap-2">
                      <span class="input-label">TIN Number</span>
                      <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                        <Sparkles class="h-3 w-3" />
                        AI Read
                      </span>
                    </span>
                    <input class="input" readonly :value="selectedReceipt.tinNumber" />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Merchant Name</span>
                    <input class="input" readonly :value="selectedReceipt.merchantName" />
                  </label>
                  <label class="space-y-1 md:col-span-2">
                    <span class="input-label">Location</span>
                    <span class="relative block">
                      <input class="input pl-9" readonly :value="selectedReceipt.location" />
                      <MapPin class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-accent" />
                    </span>
                  </label>
                  <label class="space-y-1 md:col-span-2">
                    <span class="flex items-center justify-between gap-2">
                      <span class="input-label">Category (AI Auto-Detected)</span>
                      <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                        <Sparkles class="h-3 w-3" />
                        AI Detected
                      </span>
                    </span>
                    <select class="input" disabled>
                      <option selected>{{ selectedReceipt.category }}</option>
                    </select>
                  </label>
                </div>

                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                  <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                    <h4 class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Order Items</h4>
                  </div>
                  <table class="w-full border-collapse text-left text-sm">
                    <thead>
                      <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3 text-center">Qty</th>
                        <th class="px-4 py-3 text-right">Price</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="item in selectedReceipt.items" :key="item.name">
                        <td class="px-4 py-3 font-semibold text-slate-700">{{ item.name }}</td>
                        <td class="px-4 py-3 text-center text-slate-500">{{ item.quantity }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ formatPeso(item.price) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 sm:grid-cols-3">
                  <label class="space-y-1">
                    <span class="input-label">Subtotal</span>
                    <input class="input font-semibold" readonly :value="formatPeso(selectedReceipt.subtotal)" />
                  </label>
                  <label class="space-y-1">
                    <span class="input-label">Tax (VAT)</span>
                    <input class="input font-semibold" readonly :value="formatPeso(selectedReceipt.vat)" />
                  </label>
                  <div class="rounded-lg border border-accent/20 bg-accent-50 p-3">
                    <p class="input-label text-accent">Orders Total</p>
                    <p class="mt-1 font-heading text-xl font-bold text-primary">{{ formatPeso(selectedReceipt.amount || 0) }}</p>
                  </div>
                </div>

                <footer class="flex items-center gap-2 text-xs font-semibold text-slate-400">
                  <FileText class="h-4 w-4" />
                  Uploaded with receipt {{ selectedReceipt.id }}
                </footer>

                <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                  <label class="space-y-2">
                    <span class="input-label">Admin Notes for this Receipt</span>
                    <textarea
                      v-model="selectedReceipt.notes"
                      class="input min-h-24 resize-none bg-white"
                      placeholder="Leave comments or auditor feedback for this receipt..."
                    />
                  </label>
                </div>
              </section>
            </div>
          </div>
        </div>

        <div class="border-t border-slate-200 bg-white px-5 py-4">
          <div
            v-if="pendingReceiptDecision"
            class="flex flex-col gap-3 rounded-lg border border-accent/20 bg-accent-50 p-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <p class="text-sm font-semibold text-primary">
              Are you sure you want to {{ pendingReceiptDecision }} this receipt?
            </p>
            <div class="flex shrink-0 items-center gap-2">
              <button
                class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
                type="button"
                @click="cancelReceiptDecision"
              >
                Cancel
              </button>
              <button
                class="inline-flex min-h-9 items-center justify-center rounded-lg bg-accent px-4 text-xs font-bold text-white transition-colors hover:bg-accent/90"
                type="button"
                @click="confirmReceiptDecision"
              >
                Confirm
              </button>
            </div>
          </div>
          <div v-else class="flex flex-col gap-2 sm:flex-row sm:justify-end">
            <button
              class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-bold text-red-700 transition-colors hover:bg-red-100"
              type="button"
              @click="requestReceiptDecision('rejected')"
            >
              <XCircle class="h-4 w-4" />
              Reject
            </button>
            <button
              class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-sm font-bold text-white transition-colors hover:bg-accent/90"
              type="button"
              @click="requestReceiptDecision('accepted')"
            >
              <CheckCircle class="h-4 w-4" />
              Accept
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-else class="flex flex-col gap-6 font-sans">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <ArchiveRestore class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">Settlement Operations</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Liquidation Console
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Reconcile outstanding advances and settle balances
        </p>
      </div>
    </div>

    <BaseKpiGrid
      :kpis="employeeLiquidationKpis"
      gridClasses="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="4"
    />

    <BaseUtilityToolbar
      v-model:search="employeeSearchQuery"
      v-model:status-value="employeeActiveStatus"
      :statuses="employeeStatusFilters"
      searchPlaceholder="Search advance ID, purpose, status, or amount..."
    />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
      <div class="flex flex-col gap-4 lg:col-span-2">
        <h3 class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
          <ClipboardList class="h-3.5 w-3.5" />
          OUTSTANDING_ADVANCES
        </h3>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="grid grid-cols-[minmax(0,1fr)_6.5rem_6rem] border-b border-slate-200 bg-slate-50">
            <button
              v-for="option in employeeSortOptions"
              :key="option.value"
              class="flex min-h-11 items-center gap-1.5 px-4 text-[10px] font-bold uppercase tracking-[0.08em] transition-colors hover:text-accent"
              :class="[
                employeeSortKey === option.value ? 'text-accent' : 'text-slate-500',
                option.value === 'amount' ? 'justify-end text-right' : option.value === 'status' ? 'justify-center text-center' : 'justify-start text-left',
              ]"
              type="button"
              @click="
                employeeSortKey === option.value
                  ? (employeeSortDirection = employeeSortDirection === 'asc' ? 'desc' : 'asc')
                  : ((employeeSortKey = option.value), (employeeSortDirection = 'asc'))
              "
            >
              <span>{{ option.label }}</span>
              <ChevronUp
                v-if="employeeSortKey === option.value && employeeSortDirection === 'asc'"
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
          class="card flex min-h-32 items-center justify-center border-dashed p-6 text-center"
        >
          <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
            No advances match the current search or filter.
          </p>
        </div>

        <template v-if="store.isLoading">
          <div
            v-for="i in 4"
            :key="`employee-advance-skeleton-${i}`"
            class="card p-4"
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
              <div class="min-w-0 flex-1">
                <p class="mb-0.5 text-[9px] font-bold uppercase tracking-tighter text-slate-700">
                  REF: {{ adv.id }}
                </p>
                <p class="truncate text-xs font-bold uppercase tracking-tight text-slate-900">
                  {{ adv.purpose }}
                </p>
              </div>
              <StatusBadge :status="liqStore.calculateAging(adv).isOverdue ? 'overdue' : adv.status" />
            </div>

            <div class="mt-4 flex items-end justify-between">
              <div>
                <p class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400">
                  TOTAL_ISSUED
                </p>
                <p class="font-mono text-lg font-bold tracking-tighter text-primary">
                  {{ formatPeso(adv.amount || 0) }}
                </p>
              </div>
              <div class="text-right">
                <p class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400">
                  AGE_STATUS
                </p>
                <div class="flex flex-col items-end">
                  <span
                    :class="[
                      'text-[10px] font-bold uppercase',
                      liqStore.calculateAging(adv).isOverdue ? 'text-danger' : 'text-slate-500',
                    ]"
                  >
                    Day {{ liqStore.calculateAging(adv).daysSinceIssue }} of 7
                  </span>
                  <span
                    v-if="liqStore.calculateAging(adv).isOverdue"
                    class="font-mono text-[9px] font-bold text-danger"
                  >
                    PENALTY: {{ formatPeso(liqStore.calculateAging(adv).penalty) }}
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
          class="card flex h-full flex-col items-center justify-center gap-4 border-2 border-dashed bg-clinical/20 p-16 text-center"
        >
          <FilePieChart class="h-10 w-10 text-slate-200" />
          <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
            Select an active advance to clear debt
          </p>
        </div>

        <div
          v-else-if="submitted"
          class="card flex flex-col items-center gap-6 border-t-2 border-t-emerald-600 p-12 text-center"
        >
          <CheckCircle class="h-12 w-12 text-emerald-600" />
          <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-primary">
            Submission Received
          </h3>
          <p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">
            Technician report for {{ selectedAdvance.id }} sent to audit.
          </p>
          <BaseButton variant="secondary" @click="selectedAdvance = null; submitted = false">
            RELOAD CONSOLE
          </BaseButton>
        </div>

        <div v-else class="card flex flex-col gap-6 border-t-2 border-t-primary p-6 shadow-sm">
          <div class="flex items-start justify-between border-b border-slate-100 pb-4">
            <div>
              <p class="mb-1 text-[9px] font-bold uppercase tracking-tighter text-slate-700">
                RECONCILING REF: {{ selectedAdvance.id }}
              </p>
              <h3 class="text-xs font-bold uppercase tracking-widest text-primary">
                {{ selectedAdvance.purpose }}
              </h3>
            </div>
            <div class="text-right">
              <p class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400">
                CASH_ADVANCE
              </p>
              <p class="font-mono text-2xl font-bold tracking-tighter text-primary">
                {{ formatPeso(selectedAdvance.amount || 0) }}
              </p>
            </div>
          </div>


          <div class="input-wrapper border-t border-slate-100 pt-4">
            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <label class="input-label !mb-0">DIGITAL RECEIPT ATTACHMENTS *</label>
              <span class="inline-flex w-fit items-center gap-1 rounded-full bg-accent-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent">
                <Sparkles class="h-3.5 w-3.5" />
                OCR assisted verification
              </span>
            </div>
            <FileUpload
              v-model="receipts"
              empty-action-label="Upload Receipt"
              add-action-label="Upload Receipt"
            />
          </div>

          <section
            v-if="receipts.length > 0"
            class="space-y-4 rounded-xl border border-slate-200 bg-white p-4"
          >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="font-heading text-base font-bold text-primary">Receipt Scanning & Extraction</p>
                <p class="mt-0.5 text-xs font-semibold text-slate-400">
                  Verify the scanned receipt text and figures before submitting.
                </p>
              </div>
              <span class="inline-flex w-fit items-center gap-1 rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent shadow-sm">
                <CheckCircle class="h-3.5 w-3.5" />
                Verified fields
              </span>
            </div>

            <article
              v-for="(receipt, index) in receipts"
              :key="receipt.name + index"
              class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
            >
              <div class="grid grid-cols-1 lg:grid-cols-[180px_minmax(0,1fr)]">
                <aside class="border-b border-slate-200 bg-slate-100/70 p-4 lg:border-b-0 lg:border-r">
                  <p class="kpi-label text-slate-400">Receipt Preview</p>
                  <div class="mt-2 flex h-44 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <img
                      v-if="receipt.preview"
                      :src="receipt.preview"
                      alt="Uploaded receipt preview"
                      class="h-full w-full object-cover object-top"
                    />
                    <FileText v-else class="h-8 w-8 text-slate-300" />
                  </div>
                </aside>

                <div class="space-y-4 bg-white p-4">
                  <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                      <p class="truncate font-heading text-sm font-bold text-slate-900">
                        {{ receipt.ocrData?.vendor || receipt.name }}
                      </p>
                      <p class="mt-0.5 truncate text-xs font-semibold text-slate-400">
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
                      {{ receipt.ocrStatus === "done" ? "AI Scanned" : "Scanning" }}
                    </span>
                  </div>

                  <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="space-y-1">
                      <span class="input-label">Merchant Name</span>
                      <input class="input bg-white" v-model="receipt.ocrData.vendor" />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Date</span>
                      <span class="relative block">
                        <input type="date" class="input pr-10 bg-white" v-model="receipt.ocrData.date" />
                        <CalendarDays class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      </span>
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">TIN Number</span>
                      <input class="input bg-white" v-model="receipt.ocrData.tin" />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Invoice Number</span>
                      <input class="input bg-white" v-model="receipt.ocrData.invoiceNumber" />
                    </label>
                  </div>

                  <div class="grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 sm:grid-cols-3">
                    <label class="space-y-1">
                      <span class="input-label">Subtotal (Auto-Calc)</span>
                      <input class="input font-semibold bg-slate-50" readonly :value="formatPeso(Math.max(Number(receipt.ocrData?.amount || 0) - Number(receipt.ocrData?.vat || 0), 0))" />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Tax (VAT)</span>
                      <input type="number" step="0.01" class="input font-semibold bg-white" v-model.number="receipt.ocrData.vat" />
                    </label>
                    <div class="rounded-lg border border-accent/20 bg-accent-50 p-3">
                      <p class="input-label text-accent">Receipt Total</p>
                      <input type="number" step="0.01" class="input font-semibold !bg-white !text-primary font-heading text-lg" v-model.number="receipt.ocrData.amount" />
                    </div>
                  </div>
                </div>
              </div>
            </article>
          </section>

          <section class="rounded-xl border border-slate-200 bg-white p-4">
            <input
              ref="reportAttachmentInput"
              type="file"
              accept="image/*,.pdf,.doc,.docx"
              class="hidden"
              @change="selectReportAttachment"
            />
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <p class="font-heading text-base font-bold text-primary">Cash Advance Report Letter</p>
                <p class="mt-1 text-sm text-slate-500">
                  Attach your report letter for liquidation documentation.
                </p>
                <p v-if="reportAttachment" class="mt-2 truncate text-xs font-bold text-accent">
                  Attached: {{ reportAttachment.name }}
                </p>
              </div>
              <div class="flex shrink-0 gap-2">
                <button
                  v-if="reportAttachment"
                  class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
                  type="button"
                  @click="clearReportAttachment"
                >
                  Clear
                </button>
                <button
                  class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-xs font-bold text-white transition-colors hover:bg-accent/90"
                  type="button"
                  @click="reportAttachmentInput?.click()"
                >
                  <Upload class="h-4 w-4" />
                  Attachment
                </button>
              </div>
            </div>
          </section>

          <section
            v-if="needsReportAttachmentReminder"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4"
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
            class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 space-y-2"
          >
            <label class="block space-y-1">
              <span class="input-label text-amber-800 font-bold">Shortfall Explanation <span class="text-danger">*</span></span>
              <textarea
                v-model="shortfallExplanation"
                rows="3"
                class="input bg-white resize-none"
                placeholder="Explain why the total expense is less than the advanced amount (required)..."
              />
            </label>
          </section>

          <div class="mt-2 border border-slate-200 bg-clinical/20 p-5">
            <div class="mb-4 flex items-center gap-2">
              <Calculator class="h-4 w-4 text-primary opacity-50" />
              <h4 class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
                The Reconciliation (Settlement)
              </h4>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <div class="space-y-4">
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-bold uppercase tracking-tight text-slate-400">Total Balance:</span>
                  <span class="font-mono font-bold text-primary">{{ formatPeso(selectedAdvance.amount || 0) }}</span>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                  <span class="font-bold uppercase tracking-tight text-danger">Total Expenses:</span>
                  <span class="font-mono font-bold text-danger">-{{ formatPeso(totalExpenseAmount) }}</span>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                  <span class="text-[10px] font-black uppercase tracking-widest text-slate-600">Outstanding Balance:</span>
                  <span
                    :class="[
                      'font-mono text-lg font-black tracking-tighter',
                      liquidationStatus === 'Liquidated' ? 'text-emerald-600' : 'text-primary',
                    ]"
                  >
                    {{ formatPeso(liquidationOutstandingBalance) }}
                  </span>
                </div>
              </div>

              <div
                :class="[
                  'flex flex-col justify-center gap-2 border p-4 text-center',
                  liquidationStatus === 'Liquidated'
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-amber-200 bg-amber-50',
                ]"
              >
                <template v-if="liquidationStatus === 'Liquidated'">
                  <p class="text-[10px] font-black uppercase tracking-[0.1em] text-emerald-700">Status: Liquidated</p>
                  <p class="text-[9px] font-bold uppercase leading-relaxed text-emerald-600">
                    CASH ADVANCE BALANCE FULLY MET/CLEARED.
                  </p>
                </template>
                <template v-else>
                  <p class="text-[10px] font-black uppercase tracking-[0.1em] text-amber-700">Status: Incomplete</p>
                  <p class="text-[9px] font-bold uppercase leading-relaxed text-amber-600">
                    CONTINUE ATTACHING RECEIPTS UNTIL THE CASH ADVANCE IS FULLY ACCOUNTED FOR.
                  </p>
                </template>
              </div>
            </div>
          </div>

          <section
            v-if="overpaymentAmount > 0"
            class="rounded-xl border border-accent/20 bg-accent-50 p-4"
          >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="font-heading text-base font-bold text-primary">Overpayment Can Be Reimbursed</p>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                  Any excess amount spent beyond the cash advance can be filed as a reimbursement.
                  Current excess amount:
                  <span class="font-bold text-primary">{{ formatPeso(overpaymentAmount) }}</span>.
                </p>
              </div>
              <button
                class="inline-flex min-h-10 w-fit shrink-0 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-xs font-bold text-white transition-colors hover:bg-accent/90"
                type="button"
                @click="forwardOverpaymentToReimbursement"
              >
                <Upload class="h-4 w-4" />
                Reimbursement Filing
              </button>
            </div>
          </section>

          <div class="mt-4 flex justify-end">
            <BaseButton
              id="submit-liquidation-btn"
              variant="primary"
              class="w-fit px-4 py-2.5"
              :disabled="receipts.length === 0 || receipts.some(r => r.ocrStatus === 'processing') || totalExpenseAmount === 0 || submitting || (variance > 0 && !shortfallExplanation.trim())"
              @click="submitLiquidation"
            >
              <div v-if="submitting" class="flex items-center gap-2">
                <Activity class="h-4 w-4 animate-spin" />
                <span>ENCODING SETTLEMENT...</span>
              </div>
              <div v-else class="flex items-center gap-2">
                <Upload class="h-4 w-4" />
                <span>SUBMIT FOR AUDIT</span>
              </div>
            </BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Audit Confirmation Modal -->
    <DecisionConfirmationModal
      :is-open="!!approvingId || !!rejectingId"
      :mode="approvingId ? 'approve' : 'reject'"
      :is-submitting="isReviewSubmitting"
      :min-comment-length="10"
      title="Liquidation Settlement Audit"
      :description="approvingId ? 'Are you sure you want to approve this liquidation settlement? This will mark the cash advance as settled. Please enter your password to confirm.' : 'Please enter your password and a comment to authorize rejecting this liquidation settlement.'"
      v-model:password="confirmPassword"
      v-model:comment="rejectionComment"
      @close="approvingId ? cancelApprove() : cancelReject()"
      @confirm="approvingId ? confirmApprove() : confirmReject()"
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
