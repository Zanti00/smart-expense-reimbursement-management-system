<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useReceiptStore } from "@/stores/receipts";
import { useNotificationStore } from "@/stores/notification";
import { useRouter } from "vue-router";
import StatusBadge from "@/components/base/StatusBadge.vue";
import ReimbursementFormView from "@/views/ReimbursementFormView.vue";
import {
  Search,
  FileText,
  Image as ImageIcon,
  Trash2,
  X,
  Download,
  UploadCloud,
  Eye,
  Send,
  Receipt,
  Wallet,
  CheckSquare,
  DatabaseZap,
  Sparkles,
  MapPin,
  Clock,
  CheckCircle2,
  Calendar,
  Save,
  ChevronDown,
} from "lucide-vue-next";

const auth = useAuthStore();
const receiptsStore = useReceiptStore();
const notif = useNotificationStore();
const router = useRouter();

// ── File Upload ──────────────────────────────────────────────────
const dragOver = ref(false);
const fileInput = ref(null);
const uploadFileInput = ref(null);
const fileError = ref("");
const uploadFilePreview = ref("");

async function computeSHA256(file) {
  const buffer = await file.arrayBuffer();
  const hashBuffer = await crypto.subtle.digest("SHA-256", buffer);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  return hashArray.map((b) => b.toString(16).padStart(2, "0")).join("");
}

async function processFile(file) {
  fileError.value = "";
  const validTypes = ["image/jpeg", "image/png", "application/pdf"];
  if (!validTypes.includes(file.type)) {
    fileError.value = "Invalid file type. Only JPEG, PNG, or PDF allowed.";
    notif.error(fileError.value);
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    fileError.value = "File size exceeds 10MB.";
    notif.error(fileError.value);
    return;
  }
  const hash = await computeSHA256(file);
  try {
    notif.info("Processing receipt upload...");
    await receiptsStore.simulateUpload(file, hash);
    notif.success("Receipt uploaded successfully.");
  } catch (e) {
    fileError.value = e.message;
    notif.error(e.message);
  }
}

function handleFileSelect(event) {
  const file = event.target.files[0];
  if (file) processFile(file);
}

function handleDrop(event) {
  dragOver.value = false;
  const file = event.dataTransfer.files[0];
  if (file) processFile(file);
}

// ── Selection ────────────────────────────────────────────────────
const selectedIds = ref(new Set());

function toggleSelect(id) {
  const s = new Set(selectedIds.value);
  if (s.has(id)) s.delete(id);
  else s.add(id);
  selectedIds.value = s;
}

const selectedCount = computed(() => selectedIds.value.size);

const showReimbursementForm = ref(false);

const selectedReceiptsData = computed(() =>
  receiptsStore.visibleReceipts.filter((r) => selectedIds.value.has(r.id)),
);

function forwardSelected() {
  if (selectedCount.value === 0) return;
  showReimbursementForm.value = true;
}

// ── Category Filter ───────────────────────────────────────────────
const CATEGORIES = computed(() => {
  const names = receiptsStore.categories.map((c) => c.name);
  return ["all", ...names];
});
const activeCategory = ref("all");

const filteredReceipts = computed(() => {
  const base = receiptsStore.visibleReceipts;
  if (activeCategory.value === "all") return base;
  return base.filter((r) => r.category === activeCategory.value);
});

// ── Metrics ───────────────────────────────────────────────────────
const totalExpenses = computed(() =>
  receiptsStore.visibleReceipts.reduce((s, r) => s + (r.amount || 0), 0),
);

function formatCurrency(amount) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(amount);
}

function formatSize(bytes) {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + " " + sizes[i];
}

// ── Delete Modal ──────────────────────────────────────────────────
const deleteModalOpen = ref(false);
const selectedReceiptId = ref(null);
const confirmCode = ref("");

function promptDelete(id) {
  selectedReceiptId.value = id;
  deleteModalOpen.value = true;
  confirmCode.value = "";
}

function confirmDelete() {
  if (confirmCode.value === "DELETE") {
    receiptsStore.softDelete(selectedReceiptId.value);
    const s = new Set(selectedIds.value);
    s.delete(selectedReceiptId.value);
    selectedIds.value = s;
    notif.success("Receipt removed.");
    deleteModalOpen.value = false;
  } else {
    notif.error("Invalid confirmation code.");
  }
}

