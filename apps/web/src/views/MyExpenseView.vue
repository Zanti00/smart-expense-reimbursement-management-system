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
import BaseFilterTabs from "@/components/base/BaseFilterTabs.vue";
import ReceiptViewModal from "@/components/expenses/ReceiptViewModal.vue";
import ExpenseCard from "@/components/expenses/ExpenseCard.vue";
import ReceiptUploadModal from "@/components/expenses/ReceiptUploadModal.vue";
import DeleteConfirmModal from "@/components/base/DeleteConfirmModal.vue";
import ReimbursementFormView from "@/views/ReimbursementFormView.vue";
import {
  Search,
  X,
  UploadCloud,
  Send,
  Receipt,
  Wallet,
  CheckSquare,
  DatabaseZap,
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

const filteredReceipts = computed(() => {
  const base = receiptsStore.visibleReceipts;
  if (activeCategory.value === "All") return base;
  return base.filter((r) => r.category === activeCategory.value);
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

function confirmDelete(password) {
  // Use a simulated backend validation or fallback to a default mock password for UI testing
  const validPassword = auth.user?.password || "password123";
  
  if (password === validPassword) {
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
    accent: "from-accent-400 to-accent",
  },
  {
    label: "Total Expenses",
    value: formatPeso(totalExpenses.value),
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
        gridClasses="grid-cols-1 sm:grid-cols-3 gap-4"
      />

      <!-- ── Category Filter Tabs ── -->
      <div class="mb-6 overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0 sm:overflow-visible sm:pb-0">
        <BaseFilterTabs
          :tabs="CATEGORIES"
          v-model="activeCategory"
        />
      </div>

      <!-- ── Receipt Card Grid ── -->
      <TransitionGroup
        v-if="filteredReceipts.length > 0"
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
      v-model="uploadModalOpen"
      :categories="EXPENSE_CATEGORIES"
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
    />

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
