<script setup>
import { ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useToast } from "@/composables/useToast";
import BaseModal from "@/components/base/BaseModal.vue";
import StatusBadge from "@/components/base/StatusBadge.vue";
import { formatPeso } from "@/utils/formatters";
import DecisionConfirmationModal from "@/components/reimbursements/DecisionConfirmationModal.vue";
import {
  X,
  CheckCircle,
  Wallet,
  FileDown,
  Download,
  Info,
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

const emit = defineEmits([
  "close",
  "reject",
  "approve-advance",
  "approve-settlement",
]);

const auth = useAuthStore();
const store = useCashAdvanceStore();
const { addToast } = useToast();

const signatureCanvas = ref(null);
const isSigning = ref(false);
const signatureStarted = ref(false);
const adminReviewNotes = ref("");
const confirmationAction = ref("");
const showAcknowledgeModal = ref(false);
const adminPassword = ref("");

const documentData = ref(null);
const isLoadingDocument = ref(false);

watch(
  () => props.isOpen,
  async (newVal) => {
    if (newVal) {
      adminReviewNotes.value = "";
      confirmationAction.value = "";
      showAcknowledgeModal.value = false;
      documentData.value = null;
      adminPassword.value = "";
      clearSignature();

      if (props.record?.id) {
        isLoadingDocument.value = true;
        try {
          const doc = await store.fetchDocument(props.record.id);
          documentData.value = doc;
        } catch (error) {
          console.error("Failed to load document", error);
        } finally {
          isLoadingDocument.value = false;
        }
      }
    }
  },
);

function closeDetails() {
  emit("close");
}

function statusPillClass(status) {
  const classes = {
    unliquidated: "bg-amber-50 text-amber-700 border-amber-200",
    liquidated: "bg-blue-50 text-blue-700 border-blue-200",
    granted: "bg-emerald-50 text-emerald-700 border-emerald-200",
    approved: "bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]",
    disbursed: "bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]",
    signed: "bg-[#E0F2FE] text-[#075985] border-[#BAE6FD]",
    pending: "bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]",
    rejected: "bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]",
  };
  return classes[status] || "bg-slate-100 text-slate-600 border-slate-200";
}

function outstandingBalance(record) {
  if (!record) return 0;
  if (typeof record.balance === "number") return record.balance;
  return ["approved", "disbursed", "signed", "under-review", "overdue"].includes(record.status) ? record.amount : 0;
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

function downloadDocument() {
  if (!props.record) return;
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
  if (action !== "disburse" && adminReviewNotes.value.trim().length < 10) {
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
  if (!props.record) return;

  const id = props.record.id;

  try {
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
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 p-4 backdrop-blur-[1px]"
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
                class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-accent/20 bg-primary/10 font-heading text-sm font-bold text-primary"
              >
                {{
                  record.requestedBy
                    ?.split(" ")
                    .map((part) => part[0])
                    .join("")
                    .slice(0, 2) || "EA"
                }}
              </div>
              <div>
                <p class="section-label mb-1">Requestor Name</p>
                <h3 class="font-heading text-xl font-bold text-primary">
                  {{ record.requestedBy }}
                </h3>
              </div>
            </div>

            <div class="sm:text-right">
              <p class="section-label mb-2">Status</p>
              <span
                :class="[
                  'inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-bold uppercase tracking-wide',
                  statusPillClass(record.status),
                ]"
              >
                {{
                  record.status === "pending" ? "Pending Review" : record.status
                }}
              </span>
            </div>
          </section>

          <section
            class="rounded-lg border border-accent/20 bg-primary/5 p-6 text-center"
          >
            <p class="section-label mb-2">Amount Requested</p>
            <p
              class="font-heading text-[40px] font-extrabold leading-tight text-primary"
            >
              {{ formatPeso(record.amount) }}
            </p>
          </section>

          <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div
              class="rounded-lg border border-slate-200 bg-white p-4 md:col-span-2"
            >
              <p class="section-label mb-1">Purpose</p>
              <p class="text-base leading-relaxed text-slate-800">
                {{ record.purpose }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
              <p class="section-label mb-1">Date Requested</p>
              <p class="text-base font-bold text-slate-800">
                {{ record.date }}
              </p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
              <p class="section-label mb-1">Settlement Due Date</p>
              <p class="text-base font-bold text-slate-800">
                {{ record.dueDate }}
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
              formatPeso(outstandingBalance(record))
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
                  {{ record.documentFileName }}
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

          <section class="space-y-2" v-if="record.signatureImage">
            <p class="section-label">Employee Signature Verification Pad</p>
            <div
              class="relative flex h-32 w-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-200 bg-white"
            >
              <img
                :src="record.signatureImage"
                class="max-h-full max-w-full"
                alt="Signature"
              />
              <div
                class="absolute bottom-2 right-3 flex items-center gap-1 text-accent bg-white/80 px-2 py-1 rounded"
              >
                <ShieldCheck class="h-4 w-4" />
                <span class="text-[10px] font-bold uppercase tracking-widest"
                  >Digitally Verified</span
                >
              </div>
            </div>
          </section>

          <section class="space-y-2 pb-2" v-if="record.status === 'pending'">
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
                    : 'text-accent'
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
          v-if="['pending', 'approved'].includes(record.status)"
        >
          <div
            class="text-sm font-semibold text-danger text-center sm:text-left"
            v-if="record.userId === auth.user?.id"
          >
            You cannot process your own request.
          </div>
          <div v-else></div>
          <div class="flex flex-col w-full sm:w-auto sm:flex-row gap-3">
            <template v-if="record.status === 'pending'">
              <button
                class="btn btn-secondary w-full !border-danger/30 !text-danger hover:!bg-danger/5 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed"
                type="button"
                :disabled="record.userId === auth.user?.id"
                @click="requestConfirmation('reject')"
              >
                Reject
              </button>
              <button
                class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-accent px-4 text-sm font-bold text-white transition-colors hover:bg-accent/90 sm:w-auto disabled:cursor-not-allowed disabled:opacity-50"
                type="button"
                :disabled="record.userId === auth.user?.id"
                @click="requestConfirmation('approve')"
              >
                <CheckCircle class="h-4 w-4" />
                Approve
              </button>
            </template>
            <template v-if="record.status === 'approved'">
              <button
                class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-lg bg-accent px-4 text-sm font-bold text-white transition-colors hover:bg-accent/90 sm:w-auto disabled:cursor-not-allowed disabled:opacity-50"
                type="button"
                :disabled="record.userId === auth.user?.id"
                @click="requestConfirmation('disburse')"
              >
                <Wallet class="h-4 w-4" />
                Disburse
              </button>
            </template>
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
          <h2 class="font-heading text-xl font-bold text-primary">
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
                <p class="font-heading text-xl font-bold text-primary">
                  {{ record.id }}
                </p>
              </div>
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Amount Requested
                </p>
                <p
                  class="font-heading text-3xl font-bold leading-tight text-primary"
                >
                  {{ formatPeso(record.amount) }}
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
                  statusPillClass(record.status),
                ]"
              >
                {{ record.status }}
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
                {{ record.purpose }}
              </p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Current Outstanding Balance
                </p>
                <p class="font-heading text-lg font-bold text-primary">
                  {{ formatPeso(outstandingBalance(record)) }}
                </p>
              </div>
              <div>
                <p
                  class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500"
                >
                  Settlement Due Date
                </p>
                <p class="text-sm font-semibold text-slate-800">
                  {{ record.dueDate }}
                </p>
              </div>
            </div>

            <div>
              <p
                class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-500"
              >
                Request Document
              </p>

              <!-- Skeleton Loader -->
              <div
                v-if="isLoadingDocument"
                class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 animate-pulse"
              >
                <div class="flex min-w-0 items-center gap-3 w-full">
                  <div
                    class="h-10 w-10 flex-shrink-0 rounded-md bg-slate-200"
                  ></div>
                  <div class="h-4 w-1/2 rounded bg-slate-200"></div>
                </div>
                <div
                  class="h-9 w-9 flex-shrink-0 rounded-full bg-slate-200"
                ></div>
              </div>

              <!-- Loaded Document -->
              <div
                v-else-if="documentData || record.documentFileName"
                class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-100 px-4 py-3"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <span
                    class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-red-50 text-danger"
                  >
                    <FileDown class="h-5 w-5" />
                  </span>
                  <p class="truncate text-sm font-semibold text-slate-800">
                    {{
                      documentData
                        ? documentData.file_name
                        : record.documentFileName
                    }}
                  </p>
                </div>
                <button
                  class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-accent transition-colors hover:bg-accent-50"
                  type="button"
                  title="Download request document"
                  @click="downloadDocument"
                >
                  <Download class="h-5 w-5" />
                </button>
              </div>

              <!-- No Document Fallback -->
              <div
                v-else
                class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500 italic"
              >
                No document attached
              </div>
            </div>
          </section>

          <section>
            <h3 class="mb-2 font-heading text-sm font-bold text-primary">
              Admin Notes
            </h3>
            <div
              class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3"
            >
              <p class="text-sm font-medium leading-relaxed text-slate-800">
                {{ record.adminNotes }}
              </p>
            </div>
          </section>

          <section
            v-if="record.status === 'disbursed'"
            class="space-y-4 rounded-lg border border-accent/20 bg-accent-50 p-5"
          >
            <div class="flex items-start gap-3">
              <ShieldCheck
                class="mt-0.5 h-5 w-5 flex-shrink-0 text-accent"
              />
              <p class="text-sm leading-relaxed text-slate-800">
                This certifies that I received the cash advance with amount of
                <span class="font-bold text-primary">{{
                  formatPeso(record.amount)
                }}</span
                >.
              </p>
            </div>

            <div>
              <div
                v-if="record.acknowledgedAt"
                class="relative h-36 overflow-hidden rounded-lg border border-slate-300 bg-white flex items-center justify-center"
              >
                <img
                  :src="record.signatureImage"
                  class="max-h-full max-w-full"
                  alt="Signature"
                />
                <div
                  class="absolute bottom-2 right-3 flex items-center gap-1 text-accent bg-white/80 px-2 py-1 rounded"
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
              <div
                v-if="!record.acknowledgedAt"
                class="mt-2 flex justify-end gap-3"
              >
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
              formatDateOnly(record.date)
            }}</span>
          </p>
          <p>
            <span class="font-bold uppercase tracking-widest"
              >Last Updated</span
            >
            <span class="ml-2 font-semibold text-slate-800">{{
              formatDateOnly(record.updatedAt)
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
      <div
        class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 text-primary"
      >
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
          class="btn btn-primary flex-1 text-white"
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
      v-model:password="adminPassword"
      v-model:comment="adminReviewNotes"
      @close="cancelConfirmation"
      @confirm="confirmAdminDecision"
    />

    <!-- Previous record details panel retained inactive for reference -->
    <div
      v-if="false && record"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-[1px] p-4"
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
                REF: {{ record.id }}
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
                {{ record.purpose }}
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
                  ₱{{ record.amount?.toLocaleString() }}
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
                    record.balance > 0 ? 'text-danger' : 'text-success',
                  ]"
                >
                  ₱{{ record.balance?.toLocaleString() }}
                </p>
              </div>
              <div>
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  REQUESTED BY
                </p>
                <p class="text-sm font-bold text-slate-700 uppercase">
                  {{ record.requestedBy }}
                </p>
              </div>
              <div>
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  SETTLEMENT DUE DATE
                </p>
                <p class="text-sm font-bold text-slate-700 uppercase">
                  {{ record.dueDate }}
                </p>
              </div>
              <div class="col-span-2 mt-4 flex flex-col items-start gap-1">
                <p
                  class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                >
                  VERDICT / STATUS
                </p>
                <StatusBadge :status="record.status" />
              </div>
            </div>
          </div>
          <div
            v-if="['pending', 'completed'].includes(record.status)"
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
          v-if="auth.isAdmin && record.status === 'submitted'"
          class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shadow-inner"
        >
          <button
            class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6"
            @click="
              closeDetails();
              emit('reject', record.id, 'advance');
            "
          >
            REJECT ADVANCE
          </button>
          <button
            class="btn btn-cta px-6"
            @click="
              emit('approve-advance', record.id);
              closeDetails();
            "
          >
            APPROVE ADVANCE
          </button>
        </div>
        <div
          v-else-if="auth.isAdmin && record.status === 'pending'"
          class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shadow-inner"
        >
          <button
            class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6"
            @click="
              closeDetails();
              emit('reject', record.id, 'settlement');
            "
          >
            REJECT LIQUIDATION
          </button>
          <button
            class="btn btn-cta px-6"
            @click="
              emit('approve-settlement', record.id);
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
