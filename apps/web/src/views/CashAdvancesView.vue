<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import ToastNotification from "@/components/ToastNotification.vue";
import BaseTable from "@/components/base/BaseTable.vue";
import StatusBadge from "@/components/base/StatusBadge.vue";
import BaseButton from "@/components/base/BaseButton.vue";
import BaseModal from "@/components/base/BaseModal.vue";
import BaseFilterTabs from "@/components/base/BaseFilterTabs.vue";
import {
  Plus,
  X,
  Wallet,
  Activity,
  ShieldAlert,
  FileMinus,
  FileText,
  Eye,
  ArrowLeft,
  Calendar,
  UploadCloud,
  Info,
  MessageSquare,
  Send,
  Download,
  FileDown,
  RotateCcw,
  ShieldCheck,
  Trash2,
  RefreshCw,
} from "lucide-vue-next";

const store = useCashAdvanceStore();
const auth = useAuthStore();
const { addToast } = useToast();

onMounted(() => store.fetchAll());

const showModal = ref(false);
const submitting = ref(false);
const form = reactive({
  purpose: "",
  amount: "",
  expected_disbursement_date: "",
  expected_liquidation_date: "",
  documents: [],
});
const fileInput = ref(null);

function handleFileUpload(event) {
  const files = Array.from(event.target.files);

  if (form.documents.length + files.length > 5) {
    addToast({ message: "Maximum of 5 files allowed.", type: "error" });
    if (fileInput.value) fileInput.value.value = "";
    return;
  }

  for (const file of files) {
    if (file.size > 2 * 1024 * 1024) {
      addToast({
        message: `File ${file.name} exceeds the 2MB size limit.`,
        type: "error",
      });
      if (fileInput.value) fileInput.value.value = "";
      return;
    }

    const validTypes = [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "image/jpeg",
      "image/png",
      "image/jpg",
    ];

    if (!validTypes.includes(file.type)) {
      addToast({
        message: `Invalid file type: ${file.name}. Must be PDF, DOC, DOCX, XLSX, or Image.`,
        type: "error",
      });
      if (fileInput.value) fileInput.value.value = "";
      return;
    }
  }

  for (const file of files) {
    if (file.type.startsWith("image/")) {
      file.previewUrl = URL.createObjectURL(file);
    }
    form.documents.push(file);
  }

  if (fileInput.value) fileInput.value.value = "";
}

function removeFile(index) {
  const file = form.documents[index];
  if (file.previewUrl) {
    URL.revokeObjectURL(file.previewUrl);
  }
  form.documents.splice(index, 1);
}

const statusTabs = computed(() => {
  const baseTabs = ["All", "Pending", "Approved", "Rejected", "Disbursed"];
  return auth.isAdmin ? [...baseTabs, "Me"] : baseTabs;
});
const activeStatus = ref("All");

function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
}

const formattedItems = computed(() => {
  return store.items.map((item) => ({
    ...item,
    fileDescription: item.document?.file_name || "Document Attached",
    documentUrl: item.documentUrl,
    requested: item.date ? new Date(item.date).toLocaleDateString() : "--",
    dueDate: item.dueDate ? new Date(item.dueDate).toLocaleDateString() : "--",
    user: item.requestedBy || "Unknown User",
    initials: getInitials(item.requestedBy || "Unknown User"),
    outstanding: item.balance || 0,
  }));
});

const filteredRows = computed(() => {
  let items = formattedItems.value;
  if (activeStatus.value !== "All") {
    if (activeStatus.value === "Me") {
      items = items.filter((row) => row.userId === auth.user?.id);
    } else {
      items = items.filter(
        (row) => row.status === activeStatus.value.toLowerCase(),
      );
    }
  }
  return items;
});

const adminMetrics = computed(() => {
  const items = formattedItems.value;
  const outstandingRows = items.filter((row) => Number(row.outstanding) > 0);

  const uniqueEmployees = new Set(outstandingRows.map((row) => row.user));

  return {
    pending: items.filter((row) => row.status === "pending").length,
    approved: items.filter((row) => row.status === "approved").length,
    rejected: items.filter((row) => row.status === "rejected").length,
    outstanding: outstandingRows.reduce(
      (sum, row) => sum + (Number(row.outstanding) || 0),
      0,
    ),
    outstandingEmployees: uniqueEmployees.size,
  };
});

const userMetrics = computed(() => {
  const items = formattedItems.value;
  const outstandingRows = items.filter((row) => Number(row.outstanding) > 0);

  return {
    totalAmount: items.reduce((sum, row) => sum + (Number(row.amount) || 0), 0),
    pending: items.filter((row) => row.status === "pending").length,
    approved: items.filter((row) => row.status === "approved").length,
    rejected: items.filter((row) => row.status === "rejected").length,
    outstanding: outstandingRows.reduce(
      (sum, row) => sum + (Number(row.outstanding) || 0),
      0,
    ),
  };
});

function formatPeso(value) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(value);
}

function statusClass(status) {
  const classes = {
    unliquidated: "bg-amber-50 text-amber-700 border border-amber-200",
    liquidated: "bg-blue-50 text-blue-700 border border-blue-200",
    pending: "bg-yellow-100 text-yellow-800 border border-yellow-200",
    granted: "bg-emerald-50 text-emerald-700 border border-emerald-200",
    approved: "bg-success text-white border border-success",
    disbursed: "bg-[#F0FDFA] text-[#0D9488] border border-teal-100",
    rejected: "bg-[#FEF2F2] text-[#B91C1C] border border-red-200",
  };
  return classes[status] || "bg-slate-100 text-slate-600";
}

function openDetails(row) {
  viewingRecord.value = {
    id: row.id,
    purpose: row.purpose.replace(/\s\.\.\.$|\.\.\.$/, ""),
    amount: row.amount,
    balance:
      row.outstanding ??
      (["approved", "disbursed", "granted", "unliquidated"].includes(row.status)
        ? row.amount
        : 0),
    status: row.status,
    date: row.requested,
    updatedAt: row.status === "pending" ? row.requested : "11/01/2025",
    requestedBy: row.user || auth.user?.name || "Employee",
    userId: row.userId,
    dueDate:
      row.dueDate ||
      (row.status === "pending" || row.status === "rejected"
        ? "--"
        : "02/15/2025"),
    documentFileName:
      row.fileDescription || `Cash_Advance_Request_${row.id}.pdf`,
    adminNotes: row.adminNotes || "N/A",
    acknowledgedAt: row.acknowledgedAt,
    signatureImage: row.signatureImage,
    documentUrl: row.documentUrl,
  };
  clearSignature();
}

