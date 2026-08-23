<script setup>
import { computed, ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useToast } from "@/composables/useToast";
import BaseModal from "@/components/base/BaseModal.vue";
import StatusBadge from "@/components/base/StatusBadge.vue";
import DecisionConfirmationModal from "@/components/base/DecisionConfirmationModal.vue";
import { formatPeso } from "@/utils/formatters";
import {
  X,
  CheckCircle,
  Wallet,
  FileDown,
  Download,
  ShieldCheck,
  RotateCcw,
  FileText,
} from "lucide-vue-next";

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true,
  },
  record: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["close"]);

const auth = useAuthStore();
const store = useCashAdvanceStore();
const { addToast } = useToast();

const signatureCanvas = ref(null);
const isSigning = ref(false);
const signatureStarted = ref(false);
const adminReviewNotes = ref("");
const confirmationAction = ref("");
const isAdminDecisionSubmitting = ref(false);
const showAcknowledgeModal = ref(false);
const adminPassword = ref("");

const documentData = ref(null);
const isLoadingDocument = ref(false);
const isOwnSubmission = computed(() => {
  const currentUserId = auth.user?.id;
  const ownerId =
    props.record?.userId ??
    props.record?.user_id ??
    props.record?.requester?.id ??
    props.record?.user?.id;

  return (
    currentUserId !== null &&
    currentUserId !== undefined &&
    ownerId !== null &&
    ownerId !== undefined &&
    String(currentUserId) === String(ownerId)
  );
});

const showSignatureSection = computed(
  () => Boolean(props.record?.signatureImage) || isOwnSubmission.value,
);

const canAcknowledgeFromCurrentView = computed(
  () =>
    isOwnSubmission.value &&
    props.record?.status === "disbursed" &&
    !props.record?.acknowledgedAt,
);

watch(
  () => props.isOpen,
  async (newVal) => {
    if (newVal) {
      adminReviewNotes.value = "";
      confirmationAction.value = "";
      showAcknowledgeModal.value = false;
      documentData.value = props.record?.document || null;
      adminPassword.value = "";
      clearSignature();
    }
  },
);

function closeDetails() {
  emit("close");
}