// ── View Modal ────────────────────────────────────────────────────
const viewModalOpen = ref(false);
const viewedReceipt = ref(null);

function openViewModal(receipt) {
  viewedReceipt.value = receipt;
  viewModalOpen.value = true;
}

function closeViewModal() {
  viewModalOpen.value = false;
  setTimeout(() => {
    viewedReceipt.value = null;
  }, 200);
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

// ── Upload Modal ──────────────────────────────────────────────────
const uploadModalOpen = ref(false);
const uploadFile = ref(null);
const uploadForm = ref({
  invoice_number: "",
  transaction_date: "",
  tin: "",
  vendor_name: "",
  expense_category_id: "",
  total_amount: "",
  vat_amount: "",
  vat_classification: "vat",
});

function handleUploadFileSelect(event) {
  const file = event.target.files[0];
  if (file) {
    const validTypes = ["image/jpeg", "image/png", "application/pdf"];
    const ext = file.name.split(".").pop().toLowerCase();
    const validExts = ["jpg", "jpeg", "png", "pdf"];

    if (!validTypes.includes(file.type) && !validExts.includes(ext)) {
      notif.error("Invalid file type. Only JPEG, PNG, or PDF allowed.");
      event.target.value = "";
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      notif.error("File size exceeds 10MB.");
      event.target.value = "";
      return;
    }
    uploadFile.value = file;
    if (
      file.type.startsWith("image/") ||
      ["jpg", "jpeg", "png"].includes(ext)
    ) {
      uploadFilePreview.value = URL.createObjectURL(file);
    } else {
      uploadFilePreview.value = "";
    }
  }
}

function formatTIN(event) {
  let value = event.target.value.replace(/\D/g, "");
  let formatted = "";
  if (value.length > 0) formatted += value.substring(0, 3);
  if (value.length > 3) formatted += "-" + value.substring(3, 6);
  if (value.length > 6) formatted += "-" + value.substring(6, 9);
  if (value.length > 9) formatted += "-" + value.substring(9, 12);
  uploadForm.value.tin = formatted;
  event.target.value = formatted;
}

function triggerFileUpload() {
  if (uploadFileInput.value) {
    uploadFileInput.value.click();
  }
}

function resetUploadForm() {
  uploadFile.value = null;
  uploadFilePreview.value = "";
  if (uploadFileInput.value) uploadFileInput.value.value = "";

  uploadForm.value = {
    invoice_number: "",
    transaction_date: "",
    tin: "",
    vendor_name: "",
    expense_category_id: "",
    total_amount: "",
    vat_amount: "",
    vat_classification: "vat",
  };
}

async function saveReceipt() {
  if (!uploadForm.value.expense_category_id) {
    notif.error("Please select a category.");
    return;
  }
  if (uploadForm.value.tin) {
    const tinRegex = /^\d{3}-\d{3}-\d{3}(?:-\d{3})?$/;
    if (!tinRegex.test(uploadForm.value.tin)) {
      notif.error("TIN must be in the format 000-000-000 or 000-000-000-000");
      return;
    }
  }
  try {
    notif.info("Uploading receipt...");
    await receiptsStore.uploadReceipt(uploadFile.value, {
      expense_category_id: uploadForm.value.expense_category_id,
      vendor_name: uploadForm.value.vendor_name || null,
      transaction_date: uploadForm.value.transaction_date || null,
      total_amount: uploadForm.value.total_amount || null,
      vat_amount: uploadForm.value.vat_amount || null,
      tin: uploadForm.value.tin || null,
      invoice_number: uploadForm.value.invoice_number || null,
      vat_classification: uploadForm.value.vat_classification || null,
    });
    notif.success("Receipt uploaded and stored successfully.");
    resetUploadForm();
    uploadModalOpen.value = false;
  } catch (e) {
    notif.error(e.message || "Failed to upload receipt.");
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([
    receiptsStore.fetchCategories(),
    receiptsStore.fetchAll(),
  ]);
});

// KPI definitions matching dashboard pattern
const kpis = computed(() => [
  {
    label: "Total Receipts",
    value: receiptsStore.visibleReceipts.length,
    sub: "In repository",
    icon: Receipt,
    iconBg: "bg-accent-100",
    iconColor: "text-accent-600",
    accent: "from-accent-400 to-accent",
  },
  {
    label: "Total Expenses",
    value: formatCurrency(totalExpenses.value),
    sub: "Cumulative amount",
    icon: Wallet,
    iconBg: "bg-emerald-100",
    iconColor: "text-emerald-600",
    accent: "from-emerald-400 to-emerald-600",
  },
  {
    label: "Selected",
    value: selectedCount.value,
    sub: "Ready to forward",
    icon: CheckSquare,
    iconBg: "bg-primary-100",
    iconColor: "text-primary-600",
    accent: "from-primary-400 to-primary",
  },
]);
</script>

<template>
  <div>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto pb-12 animate-fade-up">
    <!-- ── Page Header ── -->
    <div
      class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
    >
      <div>
        <div class="flex items-center gap-2 mb-2">
          <DatabaseZap class="w-3.5 h-3.5 text-accent" />
          <span class="section-label">Expense Validation Module</span>
        </div>
        <h1
          class="text-2xl font-bold text-slate-800 leading-tight"
          style="
            font-family: &quot;Poppins&quot;, sans-serif;
            letter-spacing: -0.02em;
          "
        >
          My Expense
        </h1>
        <p
          class="text-sm text-slate-400 mt-1"
          style="font-family: &quot;Open Sans&quot;, sans-serif"
        >
          Organize and manage your receipts
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <!-- Forward to Reimbursement -->
        <button
          @click="forwardSelected"
          :disabled="selectedCount === 0"
          class="btn"
          :class="
            selectedCount > 0
              ? 'btn-primary'
              : 'btn-secondary opacity-50 cursor-not-allowed'
          "
        >
          <Send class="w-4 h-4" />
          Forward to Reimbursement{{
            selectedCount > 0 ? ` (${selectedCount})` : ""
          }}
        </button>
        <!-- Upload Receipt -->
        <button
          @click="uploadModalOpen = true"
          class="btn btn-cta min-h-[42px]"
        >
          <UploadCloud class="w-4 h-4" />
          Upload Receipt
        </button>
      </div>
    </div>

    <!-- ── KPI Cards — identical pattern to DashboardView ── -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div v-for="kpi in kpis" :key="kpi.label" class="kpi-card group">
        <!-- Colored accent top strip (overrides the default ::before with per-card gradient) -->
        <div
          :class="[
            'absolute top-0 left-0 right-0 h-0.5 rounded-t-xl bg-gradient-to-r',
            kpi.accent,
          ]"
        />

        <div class="flex items-center justify-between mb-4">
          <span
            class="text-xs text-slate-400"
            style="font-family: &quot;Open Sans&quot;, sans-serif"
            >{{ kpi.sub }}</span
          >
          <div
            :class="[
              'w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0',
              kpi.iconBg,
            ]"
          >
            <component :is="kpi.icon" :class="['w-4 h-4', kpi.iconColor]" />
          </div>
        </div>
        <p class="kpi-value">{{ kpi.value }}</p>
        <p class="kpi-label">{{ kpi.label }}</p>
      </div>
    </div>

    <!-- ── Category Filter Tabs ── -->
    <div v-if="false" class="hidden">
      <button
        v-for="cat in CATEGORIES"
        :key="cat"
        @click="activeCategory = cat"
        class="px-5 py-2 rounded-full text-[13px] font-semibold transition-all capitalize"
        :class="
          activeCategory === cat
            ? 'bg-gradient-to-r from-primary to-secondary text-white shadow-sm'
            : 'bg-white border border-slate-200 text-slate-500 hover:border-primary/30 hover:text-primary hover:bg-primary-50'
        "
      >
        {{ cat }}
      </button>
    </div>

    <!-- ── Receipt Card Grid ── -->
    <TransitionGroup
      v-if="filteredReceipts.length > 0"
      tag="div"
      name="list"
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
    >
      <div
        v-for="receipt in filteredReceipts"
        :key="receipt.id"
        class="bg-white rounded-xl overflow-hidden flex flex-col group transition-all hover:shadow-card-hover relative cursor-pointer"
        :class="
          selectedIds.has(receipt.id)
            ? 'border-2 border-primary shadow-md'
            : 'border border-slate-100 shadow-card'
        "
        @click="toggleSelect(receipt.id)"
      >
        <!-- Selected Badge -->
        <Transition name="pop">
          <div
            v-if="selectedIds.has(receipt.id)"
            class="absolute top-3 right-3 z-10 w-7 h-7 bg-primary rounded-full flex items-center justify-center shadow-md"
          >
            <svg
              class="w-4 h-4 text-white"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
        </Transition>

        <!-- Receipt Image Preview -->
        <div
          class="aspect-square w-full bg-slate-50 overflow-hidden flex-shrink-0 border-b border-slate-100"
        >
          <img
            v-if="receipt.thumbnail"
            :src="receipt.thumbnail"
            :alt="receipt.fileName"
            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 opacity-75"
          />
          <div
            v-else
            class="w-full h-full flex flex-col items-center justify-center gap-2"
          >
            <div
              class="w-12 h-12 rounded-2xl bg-primary/5 flex items-center justify-center"
            >
              <FileText
                v-if="
                  receipt.fileType === 'application/pdf' ||
                  receipt.fileType === 'pdf'
                "
                class="w-6 h-6 text-primary/40"
              />
              <ImageIcon v-else class="w-6 h-6 text-primary/40" />
            </div>
            <p
              class="text-[10px] text-slate-300 font-semibold uppercase tracking-widest"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              No Preview
            </p>
          </div>
        </div>

        <!-- Card Body -->
        <div class="p-4 flex flex-col flex-1">
          <!-- File / Merchant Info -->
          <div class="mb-3">
            <h3
              class="font-bold text-slate-800 text-[13px] leading-snug truncate"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              {{ receipt.fileName.replace(/\.[^.]+$/, "").replace(/_/g, " ") }}
            </h3>
            <p class="text-slate-400 text-[11px] mt-0.5 font-mono">
              {{ receipt.id }}
            </p>
            <p class="text-slate-400 text-[11px] mt-0.5">{{ receipt.date }}</p>
          </div>

          <!-- Category + Amount -->
          <div class="mt-auto flex items-center justify-between mb-3">
            <span
              class="px-2.5 py-1 bg-primary/5 text-primary-600 rounded-md text-[11px] font-semibold border border-primary/10 truncate max-w-[55%]"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              {{ receipt.category }}
            </span>
            <span class="font-bold text-[14px] text-success font-mono">
              {{ receipt.amount > 0 ? formatCurrency(receipt.amount) : "—" }}
            </span>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-2">
            <button
              class="btn btn-primary flex-1 !py-2 !text-xs"
              @click.stop="openViewModal(receipt)"
            >
              <Eye class="w-3.5 h-3.5" /> View
            </button>
            <button
              class="px-3 py-2 rounded-lg border transition-all flex items-center justify-center"
              :class="
                selectedIds.has(receipt.id)
                  ? 'border-danger/30 text-danger hover:bg-red-50'
                  : 'border-slate-200 text-slate-400 hover:border-danger/30 hover:text-danger hover:bg-red-50'
              "
              @click.stop="promptDelete(receipt.id)"
              title="Delete"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </TransitionGroup>

    <!-- Empty State -->
    <div v-else class="card p-16 flex flex-col items-center gap-4 text-center">
      <div
        class="w-16 h-16 rounded-2xl bg-primary/5 flex items-center justify-center"
      >
        <Search class="w-7 h-7 text-primary/30" />
      </div>
      <div>
        <p
          class="text-sm font-semibold text-slate-600"
          style="font-family: &quot;Poppins&quot;, sans-serif"
        >
          No receipts found
        </p>
        <p class="text-xs text-slate-400 mt-1">
          Try a different category filter or upload a new receipt.
        </p>
      </div>
      <button @click="uploadModalOpen = true" class="btn btn-cta mt-2">
        <UploadCloud class="w-4 h-4" /> Upload Receipt
      </button>
    </div>
  </div>

  <!-- ── Upload / Receipt Scanned Modal ── -->
  <Transition name="modal">
    <div
      v-if="uploadModalOpen"
      class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4"
      @click="
        resetUploadForm();
        uploadModalOpen = false;
      "
    >
      <div
        class="card w-full max-w-5xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300"
        @click.stop
      >
        <!-- Modal Header -->
        <div
          class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10"
        >
          <div class="flex items-center gap-3">
            <h2
              class="text-xl font-bold text-primary"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              Receipt Scanned
            </h2>
            <span
              class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-[11px] font-bold flex items-center gap-1.5 border border-emerald-100"
            >
              <Sparkles class="w-3.5 h-3.5 fill-emerald-600" />
              AI Read
            </span>
          </div>
          <button
            @click="
              resetUploadForm();
              uploadModalOpen = false;
            "
            class="p-2 text-slate-400 hover:text-primary transition-colors"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Content (Two Columns) -->
        <div
          class="flex flex-col md:flex-row flex-1 overflow-y-auto max-h-[75vh] md:max-h-[80vh]"
        >
          <!-- Left Column: File Upload Area -->
          <div
            class="w-full md:w-[340px] p-6 bg-slate-50 border-r border-slate-100 flex flex-col items-center"
          >
            <div
              class="w-full aspect-[3/4] bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col overflow-hidden group relative cursor-pointer"
              @click="triggerFileUpload"
            >
              <!-- Preview if image file selected -->
              <div v-if="uploadFile && uploadFilePreview" class="w-full h-full">
                <img
                  :src="uploadFilePreview"
                  alt="Receipt preview"
                  class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity"
                />
              </div>
              <!-- PDF or no-file placeholder -->
              <div
                v-else
                class="w-full h-full flex flex-col items-center justify-center gap-3 text-slate-400"
              >
                <div
                  class="w-16 h-16 rounded-2xl bg-primary/5 flex items-center justify-center"
                >
                  <UploadCloud
                    v-if="!uploadFile"
                    class="w-7 h-7 text-primary/40"
                  />
                  <FileText v-else class="w-7 h-7 text-primary/40" />
                </div>
                <p
                  class="text-[10px] text-slate-300 font-semibold uppercase tracking-widest text-center px-4"
                  style="font-family: &quot;Poppins&quot;, sans-serif"
                >
                  {{ uploadFile ? uploadFile.name : "Click to select file" }}
                </p>
                <p v-if="!uploadFile" class="text-[10px] text-slate-300">
                  JPEG, PNG, or PDF (max 10MB)
                </p>
              </div>
              <div
                class="absolute inset-0 bg-primary/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"
              >
                <UploadCloud class="w-10 h-10 text-primary" />
              </div>
            </div>
            <input
              ref="uploadFileInput"
              type="file"
              accept=".jpeg,.jpg,.png,.pdf"
              class="hidden"
              @change="handleUploadFileSelect"
            />
            <p class="mt-4 text-[11px] font-mono text-slate-400">
              {{ uploadFile ? uploadFile.name : "No file selected" }}
            </p>
          </div>

          <!-- Right Column: Form Data -->
          <div class="flex-1 p-6 space-y-6">
            <!-- Form Grid -->
            <div class="grid grid-cols-2 gap-4">
              <div class="input-wrapper">
                <label class="input-label">Invoice Number</label>
                <input
                  class="input"
                  type="text"
                  v-model="uploadForm.invoice_number"
                  placeholder="INV-2026-00001"
                />
              </div>
              <div class="input-wrapper">
                <label class="input-label">Date</label>
                <div class="relative">
                  <input
                    class="input"
                    type="date"
                    v-model="uploadForm.transaction_date"
                  />
                </div>
              </div>
            </div>

            <div class="input-wrapper">
              <label class="input-label">TIN Number</label>
              <input
                class="input"
                type="text"
                v-model="uploadForm.tin"
                @input="formatTIN"
                placeholder="000-000-000-000"
                maxlength="15"
              />
            </div>

            <div class="input-wrapper">
              <label class="input-label">Vendor Name</label>
              <input
                class="input"
                type="text"
                v-model="uploadForm.vendor_name"
                placeholder="Enter vendor name"
              />
            </div>

            <div class="input-wrapper">
              <label class="input-label"
                >Category <span class="text-danger">*</span></label
              >
              <div class="flex gap-3">
                <div class="relative flex-1">
                  <select
                    class="input appearance-none cursor-pointer"
                    v-model="uploadForm.expense_category_id"
                  >
                    <option value="" disabled>Select category</option>
                    <option
                      v-for="cat in receiptsStore.categories"
                      :key="cat.id"
                      :value="cat.id"
                    >
                      {{ cat.name }}
                    </option>
                  </select>
                  <ChevronDown
                    class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                  />
                </div>
                <span
                  class="bg-emerald-50 text-emerald-600 border border-emerald-100 px-4 py-2 rounded-lg flex items-center gap-2 text-[11px] font-bold whitespace-nowrap shadow-sm"
                >
                  <Sparkles class="w-3.5 h-3.5 fill-emerald-600" />
                  [AI-Suggested]
                </span>
              </div>
            </div>

            <div class="input-wrapper">
              <label class="input-label">VAT Classification</label>
              <div class="relative">
                <select
                  class="input appearance-none cursor-pointer"
                  v-model="uploadForm.vat_classification"
                  @change="
                    uploadForm.vat_classification === 'non-vat' &&
                    (uploadForm.vat_amount = '')
                  "
                >
                  <option value="vat">VAT</option>
                  <option value="non-vat">Non-VAT</option>
                </select>
                <ChevronDown
                  class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                />
              </div>
            </div>

            <!-- Totals Section -->
            <div
              class="flex items-end justify-between gap-4 pt-4 border-t border-slate-100"
            >
              <div class="flex gap-4">
                <div class="input-wrapper">
                  <label class="input-label">Total Amount</label>
                  <input
                    class="input !w-36"
                    type="number"
                    step="0.01"
                    min="0"
                    v-model="uploadForm.total_amount"
                    placeholder="0.00"
                  />
                </div>
                <div class="input-wrapper">
                  <label
                    class="input-label"
                    :class="{
                      'opacity-50': uploadForm.vat_classification === 'non-vat',
                    }"
                    >VAT Amount</label
                  >
                  <input
                    class="input !w-36 disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-slate-50"
                    type="number"
                    step="0.01"
                    min="0"
                    v-model="uploadForm.vat_amount"
                    placeholder="0.00"
                    :disabled="uploadForm.vat_classification === 'non-vat'"
                  />
                </div>
              </div>
              <div
                v-if="uploadForm.total_amount"
                class="bg-emerald-50 px-6 py-3 rounded-xl border border-emerald-100 flex flex-col items-end shadow-sm"
              >
                <span
                  class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider"
                  >Total Amount</span
                >
                <span class="text-xl font-black text-emerald-600 font-mono">{{
                  formatCurrency(Number(uploadForm.total_amount) || 0)
                }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div
          class="px-6 py-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3 sticky bottom-0"
        >
          <button
            @click="
              resetUploadForm();
              uploadModalOpen = false;
            "
            class="btn btn-secondary !px-8"
          >
            Discard All
          </button>
          <button
            @click="saveReceipt"
            class="btn btn-primary !px-8"
            :disabled="receiptsStore.isSaving"
          >
            <Save class="w-4 h-4" />
            {{ receiptsStore.isSaving ? "Saving..." : "Save Receipt" }}
          </button>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ── Delete Confirmation Modal ── -->
  <Transition name="modal">
    <div
      v-if="deleteModalOpen"
      class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="card w-full max-w-sm shadow-2xl overflow-hidden">
        <div
          class="px-6 py-4 flex items-center gap-3 border-b border-red-100"
          style="background: linear-gradient(135deg, #fef2f2 0%, #fff5f5 100%)"
        >
          <div
            class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center"
          >
            <Trash2 class="w-4 h-4 text-danger" />
          </div>
          <div>
            <h3
              class="text-sm font-semibold text-slate-800"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              Delete Receipt
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">
              This action cannot be undone.
            </p>
          </div>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <p
            class="text-sm text-slate-600"
            style="font-family: &quot;Open Sans&quot;, sans-serif"
          >
            Type <strong class="text-slate-900 font-bold">DELETE</strong> to
            confirm this action.
          </p>
          <div class="input-wrapper">
            <label class="input-label">Confirmation Code</label>
            <input
              type="text"
              class="input w-full uppercase tracking-widest"
              v-model="confirmCode"
              placeholder="DELETE"
            />
          </div>
          <div class="flex gap-2.5">
            <button
              class="btn btn-secondary flex-1"
              @click="deleteModalOpen = false"
            >
              Cancel
            </button>
            <button
              class="flex-1 px-4 py-2 rounded-lg text-xs font-bold text-white transition-all duration-200"
              :class="
                confirmCode === 'DELETE'
                  ? 'bg-danger hover:bg-red-700 shadow-sm'
                  : 'bg-slate-200 text-slate-400 cursor-not-allowed'
              "
              :disabled="confirmCode !== 'DELETE'"
              @click="confirmDelete"
            >
              Confirm Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ── View Receipt Modal ── -->
  <Transition name="modal">
    <div
      v-if="viewModalOpen && viewedReceipt"
      class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4 lg:p-8 backdrop-blur-sm"
      @click="closeViewModal"
    >
      <div
        class="card w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col shadow-2xl"
        @click.stop
      >
        <!-- HEADER -->
        <header
          class="px-6 py-4 flex items-center justify-between sticky top-0 z-20 text-white"
          style="background: linear-gradient(135deg, #252578 0%, #2f2f7e 100%)"
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
              <p class="text-xs text-white/70">{{ viewedReceipt.fileName }}</p>
            </div>
          </div>
          <button
            @click="closeViewModal"
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
                viewedReceipt.thumbnail &&
                viewedReceipt.fileType !== 'application/pdf'
              "
              :src="viewedReceipt.thumbnail"
              class="w-full h-full object-cover opacity-80"
            />
            <div
              v-else
              class="w-full h-full flex flex-col items-center justify-center gap-2 text-slate-300"
            >
              <FileText
                v-if="
                  viewedReceipt.fileType === 'application/pdf' ||
                  viewedReceipt.fileType === 'pdf'
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
            class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-100 rounded-xl"
          >
            <Sparkles class="w-4 h-4 text-emerald-600 fill-emerald-600" />
            <span
              class="text-xs font-semibold text-emerald-700"
              style="font-family: &quot;Poppins&quot;, sans-serif"
              >AI Scanned — Details automatically extracted</span
            >
          </div>

          <!-- DATA GRID -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- ID / Invoice -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Receipt ID</p>
              <p class="text-sm font-bold text-slate-800 font-mono">
                {{ viewedReceipt.id }}
              </p>
            </div>
            <!-- Category -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-2">Category</p>
              <span
                class="badge bg-primary-100 border-primary-200 text-primary-700"
              >
                {{ viewedReceipt.category }}
              </span>
            </div>
            <!-- Uploader -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Submitted By</p>
              <p class="text-sm font-bold text-slate-800">
                {{ viewedReceipt.uploader }}
              </p>
            </div>
            <!-- Date -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Transaction Date</p>
              <p class="text-sm font-bold text-slate-800">
                {{ formatDate(viewedReceipt.date) }}
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
                {{ viewedReceipt.hash }}
              </p>
            </div>
          </div>

          <!-- ITEMS CHECKLIST -->
          <div class="space-y-3">
            <h3
              class="text-sm font-bold text-slate-800 px-1"
              style="font-family: &quot;Poppins&quot;, sans-serif"
            >
              Items / Orders
            </h3>
            <div
              class="border border-slate-100 rounded-xl overflow-hidden bg-white divide-y divide-slate-50"
            >
              <div
                v-for="(item, idx) in getMockItems(viewedReceipt.category)"
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
                style="font-family: &quot;Poppins&quot;, sans-serif"
              >
                Amount Breakdown
              </h3>
            </div>
            <div class="p-5 space-y-3">
              <div class="flex justify-between items-center text-slate-500">
                <span class="text-sm">Subtotal</span>
                <span class="text-sm font-mono">{{
                  formatCurrency(getSubtotal(viewedReceipt.amount))
                }}</span>
              </div>
              <div
                class="flex justify-between items-center text-slate-500 pb-3 border-b border-slate-200"
              >
                <span class="text-sm">Tax (VAT 12%)</span>
                <span class="text-sm font-mono">{{
                  formatCurrency(getVat(viewedReceipt.amount))
                }}</span>
              </div>
              <div class="flex justify-between items-center pt-1">
                <span
                  class="text-base font-bold text-primary"
                  style="font-family: &quot;Poppins&quot;, sans-serif"
                  >Total Amount</span
                >
                <span class="text-xl font-black text-primary font-mono">{{
                  formatCurrency(viewedReceipt.amount)
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
                >Processed locally on {{ viewedReceipt.date }}</span
              >
            </div>
            <div class="flex gap-2">
              <button class="btn btn-secondary !py-2 !text-xs">
                <Download class="w-3.5 h-3.5" /> Download
              </button>
              <button
                @click="
                  promptDelete(viewedReceipt.id);
                  closeViewModal();
                "
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

  <!-- ── In-Page Reimbursement Form Overlay ── -->
  <Transition name="slide-up">
    <div
      v-if="showReimbursementForm"
      class="fixed inset-0 z-[60] flex flex-col bg-clinical overflow-hidden"
    >
      <!-- Sticky top bar with back button -->
      <div
        class="flex-shrink-0 flex items-center gap-3 px-6 py-3 bg-white border-b border-slate-100 shadow-sm sticky top-0 z-10"
        style="background: linear-gradient(135deg, #252578 0%, #2f2f7e 100%)"
      >
        <button
          @click="showReimbursementForm = false"
          class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors text-white"
        >
          <X class="w-4 h-4" />
        </button>
        <div>
          <p
            class="text-[10px] font-semibold text-white/60 uppercase tracking-widest"
          >
            My Expense
          </p>
          <h2
            class="text-sm font-bold text-white leading-tight"
            style="font-family: &quot;Poppins&quot;, sans-serif"
          >
            New Reimbursement
          </h2>
        </div>
        <div class="ml-auto flex items-center gap-2 text-white/60 text-[11px]">
          <Send class="w-3.5 h-3.5" />
          <span
            >{{ selectedCount }} receipt{{
              selectedCount !== 1 ? "s" : ""
            }}
            forwarded</span
          >
        </div>
      </div>

      <!-- Scrollable form body -->
      <div class="flex-1 overflow-y-auto">
        <div class="p-6">
          <ReimbursementFormView
            :forwarded-receipts="selectedReceiptsData"
            @submitted="showReimbursementForm = false"
            @close="showReimbursementForm = false"
          />
        </div>
      </div>
    </div>
  </Transition>
  </div>
</template>

<style scoped>
/* ── Card modal pop ── */
.modal-enter-active {
  transition: opacity 0.2s ease-out;
}
.modal-leave-active {
  transition: opacity 0.15s ease-in;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-active > div {
  animation: modal-pop 0.22s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes modal-pop {
  from {
    transform: scale(0.95) translateY(8px);
    opacity: 0;
  }
  to {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}

/* ── Full-page slide-up overlay ── */
.slide-up-enter-active {
  transition:
    transform 0.35s cubic-bezier(0.22, 1, 0.36, 1),
    opacity 0.25s ease-out;
}
.slide-up-leave-active {
  transition:
    transform 0.25s cubic-bezier(0.55, 0, 1, 0.45),
    opacity 0.2s ease-in;
}
.slide-up-enter-from {
  transform: translateY(100%);
  opacity: 0;
}
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}

/* ── Grid List Transitions ── */
.list-enter-active,
.list-leave-active,
.list-move {
  transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: scale(0.9) translateY(10px);
}
.list-leave-active {
  position: absolute;
}

/* ── Badge Pop ── */
.pop-enter-active {
  animation: bounce-in 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.pop-leave-active {
  animation: bounce-in 0.2s reverse ease-in;
}
@keyframes bounce-in {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
