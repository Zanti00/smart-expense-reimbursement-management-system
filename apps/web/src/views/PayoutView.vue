<script setup>
import { ref, computed, onMounted } from "vue";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import BaseUtilityToolbar from "@/components/base/BaseUtilityToolbar.vue";
import PayoutsTable from "@/components/payouts/PayoutsTable.vue";
import PayoutDetailsModal from "@/components/payouts/PayoutDetailsModal.vue";
import { formatPeso } from "@/utils/formatters";
import { usePayoutFilters } from "@/composables/payouts/usePayoutFilters";
import {
  Banknote,
  Wallet,
  CheckCircle2,
  Clock,
} from "lucide-vue-next";

const store = useReimbursementStore();
const auth = useAuthStore();
const { addToast } = useToast();

const statusFilters = ["All", "Acknowledged", "Awaiting Acknowledgement"];

const employeePayoutColumns = [
  { key: "referenceNumber", sortKey: "id", label: "Reference Number" },
  { key: "amount", label: "Amount", align: "right" },
  { key: "payoutMethod", label: "Payout Method" },
  { key: "grantedDate", label: "Granted Date" },
  {
    key: "acknowledgementStatus",
    label: "Acknowledgement Status",
    align: "center",
  },
  { key: "action", sortKey: "id", label: "Action", align: "center" },
];

const adminPayoutColumns = [
  { key: "referenceNumber", sortKey: "id", label: "Reference Number" },
  { key: "employeeName", sortKey: "employeeName", label: "Employee" },
  { key: "amount", label: "Amount", align: "right" },
  { key: "payoutMethod", label: "Payout Method" },
  { key: "grantedDate", label: "Granted Date" },
  {
    key: "acknowledgementStatus",
    label: "Acknowledgement Status",
    align: "center",
  },
  { key: "action", sortKey: "id", label: "Action", align: "center" },
];

const payoutColumns = computed(() =>
  auth.isAdmin ? adminPayoutColumns : employeePayoutColumns,
);

const {
  searchQuery,
  activeStatus,
  activeEmployee,
  activeMethod,
  dateFrom,
  dateTo,
  employeeOptions,
  methodOptions,
  sortKey,
  sortDirection,
  pageSize,
  currentPage,
  tableRows,
  sortedTableRows,
  paginatedTableRows,
  toggleSort,
} = usePayoutFilters(store, auth);

const payoutKpis = computed(() => {
  const allGranted = tableRows.value;
  const totalAmount = allGranted.reduce(
    (sum, item) => sum + (parseFloat(item.amount) || 0),
    0,
  );
  const acknowledgedCount = allGranted.filter((item) => item.acknowledged).length;
  const awaitingCount = allGranted.length - acknowledgedCount;

  if (auth.isAdmin) {
    return [
      {
        label: "Total Payouts",
        value: allGranted.length,
        sub: "Total releases",
        icon: Banknote,
        iconBg: "bg-accent/10",
        iconColor: "text-accent",
        accent: "bg-accent",
      },
      {
        label: "Total Amount Released",
        value: formatPeso(totalAmount),
        sub: "Disbursed funds",
        icon: Wallet,
        iconBg: "bg-emerald-500/10",
        iconColor: "text-emerald-500",
        accent: "bg-emerald-500",
      },
      {
        label: "Awaiting Acknowledgement",
        value: awaitingCount,
        sub: "Pending employee sign",
        icon: Clock,
        iconBg: "bg-amber-500/10",
        iconColor: "text-amber-500",
        accent: "bg-amber-500",
      },
    ];
  }

  return [
    {
      label: "Total Payouts",
      value: allGranted.length,
      sub: "Granted claims",
      icon: Banknote,
      iconBg: "bg-accent/10",
      iconColor: "text-accent",
      accent: "bg-accent",
    },
    {
      label: "Total Amount Received",
      value: formatPeso(totalAmount),
      sub: "Released funds",
      icon: Wallet,
      iconBg: "bg-emerald-500/10",
      iconColor: "text-emerald-500",
      accent: "bg-emerald-500",
    },
    {
      label: "Acknowledged",
      value: acknowledgedCount,
      sub: "Signed payouts",
      icon: CheckCircle2,
      iconBg: "bg-emerald-500/10",
      iconColor: "text-emerald-500",
      accent: "bg-emerald-500",
    },
    {
      label: "Awaiting Acknowledgement",
      value: awaitingCount,
      sub: "Pending signature",
      icon: Clock,
      iconBg: "bg-amber-500/10",
      iconColor: "text-amber-500",
      accent: "bg-amber-500",
    },
  ];
});