function outstandingBalance(record) {
  if (!record) return 0;
  return Number(record.balance ?? record.amount ?? 0);
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

function normalizeStatus(status) {
  const normalized = String(status || "").toLowerCase();
  const statusMap = {
    pending: "pending",
    approved: "approved",
    disbursed: "disbursed",
    signed: "disbursed",
    liquidated: "liquidated",
    rejected: "rejected",
  };
  return statusMap[normalized] || normalized;
}

function isTimelineStepCompleted(stepIndex) {
  const status = normalizeStatus(props.record?.status);
  const order = ["pending", "approved", "disbursed", "liquidated"];
  const currentIndex = order.indexOf(status);
  if (status === "rejected") {
    // If rejected, steps up to 1 (approved) are complete but with rejection at decision
    return stepIndex <= 0;
  }
  return stepIndex < currentIndex;
}

function isTimelineStepCurrent(stepIndex) {
  const status = normalizeStatus(props.record?.status);
  const order = ["pending", "approved", "disbursed", "liquidated"];
  const currentIndex = order.indexOf(status);
  if (status === "rejected") return stepIndex === 1;
  return stepIndex === currentIndex;
}

async function downloadDocument() {
  if (!props.record) return;
  if (!documentData.value && props.record.id) {
    isLoadingDocument.value = true;
    try {
      documentData.value = await store.fetchDocument(props.record.id);
    } catch (error) {
      console.error("Failed to load document", error);
    } finally {
      isLoadingDocument.value = false;
    }
  }

  const fileName =
    documentData.value?.file_name ||
    props.record.documentFileName ||
    "document.pdf";
  const fileUrl =
    documentData.value?.file_url ||
    props.record.documentUrl ||
    "/mock_receipt.png";

  const a = document.createElement("a");
  a.href = fileUrl;
  a.download = fileName;
  a.target = "_blank";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function requestConfirmation(action) {
  if (
    isOwnSubmission.value &&
    ["approve", "reject", "disburse"].includes(action)
  ) {
    addToast({
      message: "You cannot process your own request.",
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
  if (!props.record) return;

  const id = props.record.id;

  try {
    isAdminDecisionSubmitting.value = true;
    if (confirmationAction.value === "approve") {
      await store.approveRequest(id, adminReviewNotes.value);
    } else if (confirmationAction.value === "reject") {
      const reason = adminReviewNotes.value || "Rejected by admin";
      await store.rejectRequest(id, reason);
    } else if (confirmationAction.value === "disburse") {
      await store.disburseRequest(id, {
        channel: "System Disbursement",
        reference: `REF-${id}-${Date.now()}`
      });
    }

    addToast({
      message: `Request successfully ${confirmationAction.value}d`,
      type: "success",
    });
    confirmationAction.value = "";
    closeDetails();
  } catch (error) {
    addToast({ message: error.message || "Action failed", type: "error" });
  } finally {
    isAdminDecisionSubmitting.value = false;
  }
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

async function confirmAcknowledge() {
  if (!props.record) return;
  const canvas = signatureCanvas.value;
  if (!canvas) return;
  const signatureData = canvas.toDataURL("image/png");

  try {
    await store.acknowledgeRequest(props.record.id, signatureData);
    addToast({
      message: "Cash advance acknowledged successfully.",
      type: "success",
    });
    showAcknowledgeModal.value = false;
    closeDetails();
  } catch (error) {
    addToast({ message: error.message || "Action failed", type: "error" });
  }
}
</script>

<template>
  <div v-if="isOpen && record">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
      <!-- ═══════════════ ADMIN VIEW ═══════════════ -->
      <div
        v-if="auth.isAdmin"
        class="relative bg-white w-full max-w-[960px] rounded-3xl shadow-[0_20px_40px_-10px_rgba(0,0,0,0.1)] flex flex-col overflow-hidden max-h-[96vh]"
        @click.stop
      >
        <!-- Close Button -->
        <button
          @click="closeDetails"
          class="absolute top-2 right-2 text-slate-400 hover:bg-slate-100 transition-colors p-1.5 rounded-full flex items-center justify-center z-10"
        >
          <X class="w-4 h-4" />
        </button>

        <!-- Header -->
        <div class="px-6 pt-8 pb-4 border-b border-slate-100 shrink-0">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">
                Cash Advance Ref #{{ record.id }}
              </p>
              <h2 class="text-2xl font-bold text-slate-800">
                {{ formatPeso(record.amount) }}
              </h2>
            </div>
            <StatusBadge :status="record.status" />
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
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isTimelineStepCompleted(0) || isTimelineStepCurrent(0)
                      ? 'bg-emerald-500 text-white'
                      : isTimelineStepCurrent(0)
                        ? 'bg-amber-400 text-white animate-pulse'
                        : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(0) || isTimelineStepCurrent(0)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="isTimelineStepCurrent(0)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none" /></svg>
                  <span v-else class="text-[10px] font-bold">1</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Submitted</p>
                <p class="text-xs text-slate-700 font-medium truncate max-w-full px-1">
                  {{ record.requestedBy || "Employee" }}
                </p>
              </div>

              <!-- Step 2: Approved -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    normalizeStatus(record.status) === 'rejected'
                      ? 'bg-red-500 text-white'
                      : isTimelineStepCompleted(1)
                        ? 'bg-emerald-500 text-white'
                        : isTimelineStepCurrent(1)
                          ? 'bg-amber-400 text-white animate-pulse'
                          : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(1) && normalizeStatus(record.status) !== 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="normalizeStatus(record.status) === 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  <svg v-else-if="isTimelineStepCurrent(1)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none" /></svg>
                  <span v-else class="text-[10px] font-bold">2</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Approved</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="[
                    normalizeStatus(record.status) === 'rejected' ? 'text-red-600' : '',
                    isTimelineStepCompleted(1) && normalizeStatus(record.status) !== 'rejected' ? 'text-slate-700' : '',
                    isTimelineStepCurrent(1) ? 'text-amber-600' : '',
                    !isTimelineStepCompleted(1) && !isTimelineStepCurrent(1) && normalizeStatus(record.status) !== 'rejected' ? 'text-slate-400' : '',
                  ]"
                >
                  {{ normalizeStatus(record.status) === 'rejected' ? 'Rejected' : isTimelineStepCompleted(1) ? 'Approved' : isTimelineStepCurrent(1) ? 'In Review' : 'Awaiting' }}
                </p>
              </div>

              <!-- Step 3: Disbursed -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isTimelineStepCompleted(2)
                      ? 'bg-emerald-500 text-white'
                      : isTimelineStepCurrent(2)
                        ? 'bg-amber-400 text-white animate-pulse'
                        : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(2)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="isTimelineStepCurrent(2)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none" /></svg>
                  <span v-else class="text-[10px] font-bold">3</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Disbursed</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="[
                    isTimelineStepCompleted(2) ? 'text-slate-700' : '',
                    isTimelineStepCurrent(2) ? 'text-amber-600' : '',
                    !isTimelineStepCompleted(2) && !isTimelineStepCurrent(2) ? 'text-slate-400' : '',
                  ]"
                >
                  {{ isTimelineStepCompleted(2) ? 'Released' : isTimelineStepCurrent(2) ? 'Processing' : 'Pending' }}
                </p>
              </div>

              <!-- Step 4: Liquidated -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isTimelineStepCompleted(3) || normalizeStatus(record.status) === 'liquidated'
                      ? 'bg-emerald-500 text-white'
                      : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(3) || normalizeStatus(record.status) === 'liquidated'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="text-[10px] font-bold">4</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Liquidated</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="normalizeStatus(record.status) === 'liquidated' ? 'text-emerald-600' : 'text-slate-400'"
                >
                  {{ normalizeStatus(record.status) === 'liquidated' ? 'Complete' : 'Pending' }}
                </p>
              </div>
            </div>
          </section>

          <!-- Details Grid -->
          <section class="grid grid-cols-2 gap-x-6 gap-y-3 pt-4 border-t border-slate-100">
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Requestor</p>
              <p class="text-sm text-slate-700">{{ record.requestedBy || "--" }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Date Requested</p>
              <p class="text-sm text-slate-700">{{ formatDateOnly(record.date) }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Purpose</p>
              <p class="text-sm text-slate-700">{{ record.purpose }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Due Date</p>
              <p class="text-sm text-slate-700">{{ record.dueDate || "--" }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Outstanding Balance</p>
              <p class="text-sm font-semibold text-primary">{{ formatPeso(outstandingBalance(record)) }}</p>
            </div>
          </section>

          <!-- Document Attachment -->
          <section v-if="documentData || record.documentFileName" class="pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">Document</p>
            <a
              class="inline-flex items-center gap-2 text-sm text-primary hover:underline cursor-pointer"
              @click="downloadDocument"
            >
              <FileText class="w-3.5 h-3.5" />
              <span>{{ documentData ? documentData.file_name : record.documentFileName }}</span>
              <Download class="w-3 h-3" />
            </a>
          </section>

          <!-- Loading Document Skeleton -->
          <section v-else-if="isLoadingDocument" class="pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">Document</p>
            <div class="h-4 w-40 animate-pulse rounded bg-slate-200"></div>
          </section>

          <!-- Signature Section -->
          <section v-if="showSignatureSection" class="space-y-3 pt-3 border-t border-slate-100">
            <div v-if="record.status === 'disbursed'">
              <p class="text-sm text-slate-600 mb-3">
                Employee certifies receipt of
                <span class="font-semibold text-primary">{{ formatPeso(record.amount) }}</span>.
              </p>
            </div>

            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-2">Signature</p>
              <div
                v-if="record.acknowledgedAt || record.signatureImage"
                class="relative flex h-32 w-full items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white"
              >
                <img
                  :src="record.signatureImage"
                  class="max-h-full max-w-full"
                  alt="Signature"
                />
                <span class="absolute bottom-2 right-3 text-[10px] font-medium text-emerald-600">
                  Verified
                </span>
              </div>
              <div
                v-else-if="canAcknowledgeFromCurrentView"
                class="relative h-32 overflow-hidden rounded-lg border border-slate-200 bg-white"
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
                  class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs text-slate-400"
                >
                  Draw your signature here
                </span>
              </div>

              <div v-if="canAcknowledgeFromCurrentView" class="mt-2 flex justify-end gap-2">
                <button
                  class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                  type="button"
                  @click="clearSignature"
                >
                  Clear
                </button>
                <button
                  class="px-4 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50"
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

        <!-- Footer (Admin: Pending / Approved) -->
        <footer
          v-if="['pending', 'approved'].includes(record.status)"
          class="px-6 py-4 border-t border-slate-100 flex flex-col gap-3 sm:flex-row shrink-0"
        >
          <p
            v-if="isOwnSubmission"
            class="text-sm text-danger text-center sm:text-left w-full"
          >
            You cannot process your own request.
          </p>
          <template v-else>
            <template v-if="record.status === 'pending'">
              <button
                class="flex-1 py-2.5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors"
                @click="requestConfirmation('reject')"
              >
                Reject
              </button>
              <button
                class="flex-1 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors shadow-sm flex items-center justify-center gap-2"
                @click="requestConfirmation('approve')"
              >
                <CheckCircle class="w-4 h-4" />
                Approve
              </button>
            </template>
            <template v-if="record.status === 'approved'">
              <button
                class="flex-1 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors shadow-sm flex items-center justify-center gap-2"
                @click="requestConfirmation('disburse')"
              >
                <Wallet class="w-4 h-4" />
                Disburse
              </button>
            </template>
          </template>
        </footer>
      </div>

      <!-- ═══════════════ EMPLOYEE VIEW ═══════════════ -->
      <div
        v-else
        class="relative bg-white w-full max-w-[960px] rounded-3xl shadow-[0_20px_40px_-10px_rgba(0,0,0,0.1)] flex flex-col overflow-hidden max-h-[96vh]"
        @click.stop
      >
        <!-- Close Button -->
        <button
          @click="closeDetails"
          class="absolute top-2 right-2 text-slate-400 hover:bg-slate-100 transition-colors p-1.5 rounded-full flex items-center justify-center z-10"
        >
          <X class="w-4 h-4" />
        </button>

        <!-- Header -->
        <div class="px-6 pt-8 pb-4 border-b border-slate-100 shrink-0">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">
                Cash Advance Ref #{{ record.id }}
              </p>
              <h2 class="text-2xl font-bold text-slate-800">
                {{ formatPeso(record.amount) }}
              </h2>
            </div>
            <StatusBadge :status="record.status" />
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
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isTimelineStepCompleted(0) || isTimelineStepCurrent(0)
                      ? 'bg-emerald-500 text-white'
                      : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(0) || isTimelineStepCurrent(0)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="text-[10px] font-bold">1</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Submitted</p>
                <p class="text-xs text-slate-700 font-medium truncate max-w-full px-1">
                  {{ record.requestedBy || "You" }}
                </p>
              </div>

              <!-- Step 2: Approved -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    normalizeStatus(record.status) === 'rejected'
                      ? 'bg-red-500 text-white'
                      : isTimelineStepCompleted(1)
                        ? 'bg-emerald-500 text-white'
                        : isTimelineStepCurrent(1)
                          ? 'bg-amber-400 text-white animate-pulse'
                          : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(1) && normalizeStatus(record.status) !== 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="normalizeStatus(record.status) === 'rejected'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  <svg v-else-if="isTimelineStepCurrent(1)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none" /></svg>
                  <span v-else class="text-[10px] font-bold">2</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Approved</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="[
                    normalizeStatus(record.status) === 'rejected' ? 'text-red-600' : '',
                    isTimelineStepCompleted(1) && normalizeStatus(record.status) !== 'rejected' ? 'text-slate-700' : '',
                    isTimelineStepCurrent(1) ? 'text-amber-600' : '',
                    !isTimelineStepCompleted(1) && !isTimelineStepCurrent(1) && normalizeStatus(record.status) !== 'rejected' ? 'text-slate-400' : '',
                  ]"
                >
                  {{ normalizeStatus(record.status) === 'rejected' ? 'Rejected' : isTimelineStepCompleted(1) ? 'Approved' : isTimelineStepCurrent(1) ? 'Pending' : 'Awaiting' }}
                </p>
              </div>

              <!-- Step 3: Disbursed -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    isTimelineStepCompleted(2)
                      ? 'bg-emerald-500 text-white'
                      : isTimelineStepCurrent(2)
                        ? 'bg-amber-400 text-white animate-pulse'
                        : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="isTimelineStepCompleted(2)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <svg v-else-if="isTimelineStepCurrent(2)" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3" /><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none" /></svg>
                  <span v-else class="text-[10px] font-bold">3</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Disbursed</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="[
                    isTimelineStepCompleted(2) ? 'text-slate-700' : '',
                    isTimelineStepCurrent(2) ? 'text-amber-600' : '',
                    !isTimelineStepCompleted(2) && !isTimelineStepCurrent(2) ? 'text-slate-400' : '',
                  ]"
                >
                  {{ isTimelineStepCompleted(2) ? 'Released' : isTimelineStepCurrent(2) ? 'Processing' : 'Pending' }}
                </p>
              </div>

              <!-- Step 4: Liquidated -->
              <div class="relative flex flex-col items-center text-center flex-1">
                <div
                  :class="[
                    'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white z-10 shrink-0 mb-2',
                    normalizeStatus(record.status) === 'liquidated'
                      ? 'bg-emerald-500 text-white'
                      : 'bg-slate-200 text-slate-400'
                  ]"
                >
                  <svg v-if="normalizeStatus(record.status) === 'liquidated'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  <span v-else class="text-[10px] font-bold">4</span>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wide mb-0.5">Liquidated</p>
                <p class="text-xs font-medium truncate max-w-full px-1"
                  :class="normalizeStatus(record.status) === 'liquidated' ? 'text-emerald-600' : 'text-slate-400'"
                >
                  {{ normalizeStatus(record.status) === 'liquidated' ? 'Complete' : 'Pending' }}
                </p>
              </div>
            </div>
          </section>

          <!-- Details Grid -->
          <section class="grid grid-cols-2 gap-x-6 gap-y-3 pt-4 border-t border-slate-100">
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Request ID</p>
              <p class="text-sm font-semibold text-primary">{{ record.id }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Date Requested</p>
              <p class="text-sm text-slate-700">{{ formatDateOnly(record.date) }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Purpose</p>
              <p class="text-sm text-slate-700">{{ record.purpose }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Outstanding Balance</p>
              <p class="text-sm font-semibold text-primary">{{ formatPeso(outstandingBalance(record)) }}</p>
            </div>
            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-0.5">Settlement Due Date</p>
              <p class="text-sm text-slate-700">{{ record.dueDate || "--" }}</p>
            </div>
          </section>

          <!-- Document -->
          <section v-if="documentData || record.documentFileName" class="pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">Document</p>
            <a
              class="inline-flex items-center gap-2 text-sm text-primary hover:underline cursor-pointer"
              @click="downloadDocument"
            >
              <FileText class="w-3.5 h-3.5" />
              <span>{{ documentData ? documentData.file_name : record.documentFileName }}</span>
              <Download class="w-3 h-3" />
            </a>
          </section>

          <!-- Loading Document Skeleton -->
          <section v-else-if="isLoadingDocument" class="pt-2 border-t border-slate-100">
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">Document</p>
            <div class="h-4 w-40 animate-pulse rounded bg-slate-200"></div>
          </section>

          <!-- Admin Notes / Rejection -->
          <div
            v-if="record.adminNotes"
            class="pt-2 border-t border-slate-100"
          >
            <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-1">
              {{ normalizeStatus(record.status) === 'rejected' ? 'Rejection Reason' : 'Admin Notes' }}
            </p>
            <p class="text-sm text-slate-700">{{ record.adminNotes }}</p>
          </div>

          <!-- Signature Section (Employee Acknowledgment for Disbursed) -->
          <section v-if="record.status === 'disbursed'" class="space-y-3 pt-3 border-t border-slate-100">
            <p class="text-sm text-slate-600">
              I certify that I received the cash advance of
              <span class="font-semibold text-primary">{{ formatPeso(record.amount) }}</span>.
            </p>

            <div>
              <p class="text-[11px] text-slate-400 uppercase tracking-wide mb-2">Signature</p>
              <div
                v-if="record.acknowledgedAt"
                class="relative flex h-32 w-full items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white"
              >
                <img
                  :src="record.signatureImage"
                  class="max-h-full max-w-full"
                  alt="Signature"
                />
                <span class="absolute bottom-2 right-3 text-[10px] font-medium text-emerald-600">
                  Verified
                </span>
              </div>
              <div
                v-else
                class="relative h-32 overflow-hidden rounded-lg border border-slate-200 bg-white"
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
                  class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs text-slate-400"
                >
                  Draw your signature here
                </span>
              </div>
              <div v-if="!record.acknowledgedAt" class="mt-2 flex justify-end gap-2">
                <button
                  class="px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"
                  type="button"
                  @click="clearSignature"
                >
                  Clear
                </button>
                <button
                  class="px-4 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-50"
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

        <!-- Footer (Employee) -->
        <footer class="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 shrink-0">
          <p>
            <span class="font-bold uppercase tracking-widest">Requested</span>
            <span class="ml-2 font-semibold text-slate-800">{{ formatDateOnly(record.date) }}</span>
          </p>
          <p>
            <span class="font-bold uppercase tracking-widest">Updated</span>
            <span class="ml-2 font-semibold text-slate-800">{{ formatDateOnly(record.updatedAt) }}</span>
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
        Are you sure you want to acknowledge the receipt of this cash advance?
        This action will finalize your receipt of the funds.
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
          class="btn btn-cta flex-1"
          type="button"
          @click="confirmAcknowledge"
        >
          Yes, I Acknowledge
        </button>
      </div>
    </BaseModal>

    <!-- Admin Decision Confirmation -->
    <DecisionConfirmationModal
      :is-open="!!confirmationAction"
      :mode="confirmationAction || 'approve'"
      :is-submitting="isAdminDecisionSubmitting"
      v-model:password="adminPassword"
      v-model:comment="adminReviewNotes"
      @close="cancelConfirmation"
      @confirm="confirmAdminDecision"
    />
  </div>
</template>
