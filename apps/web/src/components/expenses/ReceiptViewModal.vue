<script setup>
import { computed } from 'vue';
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
} from 'lucide-vue-next';

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

const emit = defineEmits(['update:modelValue', 'delete']);

function close() {
  emit('update:modelValue', false);
}

// ── Receipt Detail Helpers ────────────────────────────────────────
const MOCK_ITEMS = {
  Lodging: [
    "1 Night – Deluxe Room",
    "Breakfast Buffet (x2)",
    "Airport Transfer",
  ],
  Transportation: ["Grab Ride – NAIA to BGC", "Toll Fee – SLEX", "Parking Fee"],
  Meals: ["Set Meal A (x2)", "Drinks & Dessert", "Service Charge"],
  Supplies: ["Bond Paper (5 reams)", "Ballpens & Markers", "Correction Tape"],
  Uncategorized: ["Miscellaneous Item 1", "Miscellaneous Item 2"],
};

function getMockItems(category) {
  return MOCK_ITEMS[category] || MOCK_ITEMS.Uncategorized;
}

function getVat(amount) {
  return amount > 0 ? (amount * 0.12) / 1.12 : 0;
}
function getSubtotal(amount) {
  return amount > 0 ? amount - getVat(amount) : 0;
}

function formatDate(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(amount);
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="modelValue && receipt"
      class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4 lg:p-8 backdrop-blur-sm"
      @click="close"
    >
      <div
        class="card w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col shadow-2xl"
        @click.stop
      >
        <!-- HEADER -->
        <header
          class="px-6 py-4 flex items-center justify-between sticky top-0 z-20 text-white"
          style="
            background: linear-gradient(135deg, #252578 0%, #2f2f7e 100%);
          "
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
                style="font-family: 'Poppins', sans-serif"
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
              v-if="
                receipt.thumbnail &&
                receipt.fileType !== 'application/pdf'
              "
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
                style="font-family: 'Poppins', sans-serif"
              >
                No Image Preview
              </p>
            </div>
          </div>

          <!-- AI BADGE -->
          <div
            class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-100 rounded-xl"
          >
            <Sparkles class="w-4 h-4 text-emerald-600 fill-emerald-600" />
            <span
              class="text-xs font-semibold text-emerald-700"
              style="font-family: 'Poppins', sans-serif"
              >AI Scanned — Details automatically extracted</span
            >
          </div>

          <!-- DATA GRID -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- ID / Invoice -->
            <div
              class="p-4 rounded-xl border border-slate-100 bg-slate-50/30"
            >
              <p class="section-label mb-1">Receipt ID</p>
              <p class="text-sm font-bold text-slate-800 font-mono">
                {{ receipt.id }}
              </p>
            </div>
            <!-- Category -->
            <div
              class="p-4 rounded-xl border border-slate-100 bg-slate-50/30"
            >
              <p class="section-label mb-2">Category</p>
              <span
                class="badge bg-primary-100 border-primary-200 text-primary-700"
              >
                {{ receipt.category }}
              </span>
            </div>
            <!-- Uploader -->
            <div
              class="p-4 rounded-xl border border-slate-100 bg-slate-50/30"
            >
              <p class="section-label mb-1">Submitted By</p>
              <p class="text-sm font-bold text-slate-800">
                {{ receipt.uploader }}
              </p>
            </div>
            <!-- Date -->
            <div
              class="p-4 rounded-xl border border-slate-100 bg-slate-50/30"
            >
              <p class="section-label mb-1">Transaction Date</p>
              <p class="text-sm font-bold text-slate-800">
                {{ formatDate(receipt.date) }}
              </p>
            </div>
            <!-- Hash / Security -->
            <div
              class="p-4 rounded-xl border border-slate-100 bg-slate-50/30 md:col-span-2"
            >
              <p class="section-label mb-1">SHA-256 Audit Hash</p>
              <p
                class="text-[10px] font-mono text-slate-500 break-all leading-tight"
              >
                {{ receipt.hash }}
              </p>
            </div>
          </div>

          <!-- ITEMS CHECKLIST -->
          <div class="space-y-3">
            <h3
              class="text-sm font-bold text-slate-800 px-1"
              style="font-family: 'Poppins', sans-serif"
            >
              Items / Orders
            </h3>
            <div
              class="border border-slate-100 rounded-xl overflow-hidden bg-white divide-y divide-slate-50"
            >
              <div
                v-for="(item, idx) in getMockItems(receipt.category)"
                :key="idx"
                class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors"
              >
                <CheckCircle2
                  class="w-4 h-4 text-emerald-500 fill-emerald-50"
                />
                <span class="text-sm text-slate-700">{{ item }}</span>
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
                style="font-family: 'Poppins', sans-serif"
              >
                Amount Breakdown
              </h3>
            </div>
            <div class="p-5 space-y-3">
              <div class="flex justify-between items-center text-slate-500">
                <span class="text-sm">Subtotal</span>
                <span class="text-sm font-mono">{{
                  formatCurrency(getSubtotal(receipt.amount))
                }}</span>
              </div>
              <div
                class="flex justify-between items-center text-slate-500 pb-3 border-b border-slate-200"
              >
                <span class="text-sm">Tax (VAT 12%)</span>
                <span class="text-sm font-mono">{{
                  formatCurrency(getVat(receipt.amount))
                }}</span>
              </div>
              <div class="flex justify-between items-center pt-1">
                <span
                  class="text-base font-bold text-primary"
                  style="font-family: 'Poppins', sans-serif"
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
            class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100"
          >
            <div class="flex items-center gap-2 text-slate-400">
              <Clock class="w-4 h-4" />
              <span class="text-[11px] font-semibold uppercase tracking-wider"
                >Processed locally on {{ receipt.date }}</span
              >
            </div>
            <div class="flex gap-2">
              <button class="btn btn-secondary !py-2 !text-xs">
                <Download class="w-3.5 h-3.5" /> Download
              </button>
              <button
                @click="emit('delete', receipt.id); close()"
                class="flex items-center gap-2 px-5 py-2 rounded-lg bg-red-50 text-danger hover:bg-red-100 transition-all text-xs font-bold border border-red-100"
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
