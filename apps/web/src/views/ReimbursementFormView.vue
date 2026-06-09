<script setup>
import { ref, computed, onBeforeUnmount } from "vue";
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

// â”€â”€ Props & Emits â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const props = defineProps({
  forwardedReceipts: {
    type: Array,
    default: () => [],
  },
});
const emit = defineEmits(["submitted", "close"]);

// â”€â”€ Stores â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const store = useReimbursementStore();
const policyStore = usePolicyStore();
const authStore = useAuthStore();
const router = useRouter();

onMounted(() => {
  policyStore.fetchAll();
  const forwarded = sessionStorage.getItem("serms_forwarded_liquidation_receipts");
  if (forwarded) {
    try {
      localReceipts.value = JSON.parse(forwarded);
    } catch {
      localReceipts.value = [];
    } finally {
      sessionStorage.removeItem(serms_forwarded_liquidation_receipts);
    }
  }
});

// â”€â”€ Form State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const submitting = ref(false);
const submitted = ref(false);
const cutoffPeriod = ref("");
const reportFile = ref(null);
const reportDrag = ref(false);
const reportInput = ref(null);
const localReceipts = ref([]);
const receiptDrag = ref(false);
const receiptInput = ref(null);

const receipts = computed(() => [
  ...props.forwardedReceipts,
  ...localReceipts.value,
]);

// â”€â”€ Financials (aggregate across all forwarded receipts) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
  if (!d) return "-";
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

function tinFor(receipt) {
  const seed = String(receipt.id || receipt.fileName || "").replace(/\D/g, "").padEnd(9, "0");
  return `${seed.slice(0, 3)}-${seed.slice(3, 6)}-${seed.slice(6, 9)}-000`;
}

// â”€â”€ Cutoff options â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const CUTOFF_OPTIONS = [
  "Jan 01 - Jan 15, 2025",
  "Jan 16 - Jan 31, 2025",
  "Feb 01 - Feb 15, 2025",
  "Feb 16 - Feb 28, 2025",
  "Mar 01 - Mar 15, 2025",
  "Mar 16 - Mar 31, 2025",
];

// â”€â”€ Categories â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const CATEGORIES = [
  "Food & Dining",
  "Transportation",
  "Lodging",
  "Supplies",
  "Entertainment",
  "Utilities",
  "Other",
];

// â”€â”€ Mock extracted items per category â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const MOCK_ITEMS = {
  "Food & Dining": ["Chickenjoy 2pc Meal", "Yumburger w/ Cheese", "Iced Tea"],
  Lodging: [
    "1 Night - Deluxe Room",
    "Breakfast Buffet (x2)",
    "Airport Transfer",
  ],
  Transportation: [
    "GrabCar Ride - Terminal to CBD",
    "Toll Fee - SLEX",
    "Parking Fee",
  ],
  Meals: ["Set Meal A (x2)", "Drinks & Dessert", "Service Charge"],
  Supplies: ["Bond Paper (5 reams)", "Ballpens & Markers", "Correction Tape"],
  Uncategorized: ["Item 1", "Item 2"],
};
function getItems(cat) {
  return MOCK_ITEMS[cat] || MOCK_ITEMS.Uncategorized;
}

function mockReceiptAmount(index) {
  return [439.04, 3200, 875.5, 1240][index % 4];
}

// â”€â”€ Validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const canProceed = computed(
  () => receipts.value.length >= 2 && cutoffPeriod.value,
);

