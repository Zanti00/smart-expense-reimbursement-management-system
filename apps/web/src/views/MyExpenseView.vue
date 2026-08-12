<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useReceiptStore } from "@/stores/receipts";
import { useToast } from "@/composables/useToast";
import { formatPeso } from "@/utils/formatters";
import { EXPENSE_CATEGORIES } from "@/utils/constants";
import {
  getForwardingBlockReason,
  mapReceiptToReimbursement,
} from "@/utils/reimbursementForwarding";

import StatusBadge from "@/components/base/StatusBadge.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BasePagination from "@/components/base/BasePagination.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import ReceiptViewModal from "@/components/expenses/ReceiptViewModal.vue";
import ExpenseCard from "@/components/expenses/ExpenseCard.vue";
import ExpenseCardSkeleton from "@/components/expenses/ExpenseCardSkeleton.vue";
import ReceiptUploadModal from "@/components/expenses/ReceiptUploadModal.vue";
import DeleteConfirmModal from "@/components/base/DeleteConfirmModal.vue";
import {
  AlertTriangle,
  Search,
  UploadCloud,
  Send,
  Receipt,
  Wallet,
  CheckSquare,
  ShieldCheck,
} from "lucide-vue-next";

const auth = useAuthStore();
const receiptsStore = useReceiptStore();
const { addToast } = useToast();
const router = useRouter();

// ── Selection ────────────────────────────────────────────────────
const selectedIds = ref(new Set());

function toggleSelect(id) {
  const s = new Set(selectedIds.value);
  if (s.has(id)) s.delete(id);
  else s.add(id);
  selectedIds.value = s;
}

const selectedCount = computed(() => selectedIds.value.size);

const selectedReceiptsData = computed(() =>
  receiptsStore.visibleReceipts.filter((r) => selectedIds.value.has(r.id)),
);

function forwardSelected() {
  forwardReceipts(selectedReceiptsData.value);
}

function forwardReceipt(receipt) {
  forwardReceipts([receipt]);
}

function forwardReceipts(receipts) {
  const reason = getForwardingBlockReason(receipts);
  if (reason) {
    addToast({ message: reason, type: "error" });
    return;
  }

  sessionStorage.setItem(
    "serms_forwarded_reimbursement_receipts",
    JSON.stringify(receipts.map(mapReceiptToReimbursement)),
  );
  router.push("/reimbursements/new");
}

const CATEGORIES = computed(() => {
  const source = receiptsStore.categories.length
    ? receiptsStore.categories
    : EXPENSE_CATEGORIES;

  return ["All", ...source.map((c) => c.name)];
});
const activeCategory = ref("All");
const activeStatus = ref("All");
const searchQuery = ref("");
const currentPage = ref(1);
const pageSize = 10;

const STATUS_FILTERS = ["All", "Processed", "Pending", "Rejected", "Approved"];

function normalizeFilterLabel(value) {
  return String(value || "")
    .toLowerCase()
    .replace(/\s+/g, "-");
}

const filteredReceipts = computed(() => receiptsStore.visibleReceipts);

async function fetchReceipts(page = currentPage.value) {
  currentPage.value = page;
  await receiptsStore.fetchAll({
    page,
    perPage: pageSize,
    search: searchQuery.value.trim(),
    status: normalizeFilterLabel(activeStatus.value),
    category: activeCategory.value,
    scope: "mine",
  });
}

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
  const receipt = receiptsStore.visibleReceipts.find(
    (item) => item.id === selectedReceiptId.value,
  );

  if (
    receipt &&
    !["processed", "rejected"].includes(
      String(receipt.status || "").toLowerCase(),
    )
  ) {
    addToast({
      message: "Only processed or rejected receipts can be deleted.",
      type: "error",
    });
    deleteModalOpen.value = false;
    return;
  }

  const isValid = await auth.verifyPassword(password);

  if (isValid) {
    await receiptsStore.softDelete(selectedReceiptId.value);
    const s = new Set(selectedIds.value);
    s.delete(selectedReceiptId.value);
    selectedIds.value = s;
    const nextPage =
      receiptsStore.visibleReceipts.length === 1 && currentPage.value > 1
        ? currentPage.value - 1
        : currentPage.value;
    await fetchReceipts(nextPage);
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
  receiptsStore.visibleReceipts.filter(
    (r) => r.status === "automatic-rejected",
  ),
);

const pendingReReviewReceipts = computed(() =>
  receiptsStore.reReviewReceipts.filter(
    (r) => r.status === "pending-admin-re-review",
  ),
);

