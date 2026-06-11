<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useReceiptStore } from "@/stores/receipts";
import { useNotificationStore } from "@/stores/notification";
import { useToast } from "@/composables/useToast";
import { formatPeso } from "@/utils/formatters";
import { EXPENSE_CATEGORIES } from "@/utils/constants";

import StatusBadge from "@/components/base/StatusBadge.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import ReceiptViewModal from "@/components/expenses/ReceiptViewModal.vue";
import ExpenseCard from "@/components/expenses/ExpenseCard.vue";
import ExpenseCardSkeleton from "@/components/expenses/ExpenseCardSkeleton.vue";
import ReceiptUploadModal from "@/components/expenses/ReceiptUploadModal.vue";
import DeleteConfirmModal from "@/components/base/DeleteConfirmModal.vue";
import ReimbursementFormView from "@/views/ReimbursementFormView.vue";
import {
  AlertTriangle,
  Search,
  X,
  UploadCloud,
  Send,
  Receipt,
  Wallet,
  CheckSquare,
  DatabaseZap,
  ShieldCheck,
} from "lucide-vue-next";

const auth = useAuthStore();
const receiptsStore = useReceiptStore();
const notif = useNotificationStore();
const { addToast } = useToast();

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

const CATEGORIES = computed(() => {
  return ["All", ...EXPENSE_CATEGORIES.map((c) => c.name)];
});
const activeCategory = ref("All");
const activeStatus = ref("All");
const searchQuery = ref("");

const STATUS_FILTERS = [
  "All",
  "Processed",
  "Automatic Rejected",
  "Pending Admin Re-review",
  "Final Rejected",
];

function normalizeFilterLabel(value) {
  return String(value || "")
    .toLowerCase()
    .replace(/\s+/g, "-");
}

const filteredReceipts = computed(() => {
  let base = receiptsStore.visibleReceipts;
  if (activeCategory.value !== "All") {
    base = base.filter((r) => r.category === activeCategory.value);
  }
  if (activeStatus.value !== "All") {
    const status = normalizeFilterLabel(activeStatus.value);
    base = base.filter((r) => normalizeFilterLabel(r.status) === status);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    base = base.filter((r) =>
      [r.id, r.vendorName, r.fileName, r.category, r.status, r.invoiceNumber]
        .some((value) => String(value || "").toLowerCase().includes(q)),
    );
  }
  return base;
});

// ── Metrics ───────────────────────────────────────────────────────
const totalExpenses = computed(() =>
  receiptsStore.visibleReceipts.reduce((s, r) => s + (r.amount || 0), 0),
);

// ── Delete Modal ──────────────────────────────────────────────────
const deleteModalOpen = ref(false);
const selectedReceiptId = ref(null);

function promptDelete(id) {
  selectedReceiptId.value = id;
  deleteModalOpen.value = true;
}

async function confirmDelete(password) {
  const isValid = await auth.verifyPassword(password);
  
  if (isValid) {
    receiptsStore.softDelete(selectedReceiptId.value);
    const s = new Set(selectedIds.value);
    s.delete(selectedReceiptId.value);
    selectedIds.value = s;
    addToast({ message: "Receipt removed.", type: "success" });
    deleteModalOpen.value = false;
  } else {
    addToast({ message: "Invalid password.", type: "error" });
  }
}

// ── View Modal ────────────────────────────────────────────────────
const viewModalOpen = ref(false);
const viewedReceipt = ref(null);

function openViewModal(receipt) {
  viewedReceipt.value = receipt;
  viewModalOpen.value = true;
}

// ── Upload Modal ──────────────────────────────────────────────────
const uploadModalOpen = ref(false);
const receiptBeingEdited = ref(null);
const adminNotesByReceipt = ref({});

const automaticRejectedReceipts = computed(() =>
  receiptsStore.visibleReceipts.filter((r) => r.status === "automatic-rejected"),
);

const pendingReReviewReceipts = computed(() =>
  receiptsStore.visibleReceipts.filter((r) => r.status === "pending-admin-re-review"),
);

function openEditReceipt(receipt) {
  receiptBeingEdited.value = receipt;
  uploadModalOpen.value = true;
}

function closeUploadModal(value) {
  uploadModalOpen.value = value;
  if (!value) receiptBeingEdited.value = null;
}

async function finalizeReceiptReview(receipt, decision) {
  const notes = adminNotesByReceipt.value[receipt.id] || "";
  if (notes.trim().length < 10) {
    addToast({ message: "Admin notes must be at least 10 characters.", type: "error" });
    return;
  }

  await receiptsStore.finalizeReReview(receipt.id, decision, notes);
  addToast({
    message:
      decision === "approve"
        ? "Receipt approved by override."
        : "Final rejection confirmed.",
    type: "success",
  });
}

