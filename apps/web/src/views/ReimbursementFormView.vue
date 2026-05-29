<script setup>
import { ref, reactive, computed } from "vue";
import { useRouter } from "vue-router";
import { useReimbursementStore } from "@/stores/reimbursement";
import { usePolicyStore } from "@/stores/policy";
import { useAuthStore } from "@/stores/auth";
import { onMounted } from "vue";
import {
  ArrowLeft,
  UploadCloud,
  PlusCircle,
  AlertTriangle,
  Sparkles,
  Calendar,
  MapPin,
  ChevronDown,
  Trash2,
  FileText,
  Image as ImageIcon,
  Send,
  Info,
  PackageCheck,
  Activity,
  Save,
  CheckCircle2,
  X,
} from "lucide-vue-next";

// ── Props & Emits ─────────────────────────────────────────────────
const props = defineProps({
  forwardedReceipts: {
    type: Array,
    default: () => [],
  },
});
const emit = defineEmits(["submitted", "close"]);

// ── Stores ────────────────────────────────────────────────────────
const store = useReimbursementStore();
const policyStore = usePolicyStore();
const authStore = useAuthStore();
const router = useRouter();

onMounted(() => {
  policyStore.fetchAll();
});

// ── Local state per receipt (editable copies so form is mutable) ──
const receipts = computed(() => {
  if (props.forwardedReceipts.length > 0) return props.forwardedReceipts;
  // Standalone page fallback — single empty receipt
  return [];
});

// ── Form State ────────────────────────────────────────────────────
const submitting = ref(false);
const submitted = ref(false);
const cutoffPeriod = ref("");
const reportFile = ref(null);
const reportDrag = ref(false);
const reportInput = ref(null);

// ── Financials (aggregate across all forwarded receipts) ──────────
const totalAmount = computed(() =>
  receipts.value.reduce((sum, r) => sum + (Number(r.amount) || 0), 0),
);
const totalVat = computed(() =>
  receipts.value.reduce(
    (sum, r) =>
      sum + (Number(r.amount) > 0 ? (Number(r.amount) * 0.12) / 1.12 : 0),
    0,
  ),
);
const totalSubtotal = computed(() => totalAmount.value - totalVat.value);

function vatOf(amount) {
  return amount > 0 ? (amount * 0.12) / 1.12 : 0;
}
function subtotalOf(amount) {
  return amount > 0 ? amount - vatOf(amount) : 0;
}

function formatCurrency(v) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(v);
}
function formatDate(d) {
  if (!d) return "—";
  const dt = new Date(d);
  if (isNaN(dt)) return d;
  return dt.toLocaleDateString("en-US", {
    month: "2-digit",
    day: "2-digit",
    year: "numeric",
  });
}
function cleanName(fileName) {
  return (fileName || "").replace(/\.[^.]+$/, "").replace(/[_-]/g, " ");
}

// ── Cutoff options ────────────────────────────────────────────────
const CUTOFF_OPTIONS = [
  "Jan 01 – Jan 15, 2025",
  "Jan 16 – Jan 31, 2025",
  "Feb 01 – Feb 15, 2025",
  "Feb 16 – Feb 28, 2025",
  "Mar 01 – Mar 15, 2025",
  "Mar 16 – Mar 31, 2025",
];

// ── Categories ────────────────────────────────────────────────────
const CATEGORIES = [
  "Food & Dining",
  "Transportation",
  "Lodging",
  "Supplies",
  "Entertainment",
  "Utilities",
  "Other",
];

// ── Mock extracted items per category ────────────────────────────
const MOCK_ITEMS = {
  Lodging: [
    "1 Night – Deluxe Room",
    "Breakfast Buffet (x2)",
    "Airport Transfer",
  ],
  Transportation: [
    "GrabCar Ride – Terminal to CBD",
    "Toll Fee – SLEX",
    "Parking Fee",
  ],
  Meals: ["Set Meal A (x2)", "Drinks & Dessert", "Service Charge"],
  Supplies: ["Bond Paper (5 reams)", "Ballpens & Markers", "Correction Tape"],
  Uncategorized: ["Item 1", "Item 2"],
};
function getItems(cat) {
  return MOCK_ITEMS[cat] || MOCK_ITEMS.Uncategorized;
}

// ── Validation ────────────────────────────────────────────────────
const canProceed = computed(
  () => receipts.value.length >= 2 && cutoffPeriod.value,
);

