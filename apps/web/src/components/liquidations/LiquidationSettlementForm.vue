<script setup>
import { ref } from "vue";
import {
  FilePieChart,
  CheckCircle,
  FileText,
  X,
  Upload,
  AlertTriangle,
  Calculator,
} from "lucide-vue-next";
import BaseButton from "@/components/base/BaseButton.vue";
import FileUpload from "@/components/base/FileUpload.vue";
import { formatPeso } from "@/utils/formatters";
import { vatOf } from "@/utils/receiptUtils";

const props = defineProps({
  selectedAdvance: {
    type: Object,
    default: null,
  },
  submitted: {
    type: Boolean,
    default: false,
  },
  receipts: {
    type: Array,
    default: () => [],
  },
  receiptCategoryOptions: {
    type: Array,
    default: () => [],
  },
  reportAttachment: {
    type: [Object, File, String],
    default: null,
  },
  existingLiquidation: {
    type: Object,
    default: null,
  },
  needsReportAttachmentReminder: {
    type: Boolean,
    default: false,
  },
  variance: {
    type: Number,
    default: 0,
  },
  shortfallExplanation: {
    type: String,
    default: "",
  },
  totalExpenseAmount: {
    type: Number,
    default: 0,
  },
  liquidationStatus: {
    type: String,
    default: "",
  },
  liquidationOutstandingBalance: {
    type: Number,
    default: 0,
  },
  overpaymentAmount: {
    type: Number,
    default: 0,
  },
  submitting: {
    type: Boolean,
    default: false,
  },
  hasIncompleteReceiptFields: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "update:receipts",
  "update:shortfallExplanation",
  "reload-console",
  "upload-error",
  "file-selected",
  "file-cleared",
  "forward-overpayment",
  "delete-liquidation",
  "submit-liquidation",
]);

const reportAttachmentInput = ref(null);

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

function attachmentFileName(file) {
  if (!file) return "";
  if (typeof file === "string")
    return file.split("/").pop() || "Attached Report";
  return file.name || "Attached Report";
}

function attachmentFileSize(file) {
  if (!file || typeof file === "string" || !file.size) return "";
  return `${(file.size / 1024 / 1024).toFixed(2)} MB`;
}
</script>