// ── Lifecycle ─────────────────────────────────────────────────────
onMounted(async () => {
  await Promise.all([receiptsStore.fetchAll()]);
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
    accent: "bg-accent",
  },
  {
    label: "Total Expenses",
    value: formatPeso(totalExpenses.value),
    sub: "Cumulative amount",
    icon: Wallet,
    iconBg: "bg-emerald-100",
    iconColor: "text-emerald-600",
    accent: "bg-emerald-500",
  },
  {
    label: "Selected",
    value: selectedCount.value,
    sub: "Ready to forward",
    icon: CheckSquare,
    iconBg: "bg-primary-100",
    iconColor: "text-primary-600",
    accent: "bg-primary",
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
              font-family: 'Poppins', sans-serif;
              letter-spacing: -0.02em;
            "
          >
            My Expense
          </h1>
          <p
            class="text-sm text-slate-400 mt-1"
            style="font-family: 'Open Sans', sans-serif"
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

      <!-- ── KPI Cards ── -->
      <BaseKpiGrid
        :kpis="kpis"
        :is-loading="receiptsStore.isLoading"
        gridClasses="grid-cols-1 sm:grid-cols-3 gap-4"
      />

      <div
        v-if="!auth.isAdmin && automaticRejectedReceipts.length > 0"
        class="rounded-xl border border-danger/20 bg-danger/5 p-4"
      >
        <div class="flex items-start gap-3">
          <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-danger" />
          <div>
            <p class="font-heading text-sm font-bold text-danger">
              {{ automaticRejectedReceipts.length }} receipt{{ automaticRejectedReceipts.length === 1 ? "" : "s" }} need correction
            </p>
            <p class="mt-1 text-sm text-slate-600">
              System validation automatically rejected these receipts. Edit and resubmit them for admin re-review.
            </p>
          </div>
        </div>
      </div>

      <section
        v-if="auth.isAdmin && pendingReReviewReceipts.length > 0"
        class="overflow-hidden rounded-xl border border-accent/20 bg-white shadow-sm"
      >
        <div class="flex items-center justify-between border-b border-slate-200 bg-accent-50 px-5 py-4">
          <div>
            <h2 class="font-heading text-base font-bold text-primary">
              Pending Admin Re-review
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
              Previously system rejected, then modified and resubmitted by employee.
            </p>
          </div>
          <span class="kpi-label text-accent">{{ pendingReReviewReceipts.length }} queued</span>
        </div>
        <div class="divide-y divide-slate-100">
          <div
            v-for="receipt in pendingReReviewReceipts"
            :key="receipt.id"
            class="grid gap-4 p-5 lg:grid-cols-[1fr_320px]"
          >
            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <StatusBadge status="pending-admin-re-review" />
                <span class="rounded-full border border-danger/20 bg-danger/5 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-danger">
                  Previously System Rejected
                </span>
              </div>
              <h3 class="font-heading text-sm font-bold text-slate-900">
                {{ receipt.vendorName || "Unknown Vendor" }}
              </h3>
              <p class="text-xs text-slate-500">
                {{ receipt.id }} | {{ receipt.fileName }} | {{ formatPeso(receipt.amount || 0) }}
              </p>
              <p class="text-xs text-slate-400">
                {{ receipt.complianceReason || "Modified by employee after automatic system rejection." }}
              </p>
            </div>
            <div class="space-y-3">
              <div class="input-wrapper">
                <label class="input-label">Admin Notes <span class="text-danger">*</span></label>
                <textarea
                  v-model="adminNotesByReceipt[receipt.id]"
                  class="input min-h-[88px] resize-none"
                  placeholder="Explain the final decision..."
                />
              </div>
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                  class="btn btn-primary !py-2"
                  @click="finalizeReceiptReview(receipt, 'approve')"
                >
                  <ShieldCheck class="h-4 w-4" />
                  Approve Override
                </button>
                <button
                  class="btn btn-danger !py-2"
                  @click="finalizeReceiptReview(receipt, 'reject')"
                >
                  Confirm Final Rejection
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Search / Popup Filters ── -->
      <BaseUtilityToolbar
        v-model:search="searchQuery"
        v-model:status-value="activeStatus"
        v-model:category-value="activeCategory"
        :statuses="STATUS_FILTERS"
        :categories="CATEGORIES"
      />

      <!-- ── Receipt Card Grid ── -->
      <TransitionGroup
        v-if="receiptsStore.isLoading"
        tag="div"
        name="list"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
      >
        <ExpenseCardSkeleton v-for="i in 8" :key="'skeleton-' + i" />
      </TransitionGroup>

      <TransitionGroup
        v-else-if="filteredReceipts.length > 0"
        tag="div"
        name="list"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
      >
        <ExpenseCard
          v-for="receipt in filteredReceipts"
          :key="receipt.id"
          :expense="receipt"
          :is-selected="selectedIds.has(receipt.id)"
          @select="toggleSelect"
          @view="openViewModal"
          @edit="openEditReceipt"
          @delete="promptDelete"
        />
      </TransitionGroup>

      <!-- Empty State -->
      <div
        v-else
        class="card p-16 flex flex-col items-center gap-4 text-center"
      >
        <div
          class="w-16 h-16 rounded-2xl bg-primary/5 flex items-center justify-center"
        >
          <Search class="w-7 h-7 text-primary/30" />
        </div>
        <div>
          <p
            class="text-sm font-semibold text-slate-600"
            style="font-family: 'Poppins', sans-serif"
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
    <ReceiptUploadModal
      :model-value="uploadModalOpen"
      :categories="EXPENSE_CATEGORIES"
      :receipt-to-edit="receiptBeingEdited"
      @update:model-value="closeUploadModal"
    />

    <!-- ── Delete Confirmation Modal ── -->
    <DeleteConfirmModal
      v-model="deleteModalOpen"
      @confirm="confirmDelete"
    />

    <!-- ── View Receipt Modal ── -->
    <ReceiptViewModal
      v-model="viewModalOpen"
      :receipt="viewedReceipt"
      @delete="promptDelete"
      @edit="openEditReceipt"
    />

    <!-- ── In-Page Reimbursement Form Overlay ── -->
    <Transition name="slide-up">
      <div
        v-if="showReimbursementForm"
        class="fixed inset-0 z-[60] flex flex-col bg-clinical overflow-hidden"
      >
        <!-- Sticky top bar with back button -->
        <div
          class="flex-shrink-0 flex items-center gap-3 px-6 py-3 bg-primary border-b border-slate-100 shadow-sm sticky top-0 z-10"
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
              style="font-family: 'Poppins', sans-serif"
            >
              New Reimbursement
            </h2>
          </div>
          <div
            class="ml-auto flex items-center gap-2 text-white/60 text-[11px]"
          >
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