const viewingRecord = ref(null);
const isAcknowledgeSubmitting = ref(false);

function openDetails(row) {
  viewingRecord.value = row;
}

function closeDetails() {
  viewingRecord.value = null;
}

async function handleAcknowledge(signature) {
  if (!viewingRecord.value) return;
  isAcknowledgeSubmitting.value = true;
  try {
    await store.acknowledgePayout(viewingRecord.value.id, signature);
    addToast({ message: "Payout acknowledged successfully!", type: "success" });
    await store.fetchAll();
    const updated = tableRows.value.find((r) => r.id === viewingRecord.value.id);
    if (updated) {
      viewingRecord.value = updated;
    } else {
      closeDetails();
    }
  } catch (e) {
    addToast({
      message: e.message || "Failed to acknowledge payout",
      type: "error",
    });
  } finally {
    isAcknowledgeSubmitting.value = false;
  }
}

onMounted(() => store.fetchAll());
</script>

<template>
  <div class="flex flex-col gap-6 font-sans animate-fade-up">
    <!-- Page Header -->
    <div
      class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
      <div class="min-w-0">
        <h1
          class="text-2xl font-bold leading-tight font-heading text-slate-800"
        >
          Payout
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          {{ auth.isAdmin ? "Monitor, audit, and track all granted reimbursement payouts across employees." : "View and track all granted reimbursement payouts released to you." }}
        </p>
      </div>
    </div>

    <!-- KPI Cards -->
    <BaseKpiGrid
      :kpis="payoutKpis"
      :gridClasses="auth.isAdmin ? 'grid-cols-1 sm:grid-cols-3 gap-4' : 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4'"
      :isLoading="store.isLoading"
      :skeletonCount="auth.isAdmin ? 3 : 4"
    />

    <!-- Search & Filter Toolbar -->
    <BaseUtilityToolbar
      v-model:search="searchQuery"
      v-model:status-value="activeStatus"
      :statuses="statusFilters"
      search-placeholder="Search reference number, payout method, or amount..."
    >
      <template v-if="auth.isAdmin" #extra-filters>
        <div class="flex flex-col gap-3 p-1">
          <div>
            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 block">
              Employee
            </label>
            <select
              id="admin-employee-filter"
              v-model="activeEmployee"
              class="w-full text-xs rounded-lg border border-slate-200 bg-white p-2 text-slate-700"
            >
              <option value="All">All Employees</option>
              <option v-for="emp in employeeOptions" :key="emp" :value="emp">
                {{ emp }}
              </option>
            </select>
          </div>

          <div>
            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 block">
              Payout Method
            </label>
            <select
              id="admin-method-filter"
              v-model="activeMethod"
              class="w-full text-xs rounded-lg border border-slate-200 bg-white p-2 text-slate-700"
            >
              <option v-for="m in methodOptions" :key="m" :value="m">
                {{ m === "All" ? "All Methods" : m }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 block">
                From
              </label>
              <input
                id="admin-date-from"
                type="date"
                v-model="dateFrom"
                class="w-full text-xs rounded-lg border border-slate-200 bg-white p-2 text-slate-700"
              />
            </div>
            <div>
              <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1 block">
                To
              </label>
              <input
                id="admin-date-to"
                type="date"
                v-model="dateTo"
                class="w-full text-xs rounded-lg border border-slate-200 bg-white p-2 text-slate-700"
              />
            </div>
          </div>
        </div>
      </template>
    </BaseUtilityToolbar>

    <!-- Main Payouts Table -->
    <PayoutsTable
      :is-loading="store.isLoading"
      :rows="paginatedTableRows"
      :total-rows="sortedTableRows.length"
      :columns="payoutColumns"
      :sort-key="sortKey"
      :sort-direction="sortDirection"
      v-model:current-page="currentPage"
      :page-size="pageSize"
      :is-admin="auth.isAdmin"
      @toggle-sort="toggleSort"
      @view-details="openDetails"
    />

    <!-- Payout Details Modal (G2-049, G2-050, G2-052) -->
    <PayoutDetailsModal
      :record="viewingRecord"
      :is-admin="auth.isAdmin"
      :is-submitting="isAcknowledgeSubmitting"
      @close="closeDetails"
      @acknowledge="handleAcknowledge"
    />
  </div>
</template>