async function handleRequest() {
  if (
    !form.purpose ||
    !form.amount ||
    !form.expected_disbursement_date ||
    !form.expected_liquidation_date ||
    form.documents.length === 0
  )
    return;

  const today = new Date().toISOString().split("T")[0];
  if (form.expected_liquidation_date < today) {
    addToast({
      message: "Liquidation Deadline must be today or a future date.",
      type: "error",
    });
    return;
  }

  if (form.expected_disbursement_date >= form.expected_liquidation_date) {
    addToast({
      message: "Disbursement date cannot be greater than or equal to the liquidation date.",
      type: "error",
    });
    return;
  }

  submitting.value = true;

  const formData = new FormData();
  formData.append("purpose", form.purpose);
  formData.append("amount", form.amount);
  formData.append(
    "expected_disbursement_date",
    form.expected_disbursement_date,
  );
  formData.append("expected_liquidation_date", form.expected_liquidation_date);
  form.documents.forEach((file) => formData.append("documents[]", file));

  try {
    await store.request(formData);
    showModal.value = false;
    Object.assign(form, {
      purpose: "",
      amount: "",
      expected_disbursement_date: "",
      expected_liquidation_date: "",
      documents: [],
    });
    if (fileInput.value) fileInput.value.value = "";
    addToast({
      message: "Cash advance requested successfully",
      type: "success",
    });
  } catch (error) {
    addToast({ message: error.message || "Failed to create request", type: "error" });
  } finally {
    submitting.value = false;
  }
}

const rejectingId = ref(null);
const rejectionType = ref("");
const rejectionComment = ref("");

const viewingRecord = ref(null);
const signatureCanvas = ref(null);
const isSigning = ref(false);
const signatureStarted = ref(false);
const adminReviewNotes = ref("");
const confirmationAction = ref("");
const showAcknowledgeModal = ref(false);

async function confirmAcknowledge() {
  if (!viewingRecord.value) return;
  const canvas = signatureCanvas.value;
  if (!canvas) return;
  const signatureData = canvas.toDataURL("image/png");

  try {
    await store.acknowledgeRequest(viewingRecord.value.id, signatureData);
    addToast({ message: "Cash advance acknowledged successfully.", type: "success" });
    showAcknowledgeModal.value = false;
    closeDetails();
  } catch (error) {
    addToast({ message: error.message || "Action failed", type: "error" });
  }
}

function closeDetails() {
  viewingRecord.value = null;
  adminReviewNotes.value = "";
  confirmationAction.value = "";
  clearSignature();
}