<template>
  <div
    v-if="!selectedAdvance"
    class="flex min-h-64 flex-col items-center justify-center gap-4 border-2 border-dashed bg-clinical/20 p-8 text-center card sm:p-16 xl:h-full"
  >
    <FilePieChart class="w-10 h-10 text-slate-200" />
    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
      Select an active advance to clear debt
    </p>
  </div>

  <div
    v-else-if="submitted"
    class="flex flex-col items-center gap-6 p-8 text-center border-t-2 card border-t-emerald-600 sm:p-12"
  >
    <CheckCircle class="w-12 h-12 text-emerald-600" />
    <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-primary">
      Submission Received
    </h3>
    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">
      Technician report for {{ selectedAdvance.id }} sent to audit.
    </p>
    <BaseButton variant="secondary" @click="$emit('reload-console')">
      RELOAD CONSOLE
    </BaseButton>
  </div>

  <div
    v-else
    class="flex flex-col gap-6 p-4 border-t-2 shadow-sm card border-t-primary sm:p-6"
  >
    <div
      class="flex flex-col gap-3 pb-4 border-b border-slate-100 sm:flex-row sm:items-start sm:justify-between"
    >
      <div class="min-w-0">
        <p
          class="mb-1 text-[9px] font-bold uppercase tracking-tighter text-slate-700"
        >
          RECONCILING REF: {{ selectedAdvance.id }}
        </p>
        <h3 class="text-xs font-bold tracking-widest uppercase text-primary">
          {{ selectedAdvance.purpose }}
        </h3>
      </div>
      <div class="sm:text-right">
        <p
          class="mb-1 text-[9px] font-bold uppercase tracking-widest text-slate-400"
        >
          OUTSTANDING BALANCE
        </p>
        <p class="font-mono text-2xl font-bold tracking-tighter text-primary">
          {{ formatPeso(selectedAdvance.balance || 0) }}
        </p>
      </div>
    </div>

    <div class="pt-4 border-t input-wrapper border-slate-100">
      <div
        class="flex flex-col gap-2 mb-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <label class="input-label !mb-0">DIGITAL RECEIPT ATTACHMENTS *</label>

      </div>
      <FileUpload
        :model-value="receipts"
        @update:model-value="$emit('update:receipts', $event)"
        :max-size-mb="2"
        empty-action-label="Upload Receipt"
        add-action-label="Upload Receipt"
        @upload-error="$emit('upload-error', $event)"
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
        <div class="grid grid-cols-1 xl:grid-cols-[180px_minmax(0,1fr)]">
          <aside
            class="p-4 border-b border-slate-200 bg-slate-100/70 xl:border-b-0 xl:border-r"
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
                <input class="bg-white input" v-model="receipt.ocrData.vendor" />
              </label>
              <label class="space-y-1">
                <span class="input-label">Date</span>
                <span class="relative block">
                  <input
                    type="date"
                    class="pr-10 bg-white input"
                    v-model="receipt.ocrData.date"
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
              class="grid grid-cols-1 gap-3 pt-4 border-t border-slate-200 md:grid-cols-2 xl:grid-cols-3"
            >
              <label class="space-y-1">
                <span class="input-label">Subtotal</span>
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
                class="p-3 border rounded-lg border-accent/20 bg-accent-50 md:col-span-2 xl:col-span-1"
              >
                <p class="input-label text-accent">Receipt Total</p>
                <input
                  type="number"
                  step="0.01"
                  class="input font-semibold !bg-white !text-primary font-heading text-lg sm:text-xl"
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
        @change="(e) => $emit('file-selected', e)"
      />
      <div class="flex flex-col gap-4">
        <div class="min-w-0">
          <p class="text-base font-bold font-heading text-primary">
            Cash Advance Report Letter
          </p>
          <p class="mt-1 text-sm text-slate-500">
            Attach your report letter for liquidation documentation.
          </p>
        </div>

        <div v-if="reportAttachment" class="flex flex-col gap-3">
          <div
            class="flex items-center justify-between rounded-lg border border-slate-200 bg-white p-3"
          >
            <div class="flex min-w-0 items-center gap-3">
              <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400"
              >
                <FileText class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-800">
                  {{ attachmentFileName(reportAttachment) }}
                </p>
                <p
                  v-if="attachmentFileSize(reportAttachment)"
                  class="text-xs font-semibold text-slate-400"
                >
                  {{ attachmentFileSize(reportAttachment) }}
                </p>
              </div>
            </div>
            <button
              class="rounded-full p-2 text-danger transition-colors hover:bg-red-50"
              type="button"
              aria-label="Remove report attachment"
              @click="$emit('file-cleared')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div
          class="flex flex-col gap-4 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="flex min-w-0 items-center gap-4">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-accent shadow-sm"
            >
              <Upload class="h-5 w-5" />
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-slate-800">Select file</p>
              <p class="text-xs text-slate-500">
                Upload report letter (Images, PDF, DOCX)
              </p>
            </div>
          </div>
          <button
            class="btn btn-cta min-h-[42px] w-full sm:w-fit"
            type="button"
            @click="reportAttachmentInput?.click()"
          >
            Browse
          </button>
        </div>
      </div>
    </section>

    <section
      v-if="existingLiquidation?.admin_note || existingLiquidation?.adminNote"
      class="p-4 bg-white border rounded-xl border-slate-200"
    >
      <p class="text-base font-bold font-heading text-primary">
        Admin Notes / Rejection Feedback
      </p>
      <p class="mt-2 text-sm leading-relaxed text-slate-600">
        {{ existingLiquidation.admin_note || existingLiquidation.adminNote }}
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
          :value="shortfallExplanation"
          @input="(e) => $emit('update:shortfallExplanation', e.target.value)"
          rows="3"
          class="bg-white resize-none input"
          placeholder="Explain why the total expense is less than the advanced amount (required)..."
        />
      </label>
    </section>

    <div class="p-4 mt-2 border border-slate-200 bg-clinical/20 sm:p-5">
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
          <div class="flex items-center justify-between gap-3 text-[11px]">
            <span class="font-bold tracking-tight uppercase text-slate-400"
              >Total Balance:</span
            >
            <span class="font-mono font-bold text-primary">{{
              formatPeso(selectedAdvance.balance ?? selectedAdvance.amount ?? 0)
            }}</span>
          </div>
          <div class="flex items-center justify-between gap-3 text-[11px]">
            <span class="font-bold tracking-tight uppercase text-danger"
              >Total Expenses:</span
            >
            <span class="font-mono font-bold text-danger"
              >-{{ formatPeso(totalExpenseAmount) }}</span
            >
          </div>
          <div
            class="flex items-center justify-between gap-3 pt-2 border-t border-slate-200"
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
            Any excess amount spent beyond the cash advance can be filed as a
            reimbursement. Current excess amount:
            <span class="font-bold text-primary">{{
              formatPeso(overpaymentAmount)
            }}</span
            >.
          </p>
        </div>
        <button
          class="btn btn-cta min-h-[42px] w-full shrink-0 sm:w-fit"
          type="button"
          @click="$emit('forward-overpayment')"
        >
          <Upload class="w-4 h-4" />
          Reimbursement Filing
        </button>
      </div>
    </section>

    <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
      <BaseButton
        v-if="existingLiquidation && existingLiquidation.status === 'pending'"
        id="delete-liquidation-btn"
        variant="danger"
        class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white sm:w-fit"
        :disabled="submitting"
        @click="$emit('delete-liquidation')"
      >
        DELETE SETTLEMENT
      </BaseButton>

      <BaseButton
        id="submit-liquidation-btn"
        variant="cta"
        class="min-h-[42px] w-full sm:w-fit"
        :disabled="
          receipts.length === 0 ||
          hasIncompleteReceiptFields ||
          receipts.some((r) => r.ocrStatus === 'processing') ||
          totalExpenseAmount === 0 ||
          submitting ||
          (variance > 0 && !shortfallExplanation.trim())
        "
        @click="$emit('submit-liquidation')"
      >
        <div v-if="submitting" class="flex items-center gap-2">
          <span
            class="h-3.5 w-3.5 rounded-full border-2 border-current border-t-transparent animate-spin"
            aria-hidden="true"
          />
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
</template>