function openEditReceipt(receipt) {
  if (String(receipt?.status || "").toLowerCase() !== "processed") {
    addToast({
      message: "Only receipts with processed status can be edited.",
      type: "error",
    });
    return;
  }

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
    addToast({
      message: "Admin notes must be at least 10 characters.",
      type: "error",
    });
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
const handleOpenReceiptUpload = () => { uploadModalOpen.value = true; };

onMounted(async () => {
  const tasks = [fetchReceipts(1), receiptsStore.fetchCategories()];
  if (auth.isAdmin) tasks.push(receiptsStore.fetchReReviewReceipts());
  await Promise.all(tasks);
  window.addEventListener('open-receipt-upload', handleOpenReceiptUpload);
});

onUnmounted(() => {
  window.removeEventListener('open-receipt-upload', handleOpenReceiptUpload);
});

watch([activeCategory, activeStatus], () => {
  fetchReceipts(1);
});

watch(searchQuery, () => {
  fetchReceipts(1);
});

// KPI definitions matching dashboard pattern
const kpis = computed(() => [
  {
    label: "Total Receipts",
    value: receiptsStore.pagination.total,
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
    <div class="flex flex-col gap-6 pb-12 mx-auto max-w-7xl animate-fade-up">
      <!-- ── Page Header ── -->
      <div
        class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
      >
        <div>
          <h1
            class="text-2xl font-bold leading-tight text-slate-800"
            style="
              font-family: &quot;Poppins&quot;, sans-serif;
              letter-spacing: -0.02em;
            "
          >
            My Expense
          </h1>
          <p
            class="mt-1 text-sm text-slate-400"
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
            class="btn min-h-[42px] px-4 py-2 border border-transparent inline-flex items-center justify-center gap-2 font-medium"
            :class="
              selectedCount > 0
                ? 'bg-primary text-white hover:bg-primary/90'
                : 'bg-slate-100 text-slate-400 opacity-50 cursor-not-allowed'
            "
          >
            <Send class="w-4 h-4 shrink-0" />
            <span>To Reimbursement</span>
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
        class="p-4 border rounded-xl border-danger/20 bg-danger/5"
      >
        <div class="flex items-start gap-3">
          <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-danger" />
          <div>
            <p class="text-sm font-bold font-heading text-danger">
              {{ automaticRejectedReceipts.length }} receipt{{
                automaticRejectedReceipts.length === 1 ? "" : "s"
              }}
              need correction
            </p>
            <p class="mt-1 text-sm text-slate-600">
              System validation automatically rejected these receipts. Edit and
              resubmit them for admin re-review.
            </p>
          </div>
        </div>
      </div>

      <section
        v-if="auth.isAdmin && pendingReReviewReceipts.length > 0"
        class="overflow-hidden bg-white border shadow-sm rounded-xl border-accent/20"
      >
        <div
          class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-accent-50"
        >
          <div>
            <h2 class="text-base font-bold font-heading text-primary">
              Pending Admin Re-review
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
              Previously system rejected, then modified and resubmitted by
              employee.
            </p>
          </div>
          <span class="kpi-label text-accent"
            >{{ pendingReReviewReceipts.length }} queued</span
          >
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
                <StatusBadge status="automatic-rejected" />
              </div>
              <h3 class="text-sm font-bold font-heading text-slate-900">
                {{ receipt.vendorName || "Unknown Vendor" }}
              </h3>
              <p class="text-xs text-slate-500">
                {{ receipt.id }} | {{ receipt.fileName }} |
                {{ formatPeso(receipt.amount || 0) }}
              </p>
              <p class="text-xs text-slate-400">
                {{
                  receipt.complianceReason ||
                  "Modified by employee after automatic system rejection."
                }}
              </p>
            </div>
            <div class="space-y-3">
              <div class="input-wrapper">
                <label class="input-label"
                  >Admin Notes <span class="text-danger">*</span></label
                >
                <textarea
                  v-model="adminNotesByReceipt[receipt.id]"
                  class="input min-h-[88px] resize-none"
                  placeholder="Explain the final decision..."
                />
              </div>
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <button
                  class="btn btn-cta"
                  @click="finalizeReceiptReview(receipt, 'approve')"
                >
                  <ShieldCheck class="w-4 h-4" />
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
        class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      >
        <ExpenseCardSkeleton v-for="i in 8" :key="'skeleton-' + i" />
      </TransitionGroup>

      <template v-else-if="filteredReceipts.length > 0">
        <TransitionGroup
          tag="div"
          name="list"
          class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
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
            @forward-reimbursement="forwardReceipt"
          />
        </TransitionGroup>

        <BasePagination
          v-if="receiptsStore.pagination.total > pageSize"
          :page="currentPage"
          :page-size="pageSize"
          :total="receiptsStore.pagination.total"
          label="receipts"
          @update:page="fetchReceipts"
        />
      </template>

      <!-- Empty State -->
      <div
        v-else
        class="flex flex-col items-center gap-4 p-16 text-center card"
      >
        <div
          class="flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/5"
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
          <p class="mt-1 text-xs text-slate-400">
            Try a different category filter or upload a new receipt.
          </p>
        </div>
        <button @click="uploadModalOpen = true" class="mt-2 btn btn-cta">
          <UploadCloud class="w-4 h-4" /> Upload Receipt
        </button>
      </div>
    </div>

    <!-- ── Upload / Receipt Scanned Modal ── -->
    <ReceiptUploadModal
      :model-value="uploadModalOpen"
      :categories="receiptsStore.categories"
      :receipt-to-edit="receiptBeingEdited"
      @update:model-value="closeUploadModal"
    />

    <!-- ── Delete Confirmation Modal ── -->
    <DeleteConfirmModal v-model="deleteModalOpen" @confirm="confirmDelete" />

    <!-- ── View Receipt Modal ── -->
    <ReceiptViewModal
      v-model="viewModalOpen"
      :receipt="viewedReceipt"
      @delete="promptDelete"
      @edit="openEditReceipt"
    />
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
