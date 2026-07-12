<script setup>
import { computed } from "vue";
import {
  Receipt,
  X,
  FileText,
  Image as ImageIcon,
  Sparkles,
  CheckCircle2,
  Clock,
  Download,
  Trash2,
  Pencil,
  AlertTriangle,
} from "lucide-vue-next";
import { formatPeso as formatCurrency, formatDate as formatDateBase } from "@/utils/formatters";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  receipt: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue", "delete", "edit"]);

const canEdit = computed(
  () => String(props.receipt?.status || "").toLowerCase() === "processed",
);
const canDelete = computed(() =>
  ["processed", "rejected"].includes(
    String(props.receipt?.status || "").toLowerCase(),
  ),
);

function close() {
  emit("update:modelValue", false);
}

function editReceipt() {
  emit("edit", props.receipt);
  close();
}

// ── Receipt Detail Helpers ────────────────────────────────────────
const actualSubtotal = computed(() => {
  if (props.receipt?.items?.length) {
    return props.receipt.items.reduce(
      (sum, item) => sum + (Number(item.price) * Number(item.quantity) || 0),
      0,
    );
  }
  return props.receipt
    ? props.receipt.amount - (props.receipt.vatAmount || 0)
    : 0;
});

function formatDate(dateStr) {
  return formatDateBase(dateStr, {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="modelValue && receipt"
      class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4 lg:p-8 backdrop-blur-[1px]"
      @click="close"
    >
      <div
        class="card border-none w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col shadow-2xl"
        @click.stop
      >
        <!-- HEADER -->
        <header
          class="px-6 py-4 flex items-center justify-between sticky top-0 z-20 bg-primary text-white"
        >
          <div class="flex items-center gap-4">
            <div
              class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center"
            >
              <Receipt class="w-5 h-5 text-white" />
            </div>
            <div>
              <h2
                class="text-lg font-bold leading-tight"
                style="font-family: &quot;Poppins&quot;, sans-serif"
              >
                Receipt Details
              </h2>
              <p class="text-xs text-white/70">
                {{ receipt.fileName }}
              </p>
            </div>
          </div>
          <button
            @click="close"
            class="w-10 h-10 rounded-full hover:bg-white/10 flex items-center justify-center transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </header>

        <div class="overflow-y-auto p-6 space-y-6">
          <!-- Receipt Image Preview -->
          <div
            class="relative w-full aspect-[21/9] rounded-xl overflow-hidden border border-slate-100 bg-slate-50 group"
          >
            <img
              v-if="receipt.thumbnail && receipt.fileType !== 'application/pdf'"
              :src="receipt.thumbnail"
              class="w-full h-full object-cover opacity-80"
            />
            <div
              v-else
              class="w-full h-full flex flex-col items-center justify-center gap-2 text-slate-300"
            >
              <FileText
                v-if="
                  receipt.fileType === 'application/pdf' ||
                  receipt.fileType === 'pdf'
                "
                class="w-12 h-12 opacity-50"
              />
              <ImageIcon v-else class="w-12 h-12 opacity-50" />
              <p
                class="text-xs font-semibold uppercase tracking-widest"
                style="font-family: &quot;Poppins&quot;, sans-serif"
              >
                No Image Preview
              </p>
            </div>
          </div>

          <!-- AI BADGE -->
          <div
            v-if="receipt.status === 'automatic-rejected'"
            class="rounded-xl border border-danger/20 bg-danger/5 p-4"
          >
            <div class="flex items-start gap-3">
              <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-danger" />
              <div>
                <p class="font-heading text-sm font-bold text-danger">
                  Automatic Rejected
                </p>
                <p class="mt-1 text-sm text-slate-600">
                  {{ receipt.complianceReason || "System validation could not approve this receipt." }}
                </p>
              </div>
            </div>
          </div>

          <div
            class="flex items-center gap-2 px-4 py-2.5 bg-accent-50 border border-accent/20 rounded-xl"
          >
            <Sparkles class="w-4 h-4 text-accent fill-accent" />
            <span
              class="text-xs font-semibold text-accent"
              style="font-family: &quot;Poppins&quot;, sans-serif"
              >AI Scanned — Details automatically extracted</span
            >
          </div>

          <!-- DATA GRID -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Date -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Transaction Date</p>
              <p class="text-sm font-bold text-slate-800">
                {{ formatDate(receipt.date) }}
              </p>
            </div>
            <!-- Vendor Name -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Vendor Name</p>
              <p class="text-sm font-bold text-slate-800">
                {{ receipt.vendorName || "—" }}
              </p>
            </div>
            <!-- Category -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-2">Category</p>
              <span
                class="badge bg-primary-100 border-primary-200 text-primary-700"
              >
                {{ receipt.category }}
              </span>
            </div>
            <!-- TIN -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">TIN</p>
              <p class="text-sm font-bold text-slate-800 font-mono">
                {{ receipt.tin || "—" }}
              </p>
            </div>
            <!-- Invoice Number -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Invoice Number</p>
              <p class="text-sm font-bold text-slate-800 font-mono">
                {{ receipt.invoiceNumber || "—" }}
              </p>
            </div>
            <!-- VAT Classification -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">VAT Classification</p>
              <p class="text-sm font-bold text-slate-800 uppercase">
                {{ receipt.vatClassification || "—" }}
              </p>
            </div>
            <!-- Uploader -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Submitted By</p>
              <p class="text-sm font-bold text-slate-800">
                {{ receipt.uploader }}
              </p>
            </div>
          </div>

          <!-- ITEMS CHECKLIST -->
          <div
            class="space-y-3"
            v-if="receipt.items && receipt.items.length > 0"
          >
            <h3
              class="text-sm font-bold text-slate-800 px-1"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              Expense Breakdown
            </h3>
            <div
              class="border border-slate-100 rounded-xl overflow-hidden bg-white divide-y divide-slate-50"
            >
              <div
                v-for="item in receipt.items"
                :key="item.id"
                class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50 transition-colors"
              >
                <div class="flex items-center gap-3">
                  <CheckCircle2
                    class="w-4 h-4 text-accent fill-accent-50"
                  />
                  <span class="text-sm text-slate-700">{{
                    item.name || "Unnamed Item"
                  }}</span>
                </div>
                <div class="text-sm text-slate-500 font-mono">
                  {{ item.quantity }} x
                  {{ formatCurrency(Number(item.price) || 0) }}
                </div>
              </div>
            </div>
          </div>

          <!-- AMOUNT BREAKDOWN -->
          <div
            class="rounded-xl border border-slate-100 overflow-hidden bg-slate-50/50"
          >
            <div class="bg-primary px-5 py-3">
              <h3
                class="text-xs font-bold text-white uppercase tracking-widest"
                style="font-family: &quot;Poppins&quot;, sans-serif"
              >
                Amount Breakdown
              </h3>
            </div>
            <div class="p-5 space-y-3">
              <div class="flex justify-between items-center text-slate-500">
                <span class="text-sm">Items Subtotal</span>
                <span class="text-sm font-mono">{{
                  formatCurrency(actualSubtotal)
                }}</span>
              </div>
              <div
                class="flex justify-between items-center text-slate-500 pb-3 border-b border-slate-200"
              >
                <span class="text-sm">VAT Amount</span>
                <span class="text-sm font-mono">{{
                  formatCurrency(receipt.vatAmount || 0)
                }}</span>
              </div>
              <div class="flex justify-between items-center pt-1">
                <span
                  class="text-base font-bold text-primary"
                  :style="{ fontFamily: '\'Poppins\', sans-serif' }"
                  >Total Amount</span
                >
                <span class="text-xl font-black text-primary font-mono">{{
                  formatCurrency(receipt.amount)
                }}</span>
              </div>
            </div>
          </div>

          <!-- FOOTER -->
          <div
            class="flex flex-col md:flex-row items-center justify-between md:justify-end gap-4 pt-4 border-t border-slate-100"
          >
            <div class="flex gap-2">
              <button
                v-if="canEdit"
                class="btn btn-cta"
                @click="editReceipt"
              >
                <Pencil class="w-3.5 h-3.5" /> Edit Receipt
              </button>
              <button class="btn btn-secondary !py-2 !text-xs">
                <Download class="w-3.5 h-3.5" /> Download
              </button>
              <button
                v-if="canDelete"
                @click="
                  emit('delete', receipt.id);
                  close();
                "
                class="inline-flex h-9 w-fit items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3.5 text-xs font-bold text-danger transition-colors hover:bg-red-100"
              >
                <Trash2 class="w-3.5 h-3.5" />
                Delete Receipt
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