// ── Submit ────────────────────────────────────────────────────────
async function handleSubmit() {
  if (!canProceed.value) return;
  submitting.value = true;
  try {
    await store.submit({
      description: receipts.value.map((r) => cleanName(r.fileName)).join(", "),
      category: receipts.value[0]?.category || "General",
      amount: totalAmount.value,
      vat: totalVat.value,
      tin: "—",
      notes: "",
      receipts: receipts.value,
      status: "submitted",
      submittedBy: authStore.user?.name || "Employee",
    });
    submitted.value = true;
    emit("submitted");
  } finally {
    submitting.value = false;
  }
}

// ── Attachment ────────────────────────────────────────────────────
function handleReportDrop(e) {
  reportDrag.value = false;
  const file = e.dataTransfer.files[0];
  if (file) reportFile.value = file;
}
function handleReportSelect(e) {
  const file = e.target.files[0];
  if (file) reportFile.value = file;
}

// ── Dismiss ───────────────────────────────────────────────────────
function dismiss() {
  emit("close");
  // If opened standalone (via route), go back
  if (!props.forwardedReceipts.length) router.back();
}
</script>

<template>
  <div class="max-w-5xl mx-auto flex flex-col gap-6 pb-12 animate-fade-up">
    <!-- ── Page Header (standalone route mode only) ── -->
    <div v-if="!forwardedReceipts.length" class="flex items-center gap-3">
      <button
        @click="dismiss"
        class="w-9 h-9 rounded-full bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 hover:text-primary shadow-sm text-slate-500 transition-all"
      >
        <ArrowLeft class="w-4 h-4" />
      </button>
      <div>
        <h1
          class="text-2xl font-bold text-primary leading-tight"
          style="
            font-family: &quot;Poppins&quot;, sans-serif;
            letter-spacing: -0.02em;
          "
        >
          New Reimbursement
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">
          Submit your expense reimbursement request
        </p>
      </div>
    </div>

    <!-- ── Success State ── -->
    <div
      v-if="submitted"
      class="card p-16 flex flex-col items-center gap-5 text-center"
    >
      <div
        class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center"
      >
        <PackageCheck class="w-8 h-8 text-emerald-500" />
      </div>
      <div>
        <h2
          class="text-lg font-bold text-slate-800 mb-1"
          style="font-family: &quot;Poppins&quot;, sans-serif"
        >
          Reimbursement Submitted!
        </h2>
        <p class="text-sm text-slate-400">
          Your request has been sent for review.
        </p>
      </div>
      <div class="flex gap-3">
        <button class="btn btn-primary" @click="router.push('/reimbursements')">
          View My Claims
        </button>
        <button class="btn btn-secondary" @click="submitted = false">
          Submit Another
        </button>
      </div>
    </div>

    <template v-else>
      <!-- ── Alert Banner (forwarded mode) ── -->
      <div
        v-if="forwardedReceipts.length"
        class="flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-100 rounded-xl"
      >
        <Send class="w-4 h-4 text-emerald-600 flex-shrink-0" />
        <p class="text-sm font-semibold text-emerald-700">
          {{ forwardedReceipts.length }} receipt{{
            forwardedReceipts.length !== 1 ? "s" : ""
          }}
          forwarded from My Expense and pre-filled below.
        </p>
      </div>

      <!-- ── Empty State (standalone + no upload yet) ── -->
      <div
        v-if="receipts.length === 0"
        class="card p-16 flex flex-col items-center gap-4 border-2 border-dashed border-slate-200 text-center"
      >
        <div
          class="w-14 h-14 rounded-2xl bg-accent/10 flex items-center justify-center"
        >
          <UploadCloud class="w-7 h-7 text-accent" />
        </div>
        <div>
          <p
            class="text-sm font-semibold text-slate-700"
            style="font-family: &quot;Poppins&quot;, sans-serif"
          >
            Select receipts in My Expense first
          </p>
          <p class="text-xs text-slate-400 mt-1">
            Go back to My Expense, click receipt cards to select them,<br />then
            click "Forward to Reimbursement".
          </p>
        </div>
        <button class="btn btn-secondary mt-2" @click="dismiss">
          <ArrowLeft class="w-4 h-4" /> Go Back
        </button>
      </div>

      <template v-else>
        <!-- ── CARD 1: Upload Receipt Management ── -->
        <section class="card p-6">
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <h2
                class="text-lg font-bold text-primary"
                style="font-family: &quot;Poppins&quot;, sans-serif"
              >
                Forwarded Receipts
              </h2>
              <span
                class="badge text-[11px] font-bold"
                :class="
                  receipts.length >= 2
                    ? 'bg-emerald-50 border-emerald-100 text-emerald-700'
                    : 'bg-red-50 border-red-100 text-danger'
                "
              >
                {{ receipts.length }} receipt{{
                  receipts.length !== 1 ? "s" : ""
                }}
              </span>
            </div>
            <div
              class="flex items-center gap-1.5 text-emerald-600 text-xs font-semibold"
            >
              <Info class="w-3.5 h-3.5" />
              <span>AI reads everything automatically</span>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <button
              class="btn !bg-emerald-500 !text-white hover:!bg-emerald-600 !rounded-full !px-6"
            >
              <PlusCircle class="w-4 h-4" /> Add More Receipts
            </button>
            <div
              v-if="receipts.length < 2"
              class="flex items-center gap-2 text-danger text-sm font-semibold"
            >
              <AlertTriangle class="w-4 h-4" />
              You need at least 2 receipts to proceed
            </div>
            <div
              v-else
              class="flex items-center gap-2 text-emerald-600 text-sm font-semibold"
            >
              <CheckCircle2 class="w-4 h-4" /> Ready to submit
            </div>
          </div>
        </section>

        <!-- ── CARD 2: One Scanned Receipt Block Per Receipt ── -->
        <section class="card p-6">
          <div class="flex items-center justify-between mb-5">
            <h2
              class="text-lg font-bold text-primary"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              Scanned Receipts
            </h2>
            <div class="flex items-center gap-4">
              <span
                class="text-xs font-bold"
                :class="
                  receipts.length < 2 ? 'text-danger' : 'text-emerald-600'
                "
              >
                {{ receipts.length }} uploaded{{
                  receipts.length < 2
                    ? " — need at least 2 to proceed"
                    : " — ready!"
                }}
              </span>
              <button
                class="btn btn-secondary !py-1.5 !text-xs !border-primary/30 !text-primary"
              >
                <PlusCircle class="w-3.5 h-3.5" /> Add More
              </button>
            </div>
          </div>

          <!-- One block per receipt -->
          <div class="flex flex-col gap-8">
            <div
              v-for="(receipt, idx) in receipts"
              :key="receipt.id"
              class="border border-emerald-100 rounded-xl p-6 bg-emerald-50/20"
            >
              <!-- Receipt number badge -->
              <div class="flex items-center gap-2 mb-4">
                <span
                  class="w-6 h-6 rounded-full bg-primary text-white text-[11px] font-bold flex items-center justify-center"
                  >{{ idx + 1 }}</span
                >
                <span
                  class="text-xs font-bold text-primary"
                  style="font-family: &quot;Poppins&quot;, sans-serif"
                  >{{ cleanName(receipt.fileName) }}</span
                >
                <span class="ml-auto text-[10px] font-mono text-slate-400">{{
                  receipt.id
                }}</span>
              </div>

              <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left: Image Preview -->
                <div class="lg:col-span-4 flex flex-col gap-4">
                  <div
                    class="aspect-[3/4] rounded-xl overflow-hidden border border-slate-200 bg-white shadow-sm flex items-center justify-center"
                  >
                    <img
                      v-if="receipt.thumbnail"
                      :src="receipt.thumbnail"
                      :alt="receipt.fileName"
                      class="w-full h-full object-contain"
                    />
                    <div
                      v-else
                      class="flex flex-col items-center gap-2 text-slate-300"
                    >
                      <FileText
                        v-if="
                          receipt.fileType === 'pdf' ||
                          receipt.fileType === 'application/pdf'
                        "
                        class="w-12 h-12 opacity-40"
                      />
                      <ImageIcon v-else class="w-12 h-12 opacity-40" />
                      <p
                        class="text-[10px] font-semibold uppercase tracking-widest"
                        style="font-family: &quot;Poppins&quot;, sans-serif"
                      >
                        No Preview
                      </p>
                    </div>
                  </div>
                  <button class="btn btn-danger w-full !justify-center">
                    <Trash2 class="w-4 h-4" /> Delete Receipt
                  </button>
                </div>

                <!-- Right: Extracted Fields -->
                <div class="lg:col-span-8 flex flex-col gap-4">
                  <!-- Row 1: Invoice + Date -->
                  <div class="grid grid-cols-2 gap-4">
                    <div class="input-wrapper">
                      <label class="input-label">Invoice Number</label>
                      <input
                        class="input"
                        type="text"
                        :value="receipt.id"
                        readonly
                      />
                    </div>
                    <div class="input-wrapper">
                      <label class="input-label">Date</label>
                      <div class="relative">
                        <input
                          class="input pr-10"
                          type="text"
                          :value="receipt.date"
                          readonly
                        />
                        <Calendar
                          class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Merchant -->
                  <div class="input-wrapper">
                    <label class="input-label">Merchant Name</label>
                    <input
                      class="input"
                      type="text"
                      :value="cleanName(receipt.fileName)"
                      readonly
                    />
                  </div>

                  <!-- Location -->
                  <div class="input-wrapper">
                    <label class="input-label">Location</label>
                    <div class="relative">
                      <input
                        class="input pr-10"
                        type="text"
                        value="Metro Manila, Philippines"
                        readonly
                      />
                      <MapPin
                        class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                      />
                    </div>
                  </div>

                  <!-- Category with AI badge -->
                  <div class="input-wrapper">
                    <label class="input-label"
                      >Category (AI Auto-Detected)</label
                    >
                    <div class="flex gap-2">
                      <div class="relative flex-1">
                        <select class="input appearance-none cursor-pointer">
                          <option
                            v-for="cat in CATEGORIES"
                            :key="cat"
                            :selected="
                              cat === receipt.category ||
                              (receipt.category === 'Meals' &&
                                cat === 'Food & Dining')
                            "
                          >
                            {{ cat }}
                          </option>
                        </select>
                        <ChevronDown
                          class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                        />
                      </div>
                      <span
                        class="bg-emerald-500 text-white px-3 py-2 rounded-lg text-[11px] font-bold flex items-center gap-1.5 flex-shrink-0"
                      >
                        <Sparkles class="w-3 h-3 fill-white" /> AI Detected
                      </span>
                    </div>
                  </div>

                  <!-- Order Items -->
                  <div class="input-wrapper">
                    <label class="input-label"
                      >Order Items (AI Extracted)</label
                    >
                    <div
                      class="border border-slate-100 rounded-lg overflow-hidden shadow-sm bg-white"
                    >
                      <table class="w-full text-left border-collapse">
                        <thead
                          class="bg-slate-50 text-[11px] text-slate-500 uppercase"
                        >
                          <tr>
                            <th class="px-4 py-2.5 font-bold">Item</th>
                            <th class="px-4 py-2.5 font-bold text-center">
                              Qty
                            </th>
                            <th class="px-4 py-2.5 font-bold text-right">
                              Price
                            </th>
                          </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-50">
                          <tr
                            v-for="item in getItems(receipt.category)"
                            :key="item"
                          >
                            <td class="px-4 py-3 text-slate-700 font-medium">
                              {{ item }}
                            </td>
                            <td class="px-4 py-3 text-center text-slate-500">
                              1
                            </td>
                            <td
                              class="px-4 py-3 text-right text-primary font-bold font-mono"
                            >
                              {{
                                formatCurrency(
                                  receipt.amount /
                                    getItems(receipt.category).length,
                                )
                              }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- Financials for this receipt -->
                  <div
                    class="flex items-end justify-between gap-4 pt-2 border-t border-slate-100"
                  >
                    <div class="flex gap-4">
                      <div class="input-wrapper">
                        <label class="input-label">Subtotal</label>
                        <input
                          class="input !w-36 !bg-slate-50"
                          readonly
                          type="text"
                          :value="subtotalOf(receipt.amount).toFixed(2)"
                        />
                      </div>
                      <div class="input-wrapper">
                        <label class="input-label">Tax (VAT 12%)</label>
                        <input
                          class="input !w-36 !bg-slate-50"
                          readonly
                          type="text"
                          :value="vatOf(receipt.amount).toFixed(2)"
                        />
                      </div>
                    </div>
                    <div
                      class="bg-emerald-50 px-6 py-3 rounded-xl border border-emerald-100 flex flex-col items-end shadow-sm"
                    >
                      <span
                        class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider"
                        >Total</span
                      >
                      <span
                        class="text-xl font-black text-emerald-600 font-mono"
                        >{{ formatCurrency(receipt.amount) }}</span
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- ── CARD 3: Meta & Attachments ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Cutoff Period -->
          <section class="card p-6 flex flex-col gap-4">
            <div>
              <h3
                class="text-base font-bold text-primary mb-1"
                style="font-family: &quot;Poppins&quot;, sans-serif"
              >
                Cutoff Period <span class="text-danger">*</span>
              </h3>
              <div class="relative">
                <select
                  v-model="cutoffPeriod"
                  class="input appearance-none cursor-pointer"
                >
                  <option value="" disabled>Select cutoff period</option>
                  <option v-for="opt in CUTOFF_OPTIONS" :key="opt" :value="opt">
                    {{ opt }}
                  </option>
                </select>
                <ChevronDown
                  class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                />
              </div>
              <p class="text-[11px] text-slate-400 mt-2">
                You can submit one reimbursement per cutoff period.
              </p>
            </div>
          </section>

          <!-- Report Attachment -->
          <section class="card p-6 flex flex-col gap-4">
            <h3
              class="text-base font-bold text-primary"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              Report Attachment
              <span class="text-slate-400 font-normal text-sm">(optional)</span>
            </h3>
            <div
              class="border-2 border-dashed rounded-xl p-5 flex items-center justify-between transition-all cursor-pointer"
              :class="
                reportDrag
                  ? 'border-accent bg-accent/5'
                  : 'border-slate-200 hover:border-primary/30 bg-slate-50/40'
              "
              @dragover.prevent="reportDrag = true"
              @dragleave.prevent="reportDrag = false"
              @drop.prevent="handleReportDrop"
            >
              <div class="flex items-center gap-4">
                <div
                  class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center flex-shrink-0"
                >
                  <FileText class="w-5 h-5 text-slate-400" />
                </div>
                <div>
                  <p
                    class="text-sm font-semibold text-slate-700"
                    style="font-family: &quot;Poppins&quot;, sans-serif"
                  >
                    {{ reportFile ? reportFile.name : "No file selected" }}
                  </p>
                  <p class="text-[11px] text-slate-400">
                    Upload activity report (PDF, DOC, DOCX)
                  </p>
                </div>
              </div>
              <button
                class="btn btn-secondary !py-1.5 !px-5 !text-xs flex-shrink-0"
                @click="reportInput?.click()"
              >
                Browse
              </button>
              <input
                ref="reportInput"
                type="file"
                class="hidden"
                accept=".pdf,.doc,.docx"
                @change="handleReportSelect"
              />
            </div>
          </section>
        </div>

        <!-- ── Summary Panel ── -->
        <section
          class="bg-emerald-50 border border-emerald-100 rounded-xl p-6 space-y-4"
        >
          <h3
            class="text-base font-bold text-primary"
            style="font-family: &quot;Poppins&quot;, sans-serif"
          >
            Summary
          </h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-500">Uploaded Receipts</span>
              <span
                class="font-bold"
                :class="
                  receipts.length < 2 ? 'text-danger' : 'text-emerald-600'
                "
              >
                {{ receipts.length
                }}{{ receipts.length < 2 ? " (need at least 2)" : " ✓" }}
              </span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-500">Report Attached</span>
              <span class="text-slate-400">{{
                reportFile ? reportFile.name : "None"
              }}</span>
            </div>
            <div
              class="flex justify-between items-center text-sm pb-4 border-b border-emerald-200"
            >
              <span class="text-slate-500">Cutoff Period</span>
              <span
                :class="
                  cutoffPeriod
                    ? 'text-slate-700 font-semibold'
                    : 'text-danger font-bold'
                "
              >
                {{ cutoffPeriod || "Not selected" }}
              </span>
            </div>
            <div class="flex justify-between items-center pt-2">
              <span
                class="text-base font-bold text-primary"
                style="font-family: &quot;Poppins&quot;, sans-serif"
                >Total Amount</span
              >
              <span class="text-2xl font-black text-emerald-600 font-mono">{{
                formatCurrency(totalAmount)
              }}</span>
            </div>
          </div>
        </section>

        <!-- ── Footer Actions ── -->
        <div class="flex justify-end gap-4 pb-4">
          <button class="btn btn-secondary !px-8" @click="dismiss">
            Cancel
          </button>
          <button
            class="btn !px-10 transition-all duration-200"
            :class="
              canProceed
                ? 'btn-primary'
                : 'bg-slate-200 text-slate-400 cursor-not-allowed opacity-70'
            "
            :disabled="!canProceed || submitting"
            @click="handleSubmit"
          >
            <Activity v-if="submitting" class="w-4 h-4 animate-spin" />
            <Save v-else class="w-4 h-4" />
            {{ submitting ? "Submitting..." : "Submit Reimbursement" }}
          </button>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s linear;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