// â”€â”€ Submit â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
async function handleSubmit() {
  if (!canProceed.value) return;
  submitting.value = true;
  try {
    await store.submit({
      description: receipts.value.map((r) => cleanName(r.fileName)).join(", "),
      category: receipts.value[0]?.category || "General",
      amount: totalAmount.value,
      vat: totalVat.value,
      tin: "-",
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

// â”€â”€ Attachment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function handleReportDrop(e) {
  reportDrag.value = false;
  const file = e.dataTransfer.files[0];
  if (file) reportFile.value = file;
}
function handleReportSelect(e) {
  const file = e.target.files[0];
  if (file) reportFile.value = file;
}

function handleReceiptDrop(e) {
  receiptDrag.value = false;
  addReceiptFiles(e.dataTransfer.files);
}

function handleReceiptSelect(e) {
  addReceiptFiles(e.target.files);
  e.target.value = "";
}

function addReceiptFiles(fileList) {
  const accepted = Array.from(fileList || []).filter((file) =>
    ["image/jpeg", "image/png", "application/pdf"].includes(file.type),
  );

  accepted.forEach((file, index) => {
    const receiptIndex =
      props.forwardedReceipts.length + localReceipts.value.length + index;
    localReceipts.value.push({
      id: `RCPT-${Date.now()}-${index + 1}`,
      fileName: file.name,
      fileType: file.type,
      thumbnail: file.type.startsWith("image/") ? URL.createObjectURL(file) : "",
      amount: mockReceiptAmount(receiptIndex),
      date: new Date().toISOString().slice(0, 10),
      category: "Food & Dining",
      sourceFile: file,
    });
  });
}

function removeReceipt(receipt) {
  if (receipt.thumbnail?.startsWith("blob:")) {
    URL.revokeObjectURL(receipt.thumbnail);
  }
  localReceipts.value = localReceipts.value.filter((item) => item.id !== receipt.id);
}

onBeforeUnmount(() => {
  localReceipts.value.forEach((receipt) => {
    if (receipt.thumbnail?.startsWith("blob:")) {
      URL.revokeObjectURL(receipt.thumbnail);
    }
  });
});

// â”€â”€ Dismiss â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function dismiss() {
  emit("close");
  // If opened standalone (via route), go back
  if (!props.forwardedReceipts.length) router.back();
}

function viewMyClaims() {
  emit("close");
  router.push({ name: "Reimbursements" });
}
</script>

<template>
  <div class="max-w-5xl mx-auto flex flex-col gap-6 pb-12 animate-fade-up">
    <input
      ref="receiptInput"
      type="file"
      class="hidden"
      accept=".jpg,.jpeg,.png,.pdf"
      multiple
      @change="handleReceiptSelect"
    />

    <!-- â”€â”€ Page Header (standalone route mode only) â”€â”€ -->
    <div v-if="!forwardedReceipts.length" class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="flex items-start gap-3">
        <button
          @click="dismiss"
          class="w-9 h-9 rounded-full bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 hover:text-primary shadow-sm text-slate-500 transition-all"
        >
          <ArrowLeft class="w-4 h-4" />
        </button>
        <div class="min-w-0">
          <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
            New Reimbursement
          </h1>
          <p class="mt-1 text-sm text-slate-400">
            Submit your expense reimbursement request
          </p>
        </div>
      </div>
    </div>

    <!-- â”€â”€ Success State â”€â”€ -->
    <div
      v-if="submitted"
      class="card p-16 flex flex-col items-center gap-5 text-center"
    >
      <div
        class="w-16 h-16 rounded-2xl bg-accent-50 border border-accent/20 flex items-center justify-center"
      >
        <PackageCheck class="w-8 h-8 text-accent" />
      </div>
      <div>
        <h2
          class="text-lg font-bold text-slate-800 mb-1"
          style="font-family: 'Poppins', sans-serif"
        >
          Reimbursement Submitted!
        </h2>
        <p class="text-sm text-slate-400">
          Your request has been sent for review.
        </p>
      </div>
      <div class="mt-2 flex w-full max-w-sm flex-col-reverse gap-3 sm:flex-row sm:justify-center">
        <button
          class="inline-flex h-10 flex-1 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-50"
          type="button"
          @click="submitted = false"
        >
          Submit Another
        </button>
        <button
          class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-accent px-4 text-sm font-bold text-white shadow-sm transition-colors hover:bg-accent-600"
          type="button"
          @click="viewMyClaims"
        >
          View My Claims
        </button>
      </div>
    </div>

    <template v-else>
      <!-- â”€â”€ Alert Banner (forwarded mode) â”€â”€ -->
      <div
        v-if="forwardedReceipts.length"
        class="flex items-center gap-3 px-4 py-3 bg-accent-50 border border-accent/15 rounded-xl"
      >
        <Send class="w-4 h-4 text-accent flex-shrink-0" />
        <p class="text-sm font-semibold text-accent">
          {{ forwardedReceipts.length }} receipt{{
            forwardedReceipts.length !== 1 ? "s" : ""
          }}
          forwarded from My Expense and pre-filled below.
        </p>
      </div>

      <!-- â”€â”€ Empty State (standalone + no upload yet) â”€â”€ -->
      <section
        v-if="receipts.length === 0"
        class="card p-6"
      >
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <h2 class="font-heading text-lg font-bold text-primary">Upload Receipts</h2>
          <div class="flex items-center gap-2 text-xs font-bold text-accent">
            <Sparkles class="h-4 w-4" />
            <span>Upload your receipt - AI reads everything automatically</span>
          </div>
        </div>
        <div
          class="flex min-h-[320px] flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors"
          :class="receiptDrag ? 'border-accent bg-accent/5' : 'border-slate-200 bg-slate-50/50 hover:border-accent/50'"
          @dragover.prevent="receiptDrag = true"
          @dragleave.prevent="receiptDrag = false"
          @drop.prevent="handleReceiptDrop"
          @click="receiptInput?.click()"
        >
          <span class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-accent/10 text-accent">
            <UploadCloud class="h-8 w-8" />
          </span>
          <h3 class="font-heading text-base font-bold text-slate-800">
            Drag and drop receipt images here, or click to browse
          </h3>
          <p class="mt-1 text-sm text-slate-400">
            Supports: JPG, PNG, PDF (Max 10MB per file)
          </p>
          <p class="mt-4 flex items-center gap-2 text-sm font-bold text-danger">
            <AlertTriangle class="h-4 w-4" />
            At least 2 receipts are required to proceed
          </p>
          <button
            class="mt-6 inline-flex items-center justify-center gap-2 rounded-lg bg-accent px-6 py-3 text-sm font-bold text-white shadow-sm transition-colors hover:bg-accent-600"
            type="button"
            @click.stop="receiptInput?.click()"
          >
            <UploadCloud class="h-4 w-4" />
            Select Files
          </button>
        </div>
      </section>

      <template v-else>
        <!-- â”€â”€ CARD 1: Upload Receipt Management â”€â”€ -->
        <section class="card p-6">
          <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <h2
                class="text-lg font-bold text-primary"
                style="font-family: 'Poppins', sans-serif"
              >
                Upload Receipts
              </h2>
              <span
                class="badge text-[11px] font-bold"
                :class="
                  receipts.length >= 2
                    ? 'bg-accent-50 border-accent/15 text-accent'
                    : 'bg-red-50 border-red-100 text-danger'
                "
              >
                {{ receipts.length }} receipt{{
                  receipts.length !== 1 ? "s" : ""
                }}
              </span>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
              <button
                class="inline-flex h-9 w-fit shrink-0 items-center justify-center gap-2 rounded-lg bg-accent px-3.5 text-xs font-bold text-white transition-colors hover:bg-accent-600"
                type="button"
                @click="receiptInput?.click()"
              >
                <PlusCircle class="h-3.5 w-3.5" /> Add More Receipts
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
                class="flex items-center gap-2 text-accent text-sm font-semibold"
              >
                <CheckCircle2 class="w-4 h-4" /> Ready to submit
              </div>
            </div>
          </div>
        </section>

        <!-- â”€â”€ CARD 2: One Scanned Receipt Block Per Receipt â”€â”€ -->
        <section class="card p-6">
          <div class="flex items-center justify-between mb-5">
            <h2
              class="text-lg font-bold text-primary"
              style="font-family: 'Poppins', sans-serif"
            >
              Scanned Receipts
            </h2>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
              <span
                class="text-xs font-bold"
                :class="
                  receipts.length < 2 ? 'text-danger' : 'text-accent'
                "
              >
                {{ receipts.length }} uploaded{{
                  receipts.length < 2
                    ? " - need at least 2 to proceed"
                    : " - ready!"
                }}
              </span>
              <button
                class="inline-flex h-8 w-fit shrink-0 items-center justify-center gap-1.5 rounded-lg border border-primary/20 bg-white px-3 text-xs font-bold text-primary transition-colors hover:bg-primary/5"
                type="button"
                @click="receiptInput?.click()"
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
              class="border border-accent/15 rounded-xl p-6 bg-accent-50/20"
            >
              <!-- Receipt number badge -->
              <div class="flex items-center gap-2 mb-4">
                <span
                  class="w-6 h-6 rounded-full bg-primary text-white text-[11px] font-bold flex items-center justify-center"
                  >{{ idx + 1 }}</span
                >
                <span
                  class="text-xs font-bold text-primary"
                  style="font-family: 'Poppins', sans-serif"
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
                        style="font-family: 'Poppins', sans-serif"
                      >
                        No Preview
                      </p>
                    </div>
                  </div>
                  <div>
                    <button
                      class="inline-flex h-9 w-fit items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3.5 text-xs font-bold text-danger transition-colors hover:bg-red-100"
                      type="button"
                      @click="removeReceipt(receipt)"
                    >
                      <Trash2 class="w-3.5 h-3.5" /> Delete Receipt
                    </button>
                  </div>
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
                    <div class="flex items-center justify-between">
                      <label class="input-label">TIN Number</label>
                      <span
                        class="inline-flex items-center gap-1 rounded-lg bg-accent px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white"
                      >
                        <Sparkles class="h-3 w-3 fill-white" /> AI Read
                      </span>
                    </div>
                    <input
                      class="input"
                      type="text"
                      :value="tinFor(receipt)"
                      readonly
                    />
                  </div>

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
                        class="bg-accent text-white px-3 py-2 rounded-lg text-[11px] font-bold flex items-center gap-1.5 flex-shrink-0"
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
                            <th class="px-4 py-2.5 font-bold">Items</th>
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
                      class="bg-accent-50 px-6 py-3 rounded-xl border border-accent/15 flex flex-col items-end shadow-sm"
                    >
                      <span
                        class="text-[10px] font-bold text-accent uppercase tracking-wider"
                        >Total</span
                      >
                      <span
                        class="text-xl font-black text-accent font-mono"
                        >{{ formatCurrency(receipt.amount) }}</span
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- â”€â”€ CARD 3: Meta & Attachments â”€â”€ -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Cutoff Period -->
          <section class="card p-6 flex flex-col gap-4">
            <div>
              <h3
                class="text-base font-bold text-primary mb-1"
                style="font-family: 'Poppins', sans-serif"
              >
                Cutoff Period <span class="text-danger">*</span>
              </h3>
              <div class="relative">
                <select
                  v-model="cutoffPeriod"
                  class="input appearance-none cursor-pointer bg-white pr-10"
                  :class="cutoffPeriod ? 'text-slate-700' : 'text-slate-400'"
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
              style="font-family: 'Poppins', sans-serif"
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
                    style="font-family: 'Poppins', sans-serif"
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

        <!-- â”€â”€ Summary Panel â”€â”€ -->
        <section
          class="bg-accent-50 border border-accent/15 rounded-xl p-6 space-y-4"
        >
          <h3
            class="text-base font-bold text-primary"
            style="font-family: 'Poppins', sans-serif"
          >
            Summary
          </h3>
          <div class="space-y-3">
            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-500">Uploaded Receipts</span>
              <span
                class="font-bold"
                :class="
                  receipts.length < 2 ? 'text-danger' : 'text-accent'
                "
              >
                {{ receipts.length
                }}{{ receipts.length < 2 ? " (need at least 2)" : " - ready" }}
              </span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-slate-500">Report Attached</span>
              <span class="text-slate-400">{{
                reportFile ? reportFile.name : "None"
              }}</span>
            </div>
            <div
              class="flex justify-between items-center text-sm pb-4 border-b border-accent/20"
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
                style="font-family: 'Poppins', sans-serif"
                >Total Amount</span
              >
              <span class="text-2xl font-black text-accent font-mono">{{
                formatCurrency(totalAmount)
              }}</span>
            </div>
          </div>
        </section>

        <!-- â”€â”€ Footer Actions â”€â”€ -->
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
