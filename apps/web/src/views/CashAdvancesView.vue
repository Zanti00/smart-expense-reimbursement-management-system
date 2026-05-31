<script setup>
import { ref, computed, onMounted } from "vue";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import { useCashAdvanceList } from "@/composables/useCashAdvanceList";
import ToastNotification from "@/components/ToastNotification.vue";

import BaseFilterTabs from "@/components/base/BaseFilterTabs.vue";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import CashAdvanceTable from "@/components/cash-advances/CashAdvanceTable.vue";
import RejectionModal from "@/components/cash-advances/RejectionModal.vue";
import CashAdvanceDetailsModal from "@/components/cash-advances/CashAdvanceDetailsModal.vue";
import { Plus, Wallet, Activity, ShieldCheck, X } from "lucide-vue-next";
import { formatPeso } from "@/utils/formatters";

const store = useCashAdvanceStore();
const auth = useAuthStore();
const { addToast } = useToast();

const { activeStatus, statusTabs, filteredRows, activeMetrics } =
  useCashAdvanceList(store, auth);

onMounted(() => store.fetchAll());

function openDetails(row) {
  viewingRecord.value = {
    id: row.id,
    purpose: row.purpose.replace(/\s\.\.\.$|\.\.\.$/, ""),
    amount: row.amount,
    balance:
      row.outstanding ??
      (["approved", "disbursed", "granted", "unliquidated"].includes(row.status)
        ? row.amount
        : 0),
    status: row.status,
    date: row.requested,
    updatedAt: row.status === "pending" ? row.requested : "11/01/2025",
    requestedBy: row.user || auth.user?.name || "Employee",
    userId: row.userId,
    dueDate:
      row.dueDate ||
      (row.status === "pending" || row.status === "rejected"
        ? "--"
        : "02/15/2025"),
    documentFileName:
      row.fileDescription || `Cash_Advance_Request_${row.id}.pdf`,
    adminNotes: row.adminNotes || "N/A",
    acknowledgedAt: row.acknowledgedAt,
    signatureImage: row.signatureImage,
    documentUrl: row.documentUrl,
  };
}

const rejectingId = ref(null);
const rejectionType = ref("");

const viewingRecord = ref(null);

function closeDetails() {
  viewingRecord.value = null;
}

async function quickApproveAdvance(id) {
  await store.approveRequest(id);
}
async function quickApproveSettlement(id) {
  await store.approveSettlement(id);
}

function openRejectModal(id, type) {
  rejectingId.value = id;
  rejectionType.value = type;
}

function cancelReject() {
  rejectingId.value = null;
  rejectionType.value = "";
}

async function confirmReject(reason) {
  if (!rejectingId.value) return;
  try {
    if (rejectionType.value === "advance") {
      await store.rejectRequest(rejectingId.value, reason);
    } else if (rejectionType.value === "settlement") {
      await store.rejectSettlement(rejectingId.value, reason);
    }
    addToast({ message: "Request rejected successfully", type: "success" });
    cancelReject();
  } catch (error) {
    addToast({ message: error.message || "Failed to reject", type: "error" });
  }
}

const kpis = computed(() => {
  const m = activeMetrics.value;
  if (!m) return [];
  const admin = auth.isAdmin;
  const cards = [];

  if (!admin) {
    cards.push({
      label: "Total Amount",
      value: formatPeso(m.totalAmount || 0),
      sub: "Total requested",
      icon: Wallet,
      iconBg: "bg-blue-900/10",
      iconColor: "text-blue-900",
      accent: "from-blue-900 to-blue-700",
    });
  }

  cards.push({
    label: admin ? "Pending Advances" : "Pending",
    value: m.pending || 0,
    sub: "Needs action",
    icon: Activity,
    iconBg: "bg-amber-500/10",
    iconColor: "text-amber-500",
    accent: "from-amber-400 to-amber-600",
  });

  cards.push({
    label: admin ? "Approved Advances" : "Approved",
    value: m.approved || 0,
    sub: "Processed",
    icon: ShieldCheck,
    iconBg: "bg-emerald-500/10",
    iconColor: "text-emerald-500",
    accent: "from-emerald-400 to-emerald-600",
  });

  cards.push({
    label: admin ? "Rejected Advances" : "Rejected",
    value: m.rejected || 0,
    sub: "Denied",
    icon: X,
    iconBg: "bg-red-500/10",
    iconColor: "text-red-500",
    accent: "from-red-400 to-red-600",
  });

  cards.push({
    label: admin ? "Total Outstanding Balance" : "Outstanding Balance",
    value: formatPeso(m.outstanding || 0),
    sub: admin ? "" : "To be settled",
    subtext: admin
      ? `(Total employees with outstanding balance: ${m.outstandingEmployees || 0})`
      : undefined,
    icon: Wallet,
    iconBg: "bg-blue-900/10",
    iconColor: "text-blue-900",
    accent: "from-blue-900 to-blue-700",
  });

  return cards;
});
</script>

<template>
  <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 font-sans">
    <ToastNotification />
    <!-- Page Header -->
    <section
      class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Wallet class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">Advance Requests</span>
        </div>
        <h1
          class="font-heading text-2xl font-bold leading-tight text-slate-800"
        >
          Cash Advance
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          {{
            auth.isAdmin
              ? "Review and manage employee cash advance activity"
              : "Request and track cash advance payments"
          }}
        </p>
      </div>

      <button
        id="request-advance-btn"
        class="inline-flex min-h-[44px] w-fit items-center justify-center gap-2 rounded-lg bg-accent px-6 py-3 font-heading text-sm font-bold text-white shadow-sm transition-all duration-200 ease-out hover:bg-accent-600 hover:shadow-card-hover hover:scale-[1.01] active:scale-[0.98]"
        @click="$router.push('/cash-advances/new')"
      >
        <Plus class="h-4 w-4" />
        New Request
      </button>
    </section>

    <!-- Analytics Metrics -->
    <BaseKpiGrid 
      :kpis="kpis" 
      :gridClasses="auth.isAdmin ? 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4' : 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4'" 
      :isLoading="store.isLoading"
    />

    <!-- Filter Status Tabs -->
    <section class="overflow-x-auto mb-2">
      <BaseFilterTabs v-model="activeStatus" :tabs="statusTabs" />
    </section>

    <!-- Cash Advance Data Table -->
    <CashAdvanceTable
      :rows="filteredRows"
      :isAdmin="auth.isAdmin"
      :isLoading="store.isLoading"
      @view="openDetails"
    />

    <!-- Rejection Modal -->
    <RejectionModal
      :isOpen="!!rejectingId"
      :id="rejectingId || ''"
      :type="rejectionType"
      @close="cancelReject"
      @confirm="confirmReject"
    />

    <!-- Cash Advance Details Modal -->
    <CashAdvanceDetailsModal
      :isOpen="!!viewingRecord"
      :record="viewingRecord"
      @close="closeDetails"
      @reject="openRejectModal"
      @approve-advance="quickApproveAdvance"
      @approve-settlement="quickApproveSettlement"
    />
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.1s linear;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