function downloadDocument() {
  if (!viewingRecord.value) return;
  const fileName = viewingRecord.value.documentFileName || "document.pdf";
  const fileUrl = viewingRecord.value.documentUrl || "/mock_receipt.png";

  const a = document.createElement("a");
  a.href = fileUrl;
  a.download = fileName;
  a.target = "_blank";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function requestConfirmation(action) {
  if (adminReviewNotes.value.trim().length < 10) {
    addToast({
      message: "Please enter at least 10 characters in the admin notes.",
      type: "error",
    });
    return;
  }
  confirmationAction.value = action;
}

function cancelConfirmation() {
  confirmationAction.value = "";
}

async function confirmAdminDecision() {
  if (!viewingRecord.value) return;

  const id = viewingRecord.value.id;

  try {
    if (confirmationAction.value === "approve") {
      await store.approveRequest(id, adminReviewNotes.value);
    } else {
      const reason = adminReviewNotes.value || "Rejected by admin";
      await store.rejectRequest(id, reason);
    }

    addToast({ message: `Request successfully ${confirmationAction.value}d`, type: "success" });
    confirmationAction.value = "";
    closeDetails();
  } catch (error) {
    addToast({ message: error.message || "Action failed", type: "error" });
  }
}

function statusPillClass(status) {
  const classes = {
    unliquidated: "bg-amber-50 text-amber-700 border-amber-200",
    liquidated: "bg-blue-50 text-blue-700 border-blue-200",
    granted: "bg-emerald-50 text-emerald-700 border-emerald-200",
    approved: "bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]",
    disbursed: "bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]",
    pending: "bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]",
    rejected: "bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]",
  };
  return classes[status] || "bg-slate-100 text-slate-600 border-slate-200";
}

function outstandingBalance(record) {
  if (typeof record.balance === "number") return record.balance;
  return ["approved", "disbursed"].includes(record.status) ? record.amount : 0;
}

function formatDetailDate(value, fallbackTime = "09:00:00") {
  if (!value) return "--";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return `${value}, ${fallbackTime}`;

  return new Intl.DateTimeFormat("en-PH", {
    month: "2-digit",
    day: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  }).format(date);
}

function formatDateOnly(value) {
  if (!value) return "--";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(date);
}

function prepareSignatureCanvas() {
  const canvas = signatureCanvas.value;
  if (!canvas) return null;

  const rect = canvas.getBoundingClientRect();
  const scale = window.devicePixelRatio || 1;

  if (
    canvas.width !== Math.floor(rect.width * scale) ||
    canvas.height !== Math.floor(rect.height * scale)
  ) {
    canvas.width = Math.floor(rect.width * scale);
    canvas.height = Math.floor(rect.height * scale);
    const resizedContext = canvas.getContext("2d");
    resizedContext.scale(scale, scale);
    resizedContext.lineWidth = 2;
    resizedContext.lineCap = "round";
    resizedContext.lineJoin = "round";
    resizedContext.strokeStyle = "#14532D";
  }

  return canvas.getContext("2d");
}

function signaturePoint(event) {
  const rect = signatureCanvas.value.getBoundingClientRect();
  return {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top,
  };
}

function startSignature(event) {
  const context = prepareSignatureCanvas();
  if (!context) return;

  isSigning.value = true;
  signatureStarted.value = true;
  signatureCanvas.value.setPointerCapture?.(event.pointerId);

  const point = signaturePoint(event);
  context.beginPath();
  context.moveTo(point.x, point.y);
}

function drawSignature(event) {
  if (!isSigning.value) return;
  const context = prepareSignatureCanvas();
  if (!context) return;

  const point = signaturePoint(event);
  context.lineTo(point.x, point.y);
  context.stroke();
}

function stopSignature() {
  isSigning.value = false;
}

function clearSignature() {
  const canvas = signatureCanvas.value;
  if (!canvas) {
    signatureStarted.value = false;
    return;
  }

  const context = canvas.getContext("2d");
  context.clearRect(0, 0, canvas.width, canvas.height);
  signatureStarted.value = false;
}

async function quickApproveAdvance(id) {
  await store.approveRequest(id);
}
async function quickApproveSettlement(id) {
  await store.approveSettlement(id);
}

function openRejectModal(id, type) {
  rejectingId.value = id;
  rejectionType.value = type;
  rejectionComment.value = "";
}

function cancelReject() {
  rejectingId.value = null;
  rejectionType.value = "";
  rejectionComment.value = "";
}

async function confirmReject() {
  if (rejectionComment.value.length < 10) return;
  if (rejectionType.value === "advance") {
    await store.rejectRequest(rejectingId.value, rejectionComment.value);
  } else if (rejectionType.value === "settlement") {
    await store.rejectSettlement(rejectingId.value, rejectionComment.value);
  }
  cancelReject();
}
</script>

<template>
  <ToastNotification />
  <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 font-sans">
    <!-- Page Header -->
    <section
      class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Wallet class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">Advance Requests</span>
        </div>
        <h1
          class="font-heading text-2xl font-bold leading-tight text-slate-800"
        >
          Cash Advance
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          {{
            auth.isAdmin
              ? "Review and manage employee cash advance activity"
              : "Request and track cash advance payments"
          }}
        </p>
      </div>

      <button
        id="request-advance-btn"
        class="inline-flex min-h-[44px] w-fit items-center justify-center gap-2 rounded-lg bg-accent px-6 py-3 font-heading text-sm font-bold text-white shadow-sm transition-all duration-200 ease-out hover:bg-accent-600 hover:shadow-card-hover hover:scale-[1.01] active:scale-[0.98]"
        @click="showModal = true"
      >
        <Plus class="h-4 w-4" />
        New Request
      </button>
    </section>

    <!-- Admin Analytics Metrics -->
    <section
      v-if="auth.isAdmin"
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
    >
      <div class="kpi-card border-l-2 border-l-warning">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Pending Advances</span>
          <Activity class="h-5 w-5 text-amber-500/35" />
        </div>
        <p class="kpi-value">
          {{ adminMetrics.pending }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-success">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Approved Advances</span>
          <ShieldCheck class="h-5 w-5 text-success/35" />
        </div>
        <p class="kpi-value">
          {{ adminMetrics.approved }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-danger">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Rejected Advances</span>
          <X class="h-5 w-5 text-danger/35" />
        </div>
        <p class="kpi-value">
          {{ adminMetrics.rejected }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-primary">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Total Outstanding Balance</span>
          <Wallet class="h-5 w-5 text-blue-900/35" />
        </div>
        <p class="kpi-value">
          {{ formatPeso(adminMetrics.outstanding) }}
        </p>
        <p class="kpi-label mt-1 normal-case tracking-normal">
          (Total employees with outstanding balance:
          {{ adminMetrics.outstandingEmployees }})
        </p>
      </div>
    </section>

    <!-- Filter Status Tabs (Admin) -->
    <section v-if="auth.isAdmin" class="overflow-x-auto mb-2">
      <BaseFilterTabs v-model="activeStatus" :tabs="statusTabs" />
    </section>

    <!-- Admin Management Data Table -->
    <section
      v-if="auth.isAdmin"
      class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    >
      <div
        class="flex flex-col gap-1 border-b border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <h2
            class="font-heading text-base font-bold leading-tight text-slate-800"
          >
            Cash Advance Management
          </h2>
          <p class="mt-0.5 text-xs text-slate-400">
            Administrative review queue
          </p>
        </div>
        <span class="kpi-label text-slate-400"
          >Showing {{ filteredRows.length }} records</span
        >
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[1180px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                ID
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                File Description
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Purpose
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Date Requested
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Due Date
              </th>
              <th
                class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Amount
              </th>
              <th
                class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Outstanding
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                User
              </th>
              <th
                class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Status
              </th>
              <th
                class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in filteredRows"
              :key="row.id"
              class="whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
            >
              <td class="px-5 py-5 font-mono text-sm font-bold text-slate-900">
                {{ row.id }}
              </td>
              <td class="max-w-[170px] px-5 py-5 text-sm text-slate-500">
                <span class="block truncate">{{ row.fileDescription }}</span>
              </td>
              <td class="max-w-[220px] px-5 py-5 text-sm text-slate-600">
                <span class="block truncate">{{ row.purpose }}</span>
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.requested }}
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.dueDate }}
              </td>
              <td class="px-5 py-5 text-right text-sm font-bold text-primary">
                {{ formatPeso(row.amount) }}
              </td>
              <td
                class="px-5 py-5 text-right text-sm font-semibold text-slate-600"
              >
                {{ formatPeso(row.outstanding) }}
              </td>
              <td class="px-5 py-5">
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary"
                  >
                    {{ row.initials }}
                  </span>
                  <span class="text-sm font-medium text-slate-700">{{
                    row.user
                  }}</span>
                </div>
              </td>
              <td class="px-5 py-5 text-center">
                <span
                  :class="[
                    'inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                    statusClass(row.status),
                  ]"
                >
                  {{ row.status }}
                </span>
              </td>
              <td class="px-5 py-5 text-center">
                <button
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-accent/15 bg-accent/5 text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.04] focus:outline-none"
                  title="View cash advance"
                  @click="openDetails(row)"
                >
                  <span
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-accent/30"
                  >
                    <Eye class="h-3.5 w-3.5" />
                  </span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- User Analytics Metrics -->
    <section
      v-if="!auth.isAdmin"
      class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
    >
      <div class="kpi-card border-l-2 border-l-primary">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Total Amount</span>
          <Wallet class="h-5 w-5 text-blue-900/35" />
        </div>
        <p class="kpi-value">
          {{ formatPeso(userMetrics.totalAmount) }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-warning">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Pending</span>
          <Activity class="h-5 w-5 text-amber-500/35" />
        </div>
        <p class="kpi-value">
          {{ userMetrics.pending }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-success">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Approved</span>
          <ShieldCheck class="h-5 w-5 text-success/35" />
        </div>
        <p class="kpi-value">
          {{ userMetrics.approved }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-danger">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Rejected</span>
          <X class="h-5 w-5 text-danger/35" />
        </div>
        <p class="kpi-value">
          {{ userMetrics.rejected }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-primary">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Outstanding Balance</span>
          <Wallet class="h-5 w-5 text-blue-900/35" />
        </div>
        <p class="kpi-value">
          {{ formatPeso(userMetrics.outstanding) }}
        </p>
      </div>
    </section>

    <!-- Filter Status Tabs -->
    <section v-if="!auth.isAdmin" class="overflow-x-auto">
      <BaseFilterTabs v-model="activeStatus" :tabs="statusTabs" />
    </section>

    <!-- Cash Advance Data Table Module -->
    <section
      v-if="!auth.isAdmin"
      class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    >
      <div class="overflow-x-auto">
        <table class="w-full min-w-[880px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                ID
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Purpose
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Amount
              </th>
              <th
                class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Status
              </th>
              <th
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Requested
              </th>
              <th
                class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in filteredRows"
              :key="row.id"
              class="group transition-colors duration-200 ease-out hover:bg-slate-50/80"
            >
              <td class="px-5 py-5 font-mono text-sm font-bold text-slate-900">
                {{ row.id }}
              </td>
              <td class="max-w-sm px-5 py-5 text-sm text-slate-600">
                <span class="block truncate">{{ row.purpose }}</span>
              </td>
              <td class="px-5 py-5 text-sm font-bold text-primary">
                {{ formatPeso(row.amount) }}
              </td>
              <td class="px-5 py-5 text-center">
                <span
                  :class="[
                    'inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase',
                    statusClass(row.status),
                  ]"
                >
                  {{ row.status }}
                </span>
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.requested }}
              </td>
              <td class="px-5 py-5 text-right">
                <button
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-accent/15 bg-accent/5 text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.04] focus:outline-none"
                  title="View cash advance"
                  @click="openDetails(row)"
                >
                  <span
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-accent/30"
                  >
                    <Eye class="h-3.5 w-3.5" />
                  </span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- New Request Workspace -->
    <Transition name="slide-up">
      <div
        v-if="showModal"
        class="fixed inset-0 z-[60] flex flex-col overflow-hidden bg-clinical"
      >
        <header
          class="sticky top-0 z-10 flex flex-shrink-0 items-center gap-3 px-6 py-3 text-white shadow-sm"
          style="background: linear-gradient(135deg, #252578 0%, #2f2f7e 100%)"
        >
          <button
            class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
            type="button"
            title="Close request form"
            @click="showModal = false"
          >
            <X class="h-4 w-4" />
          </button>
          <div>
            <p
              class="text-[10px] font-semibold uppercase tracking-widest text-white/60"
            >
              Cash Advances
            </p>
            <h2 class="font-heading text-sm font-bold leading-tight text-white">
              New Cash Advance Request
            </h2>
          </div>
        </header>

        <main class="flex-1 overflow-y-auto">
          <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6">
            <section class="card p-5 md:p-6">
              <form
                id="cashAdvanceForm"
                class="space-y-6"
                @submit.prevent="handleRequest"
              >
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="input-wrapper">
                    <label class="input-label" for="ca-amount"
                      >Amount Requested *</label
                    >
                    <div class="relative">
                      <span
                        class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-mono text-slate-400"
                        >PHP</span
                      >
                      <input
                        id="ca-amount"
                        v-model="form.amount"
                        class="input !pl-14 text-base"
                        min="0"
                        placeholder="0.00"
                        type="number"
                      />
                    </div>
                  </div>

                  <div class="input-wrapper">
                    <label class="input-label" for="ca-disbursement"
                      >Disbursement Date *</label
                    >
                    <div class="relative">
                      <input
                        id="ca-disbursement"
                        v-model="form.expected_disbursement_date"
                        class="input !pr-12 text-base"
                        type="date"
                      />
                      <Calendar
                        class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                      />
                    </div>
                  </div>

                  <div class="input-wrapper">
                    <label class="input-label" for="ca-due"
                      >Liquidation Deadline *</label
                    >
                    <div class="relative">
                      <input
                        id="ca-due"
                        v-model="form.expected_liquidation_date"
                        class="input !pr-12 text-base"
                        type="date"
                      />
                      <Calendar
                        class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                      />
                    </div>
                  </div>
                </div>

                <div class="input-wrapper">
                  <label class="input-label" for="ca-purpose">Purpose *</label>
                  <textarea
                    id="ca-purpose"
                    v-model="form.purpose"
                    class="input min-h-[132px] resize-none text-base leading-relaxed"
                    placeholder="Describe the purpose of this cash advance request..."
                    rows="4"
                  />
                </div>

                <div class="input-wrapper">
                  <label class="input-label">Request Documents (Max 5) *</label>
                  <input
                    type="file"
                    ref="fileInput"
                    @change="handleFileUpload"
                    class="hidden"
                    accept=".pdf,.doc,.docx,.xlsx,image/*"
                    multiple
                  />

                  <div
                    v-if="form.documents.length > 0"
                    class="flex flex-col gap-3 mb-4"
                  >
                    <div
                      v-for="(file, index) in form.documents"
                      :key="index"
                      class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-3"
                    >
                      <div class="flex items-center gap-3">
                        <img
                          v-if="file.previewUrl"
                          :src="file.previewUrl"
                          class="h-10 w-10 rounded object-cover"
                        />
                        <div
                          v-else
                          class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400"
                        >
                          <FileText class="h-5 w-5" />
                        </div>
                        <div class="flex flex-col">
                          <span class="text-sm font-bold text-slate-800">{{
                            file.name
                          }}</span>
                          <span class="text-xs font-semibold text-slate-400"
                            >{{ (file.size / 1024 / 1024).toFixed(2) }} MB</span
                          >
                        </div>
                      </div>
                      <button
                        type="button"
                        @click="removeFile(index)"
                        class="text-danger hover:bg-red-50 p-2 rounded-full transition-colors"
                      >
                        <X class="h-4 w-4" />
                      </button>
                    </div>
                  </div>

                  <button
                    v-if="form.documents.length < 5"
                    class="group flex w-full flex-col items-center justify-between gap-4 rounded-lg border-2 border-dashed border-slate-200 bg-slate-50/80 p-6 text-left transition-all duration-200 ease-out hover:border-accent/30 hover:bg-accent-50/50 md:flex-row"
                    type="button"
                    @click="fileInput.click()"
                  >
                    <div class="flex flex-col items-center gap-4 md:flex-row">
                      <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm transition-colors group-hover:text-accent"
                      >
                        <UploadCloud class="h-6 w-6" />
                      </div>
                      <div class="text-center md:text-left">
                        <h3
                          class="font-heading text-base font-bold text-slate-800"
                        >
                          Select files
                        </h3>
                        <p class="text-sm text-slate-500">
                          Upload up to 5 request documents (PDF, DOC, DOCX,
                          XLSX, Images)
                        </p>
                      </div>
                    </div>
                    <span
                      class="rounded-md border border-black/5 bg-white px-5 py-2 text-sm font-bold text-primary shadow-sm transition-colors group-hover:border-accent/20 group-hover:text-accent"
                    >
                      Browse
                    </span>
                  </button>
                  <p
                    class="mt-1 flex items-center gap-1 text-xs font-semibold"
                    :class="
                      form.documents.length > 0 ? 'text-success' : 'text-danger'
                    "
                  >
                    <Info class="h-3.5 w-3.5" />
                    At least 1 request document is required to process your cash
                    advance.
                  </p>
                </div>
              </form>
            </section>

            <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <div class="rounded-lg border border-accent/20 bg-accent-50 p-5">
                <div class="mb-4 flex items-start gap-3">
                  <Info class="mt-0.5 h-5 w-5 text-accent" />
                  <h3 class="font-heading text-base font-bold text-accent-800">
                    Important Information
                  </h3>
                </div>
                <ul
                  class="list-inside list-disc space-y-2 text-sm leading-relaxed text-accent-800"
                >
                  <li>
                    Cash advance requests are subject to approval by the
                    accounting department
                  </li>
                  <li>
                    Approved amounts will be disbursed within 3-5 business days
                  </li>
                  <li>
                    You must submit reimbursement with receipts after using the
                    cash advance
                  </li>
                  <li>Unused amounts must be returned to the company</li>
                </ul>
              </div>
            </section>

            <footer
              class="flex flex-col items-center justify-end gap-3 border-t border-black/5 pt-6 sm:flex-row"
            >
              <button
                class="btn btn-secondary w-full px-8 py-3 sm:w-auto"
                type="button"
                @click="showModal = false"
              >
                Cancel
              </button>
              <button
                id="submit-advance-btn"
                class="btn btn-primary w-full px-8 py-3 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="
                  submitting ||
                  !form.purpose ||
                  !form.amount ||
                  !form.expected_disbursement_date ||
                  !form.expected_liquidation_date ||
                  form.documents.length === 0
                "
                form="cashAdvanceForm"
                type="submit"
              >
                <Send class="h-4 w-4" />
                {{ submitting ? "Submitting..." : "Submit Request" }}
              </button>
            </footer>
          </div>
        </main>
      </div>
    </Transition>

    <!-- Rejection Modal -->
    <BaseModal
      :isOpen="!!rejectingId"
      @close="cancelReject"
      contentClass="!p-0"
    >
      <div
        class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3"
      >
        <div
          class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-danger"
        >
          <Activity class="w-4 h-4" />
        </div>
        <h3 class="font-heading text-sm font-bold text-slate-800">
          Reject
          {{ rejectionType === "advance" ? "Advance Request" : "Liquidation" }}
        </h3>
      </div>
      <div class="p-5 flex flex-col gap-4">
        <p class="text-sm font-medium text-slate-600">
          Please provide a valid justification for rejecting Ref #{{
            rejectingId
          }}.
        </p>
        <div class="input-wrapper">
          <textarea
            v-model="rejectionComment"
            rows="3"
            class="input !font-sans resize-none"
            :class="
              rejectionComment.length > 0 && rejectionComment.length < 10
                ? 'border-danger focus:border-danger focus:ring-danger'
                : ''
            "
            placeholder="REJECTION REASON (MIN 10 CHARACTERS)"
          />
          <div
            class="text-[10px] font-bold uppercase tracking-widest flex justify-between mt-1"
            :class="
              rejectionComment.length < 10 ? 'text-danger' : 'text-success'
            "
          >
            <span>Requirement: >= 10 Chars</span>
            <span>{{ rejectionComment.length }} / 10+</span>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2 mt-2">
          <BaseButton variant="secondary" @click="cancelReject"
            >CANCEL</BaseButton
          >
          <BaseButton
            variant="primary"
            :disabled="rejectionComment.length < 10"
            class="!bg-danger !border-danger"
            @click="confirmReject"
          >
            CONFIRM REJECTION
          </BaseButton>
        </div>
      </div>
    </BaseModal>

    <!-- Cash Advance Details Modal -->
    <div
      v-if="viewingRecord"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 p-4 backdrop-blur-sm"
    >
      <div
        v-if="auth.isAdmin"
        class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      >
        <header
          class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-6 py-4"
        >
          <h2 class="font-heading text-xl font-bold text-primary">
            Review Cash Advance Request
          </h2>
          <button
            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
            type="button"
            title="Close review"
            @click="closeDetails"
          >
            <X class="h-5 w-5 stroke-[1.75]" />
          </button>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto bg-slate-50/40 px-6 py-5">
          <section
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex items-center gap-4">
              <div
                class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-emerald-100 bg-primary/10 font-heading text-sm font-bold text-primary"
              >
                {{
                  viewingRecord.requestedBy
                    ?.split(" ")
                    .map((part) => part[0])
                    .join("")
                    .slice(0, 2) || "EA"
                }}
              </div>
              <div>
                <p class="section-label mb-1">Requestor Name</p>
                <h3 class="font-heading text-xl font-bold text-primary">
                  {{ viewingRecord.requestedBy }}
                </h3>
              </div>
            </div>

            <div class="sm:text-right">
              <p class="section-label mb-2">Status</p>
              <span
                :class="[
                  'inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-bold uppercase tracking-wide',
                  statusPillClass(viewingRecord.status),
                ]"
              >
                {{
                  viewingRecord.status === "pending"
                    ? "Pending Review"
                    : viewingRecord.status
                }}
              </span>
            </div>
          </section>

          <section
            class="rounded-lg border border-emerald-100 bg-primary/5 p-6 text-center"
          >
            <p class="section-label mb-2">Amount Requested</p>
            <p
              class="font-heading text-[40px] font-extrabold leading-tight text-primary"
            >
              {{ formatPeso(viewingRecord.amount) }}
            </p>
          </section>

          <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div
              class="rounded-lg border border-slate-200 bg-white p-4 md:col-span-2"
            >
              <p class="section-label mb-1">Purpose</p>
              <p class="text-base leading-relaxed text-slate-800">
                {{ viewingRecord.purpose }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
              <p class="section-label mb-1">Date Requested</p>
              <p class="text-base font-bold text-slate-800">
                {{ viewingRecord.date }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
              <p class="section-label mb-1">Settlement Due Date</p>
              <p class="text-base font-bold text-slate-800">
                {{ viewingRecord.dueDate }}
              </p>
            </div>
          </section>

          <section
            class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-100 px-4 py-3"
          >
            <div class="flex items-center gap-3">
              <Wallet class="h-5 w-5 text-accent" />
              <span class="text-sm font-semibold text-slate-700"
                >Current Outstanding Balance</span
              >
            </div>
            <span class="font-heading text-xl font-bold text-primary">{{
              formatPeso(outstandingBalance(viewingRecord))
            }}</span>
          </section>

          <section
            class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4"
          >
            <div class="flex min-w-0 items-center gap-4">
              <span
                class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-red-50 text-danger"
              >
                <FileDown class="h-6 w-6" />
              </span>
              <div class="min-w-0">
                <p class="truncate font-heading text-sm font-bold text-primary">
                  {{ viewingRecord.documentFileName }}
                </p>
                <p class="text-xs font-semibold text-slate-400">
                  Uploaded Attachment
                </p>
              </div>
            </div>
            <button
              class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-primary transition-colors hover:bg-slate-200"
              type="button"
              title="Download request document"
              @click="downloadDocument"
            >
              <Download class="h-5 w-5" />
            </button>
          </section>

          <section
            class="rounded-r-lg border-l-4 border-accent bg-accent/10 p-4"
          >
            <div class="mb-2 flex items-center gap-2">
              <Info class="h-4 w-4 text-accent" />
              <h3 class="font-heading text-sm font-bold text-accent">
                Important Information Guidelines
              </h3>
            </div>
            <ul class="list-inside list-disc space-y-1 text-sm text-slate-700">
              <li>
                Advances over PHP 10,000 require Department Head digital
                countersign.
              </li>
              <li>
                Liquidation must be submitted within 5 business days
                post-settlement.
              </li>
              <li>
                Unliquidated advances will be deducted from next payroll cycle.
              </li>
            </ul>
          </section>

          <section class="space-y-2">
            <p class="section-label">Employee Signature Verification Pad</p>
            <div
              class="relative flex h-32 w-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-200 bg-white"
            >
              <template v-if="viewingRecord.signature">
                <span
                  class="font-heading text-3xl font-bold italic text-primary/75"
                  >{{ viewingRecord.signature }}</span
                >
                <div
                  class="absolute bottom-2 right-3 flex items-center gap-1 text-success"
                >
                  <ShieldCheck class="h-4 w-4" />
                  <span class="text-[10px] font-bold uppercase tracking-widest"
                    >Digitally Verified</span
                  >
                </div>
              </template>
            </div>
          </section>

          <section class="space-y-2 pb-2">
            <label class="section-label" for="adminReviewNotes"
              >Add Admin Notes / Instructions</label
            >
            <div class="input-wrapper">
              <textarea
                id="adminReviewNotes"
                v-model="adminReviewNotes"
                class="input min-h-[96px] resize-none !font-sans"
                :class="
                  adminReviewNotes.length > 0 &&
                  adminReviewNotes.trim().length < 10
                    ? 'border-danger focus:border-danger focus:ring-danger'
                    : ''
                "
                placeholder="Enter comments or reason for decision..."
              />
              <div
                class="text-[10px] font-bold uppercase tracking-widest flex gap-2 mt-1"
                :class="
                  adminReviewNotes.trim().length < 10
                    ? 'text-danger'
                    : 'text-success'
                "
              >
                <span>Requirement:</span>
                <span>{{ adminReviewNotes.length }} / 10+</span>
              </div>
            </div>
          </section>
        </div>

        <footer
          class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 bg-white p-5 sm:flex-row"
        >
          <div class="text-sm font-semibold text-danger text-center sm:text-left" v-if="viewingRecord.userId === auth.user?.id">
            You cannot approve or reject your own request.
          </div>
          <div v-else></div>
          <div class="flex flex-col w-full sm:w-auto sm:flex-row gap-3">
            <button
              class="btn btn-secondary w-full !border-danger/30 !text-danger hover:!bg-danger/5 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
              type="button"
              :disabled="viewingRecord.userId === auth.user?.id"
              @click="requestConfirmation('reject')"
            >
              Reject
            </button>
            <button
              class="btn btn-primary w-full sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
              type="button"
              :disabled="viewingRecord.userId === auth.user?.id"
              @click="requestConfirmation('approve')"
            >
              Approve
            </button>
          </div>
        </footer>
      </div>

      <div
        v-else
        class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
      >
        <header
          class="flex items-center justify-between border-b border-slate-200 px-6 py-4"
        >
          <h2 class="font-heading text-xl font-bold text-[#003527]">
            {{ auth.isAdmin ? "Admin Details Review" : "Cash Advance Details" }}
          </h2>
          <button
            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
            type="button"
            title="Close details"
            @click="closeDetails"
          >
            <X class="h-5 w-5 stroke-[1.75]" />
          </button>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
          <section class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="space-y-5">
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Request ID
                </p>
                <p class="font-heading text-xl font-bold text-[#003527]">
                  {{ viewingRecord.id }}
                </p>
              </div>
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Amount Requested
                </p>
                <p
                  class="font-heading text-3xl font-bold leading-tight text-[#006C49]"
                >
                  {{ formatPeso(viewingRecord.amount) }}
                </p>
              </div>
            </div>

            <div class="flex flex-col items-start sm:items-end">
              <p
                class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-500"
              >
                Status
              </p>
              <span
                :class="[
                  'inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide',
                  statusPillClass(viewingRecord.status),
                ]"
              >
                {{ viewingRecord.status }}
              </span>
            </div>
          </section>

          <section
            class="space-y-5 rounded-lg border border-slate-200 bg-slate-50/60 p-5"
          >
            <div>
              <p
                class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
              >
                Purpose
              </p>
              <p class="text-sm font-medium leading-relaxed text-slate-800">
                {{ viewingRecord.purpose }}
              </p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Current Outstanding Balance
                </p>
                <p class="font-heading text-lg font-bold text-[#003527]">
                  {{ formatPeso(outstandingBalance(viewingRecord)) }}
                </p>
              </div>
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Settlement Due Date
                </p>
                <p class="text-sm font-semibold text-slate-800">
                  {{ viewingRecord.dueDate }}
                </p>
              </div>
            </div>

            <div>
              <p
                class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-500"
              >
                Request Document
              </p>
              <div
                class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-100 px-4 py-3"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <span
                    class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-red-50 text-danger"
                  >
                    <FileDown class="h-5 w-5" />
                  </span>
                  <p class="truncate text-sm font-semibold text-slate-800">
                    {{ viewingRecord.documentFileName }}
                  </p>
                </div>
                <button
                  class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-[#006C49] transition-colors hover:bg-[#006C49]/10"
                  type="button"
                  title="Download request document"
                  @click="downloadDocument"
                >
                  <Download class="h-5 w-5" />
                </button>
              </div>
            </div>
          </section>

          <section>
            <h3 class="mb-2 font-heading text-sm font-bold text-[#003527]">
              Admin Notes
            </h3>
            <div
              class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
            >
              <p class="text-sm font-medium leading-relaxed text-slate-800">
                {{ viewingRecord.adminNotes }}
              </p>
            </div>
          </section>

          <section
            v-if="['approved', 'disbursed'].includes(viewingRecord.status)"
            class="space-y-4 rounded-lg border border-[#006C49]/15 bg-[#F0FDF4] p-5"
          >
            <div class="flex items-start gap-3">
              <ShieldCheck
                class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#006C49]"
              />
              <p class="text-sm leading-relaxed text-slate-800">
                This certifies that I received the cash advance with amount of
                <span class="font-bold text-[#006C49]">{{
                  formatPeso(viewingRecord.amount)
                }}</span
                >.
              </p>
            </div>

            <div>
              <div
                v-if="viewingRecord.acknowledgedAt"
                class="relative h-36 overflow-hidden rounded-lg border border-slate-300 bg-white flex items-center justify-center"
              >
                <img :src="viewingRecord.signatureImage" class="max-h-full max-w-full" alt="Signature" />
                <div
                  class="absolute bottom-2 right-3 flex items-center gap-1 text-success bg-white/80 px-2 py-1 rounded"
                >
                  <ShieldCheck class="h-4 w-4" />
                  <span class="text-[10px] font-bold uppercase tracking-widest"
                    >Digitally Verified</span
                  >
                </div>
              </div>
              <div
                v-else
                class="relative h-36 overflow-hidden rounded-lg border border-slate-300 bg-white"
              >
                <canvas
                  ref="signatureCanvas"
                  class="h-full w-full touch-none"
                  @pointerdown="startSignature"
                  @pointermove="drawSignature"
                  @pointerup="stopSignature"
                  @pointerleave="stopSignature"
                  @pointercancel="stopSignature"
                />
                <span
                  v-if="!signatureStarted"
                  class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs font-semibold text-slate-400"
                >
                  Draw your signature here using your mouse
                </span>
              </div>
              <div v-if="!viewingRecord.acknowledgedAt" class="mt-2 flex justify-end gap-3">
                <button
                  class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-600 transition-colors hover:bg-slate-100"
                  type="button"
                  @click="clearSignature"
                >
                  <RotateCcw class="h-4 w-4" />
                  Clear Signature
                </button>
                <button
                  class="btn btn-primary text-xs px-4 py-2 disabled:opacity-50 disabled:cursor-not-allowed"
                  type="button"
                  :disabled="!signatureStarted"
                  @click="showAcknowledgeModal = true"
                >
                  I acknowledge
                </button>
              </div>
            </div>
          </section>
        </div>

        <footer
          class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between"
        >
          <p>
            <span class="font-bold uppercase tracking-widest"
              >Requested Date</span
            >
            <span class="ml-2 font-semibold text-slate-800">{{
              formatDateOnly(viewingRecord.date)
            }}</span>
          </p>
          <p>
            <span class="font-bold uppercase tracking-widest"
              >Last Updated</span
            >
            <span class="ml-2 font-semibold text-slate-800">{{
              formatDateOnly(viewingRecord.updatedAt)
            }}</span>
          </p>
        </footer>
      </div>
    </div>

    <!-- Acknowledge Modal -->
    <BaseModal
      :isOpen="showAcknowledgeModal"
      @close="showAcknowledgeModal = false"
      zIndexClass="z-[60]"
      contentClass="p-8 text-center"
    >
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 text-primary">
        <ShieldCheck class="h-10 w-10" />
      </div>

      <h3 class="mt-6 font-heading text-xl font-bold text-slate-900">
        Acknowledge Cash Advance
      </h3>
      <p class="mt-2 text-sm leading-relaxed text-slate-500">
        Are you sure you want to acknowledge the receipt of this cash advance? This action will finalize your receipt of the funds.
      </p>

      <div class="mt-6 flex gap-3">
        <button
          class="btn btn-secondary flex-1"
          type="button"
          @click="showAcknowledgeModal = false"
        >
          Cancel
        </button>
        <button
          class="btn btn-primary flex-1 text-white"
          type="button"
          @click="confirmAcknowledge"
        >
          Yes, I Acknowledge
        </button>
      </div>
    </BaseModal>

    <!-- Admin Decision Confirmation -->
    <BaseModal
      :isOpen="!!confirmationAction"
      @close="cancelConfirmation"
      zIndexClass="z-[60]"
      contentClass="p-8 text-center"
    >
      <div
        :class="[
          'mx-auto flex h-20 w-20 items-center justify-center rounded-full',
          confirmationAction === 'approve'
            ? 'bg-success/10 text-success'
            : 'bg-danger/10 text-danger',
        ]"
      >
        <ShieldCheck
          v-if="confirmationAction === 'approve'"
          class="h-10 w-10"
        />
        <X v-else class="h-10 w-10" />
      </div>

      <h3 class="mt-6 font-heading text-xl font-bold text-slate-900">
        {{
          confirmationAction === "approve"
            ? "Approve Advance Request?"
            : "Reject Advance Request?"
        }}
      </h3>
      <p class="mt-2 text-sm leading-relaxed text-slate-500">
        Confirming this action will finalize the request status for
        {{ viewingRecord?.id }}.
      </p>

      <div class="mt-6 rounded-lg bg-slate-50 p-4 text-left">
        <p class="section-label mb-1">Final Admin Notes</p>
        <p class="text-sm font-medium italic text-primary">
          {{ adminReviewNotes || "No notes provided." }}
        </p>
      </div>

      <div class="mt-6 flex gap-3">
        <button
          class="btn btn-secondary flex-1"
          type="button"
          @click="cancelConfirmation"
        >
          Go Back
        </button>
        <button
          :class="[
            'btn flex-1 text-white',
            confirmationAction === 'approve'
              ? 'btn-primary'
              : '!bg-danger !border-danger hover:!bg-red-700',
          ]"
          type="button"
          @click="confirmAdminDecision"
        >
          {{
            confirmationAction === "approve"
              ? "Confirm Approval"
              : "Confirm Rejection"
          }}
        </button>
      </div>
    </BaseModal>

    <!-- Previous record details panel retained inactive for reference -->
    <div
      v-if="false && viewingRecord"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
    >
      <div
        class="card p-0 w-full max-w-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]"
      >
        <div
          class="bg-primary text-white px-6 py-4 flex items-center justify-between"
        >
          <div class="flex items-center gap-3">
            <FileText class="w-5 h-5" />
            <div>
              <h3 class="text-xs font-bold uppercase tracking-widest">
                Cash Advance / Settlement Documentation
              </h3>
              <p class="text-[10px] text-white/60 tracking-wider">
                REF: {{ viewingRecord.id }}
              </p>
            </div>
          </div>
          <button
            class="text-white/50 hover:text-white transition-none"
            @click="closeDetails"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 flex flex-col md:flex-row gap-6">
          <div class="flex-1 space-y-4">
            <div>
              <p
                class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
              >
                PURPOSE / DESCRIPTION
              </p>
              <p class="text-sm font-bold text-slate-800 uppercase">
                {{ viewingRecord.purpose }}
              </p>
            </div>
            <div
              class="grid grid-cols-2 gap-6 pt-2 border-t border-slate-100 mt-2"
            >
              <div>
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  AMOUNT ISSUED
                </p>
                <p
                  class="text-lg font-bold text-primary font-mono tracking-tighter"
                >
                  ₱{{ viewingRecord.amount?.toLocaleString() }}
                </p>
              </div>
              <div>
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  CURRENT OUTSTANDING
                </p>
                <p
                  :class="[
                    'text-lg font-bold font-mono tracking-tighter',
                    viewingRecord.balance > 0 ? 'text-danger' : 'text-success',
                  ]"
                >
                  ₱{{ viewingRecord.balance?.toLocaleString() }}
                </p>
              </div>
              <div>
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  REQUESTED BY
                </p>
                <p class="text-sm font-bold text-slate-700 uppercase">
                  {{ viewingRecord.requestedBy }}
                </p>
              </div>
              <div>
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  SETTLEMENT DUE DATE
                </p>
                <p class="text-sm font-bold text-slate-700 uppercase">
                  {{ viewingRecord.dueDate }}
                </p>
              </div>
              <div class="col-span-2 mt-4 flex flex-col items-start gap-1">
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  VERDICT / STATUS
                </p>
                <StatusBadge :status="viewingRecord.status" />
              </div>
            </div>
          </div>
          <div
            v-if="['pending', 'completed'].includes(viewingRecord.status)"
            class="w-full md:w-80 border border-slate-200 bg-clinical flex flex-col h-[400px]"
          >
            <div
              class="p-2 border-b border-slate-200 bg-slate-50 flex items-center justify-between"
            >
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-primary"></span>
                <span
                  class="text-[9px] font-bold text-slate-500 uppercase tracking-widest"
                  >SCAN_TARGET: RECEIPT_01.PNG</span
                >
              </div>
            </div>
            <div
              class="flex-1 p-2 flex items-center justify-center bg-slate-200/50 overflow-hidden relative group"
            >
              <img
                src="/mock_receipt.png"
                alt="Receipt Attachment"
                class="max-w-full max-h-full object-contain border border-slate-300 shadow-md transform transition-transform duration-300 hover:scale-[1.02]"
              />
            </div>
          </div>
        </div>

        <div
          v-if="auth.isAdmin && viewingRecord.status === 'submitted'"
          class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shadow-inner"
        >
          <button
            class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6"
            @click="
              closeDetails();
              openRejectModal(viewingRecord.id, 'advance');
            "
          >
            REJECT ADVANCE
          </button>
          <button
            class="btn btn-cta px-6"
            @click="
              quickApproveAdvance(viewingRecord.id);
              closeDetails();
            "
          >
            APPROVE ADVANCE
          </button>
        </div>
        <div
          v-else-if="auth.isAdmin && viewingRecord.status === 'pending'"
          class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shadow-inner"
        >
          <button
            class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6"
            @click="
              closeDetails();
              openRejectModal(viewingRecord.id, 'settlement');
            "
          >
            REJECT LIQUIDATION
          </button>
          <button
            class="btn btn-cta px-6"
            @click="
              quickApproveSettlement(viewingRecord.id);
              closeDetails();
            "
          >
            APPROVE LIQUIDATION
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.1s linear;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
