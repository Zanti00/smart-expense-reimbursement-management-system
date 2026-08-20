<script setup>
import { ref, onMounted, computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useLiquidationStore } from "@/stores/liquidation";
import BaseKpiGrid from "@/components/base/BaseKpiGrid.vue";
import SkeletonLoader from "@/components/base/SkeletonLoader.vue";
import { Bar, Doughnut } from "vue-chartjs";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend,
  Title,
} from "chart.js";
import {
  Wallet,
  FileText,
  Banknote,
  ReceiptText,
  Clock,
  AlertTriangle,
} from "lucide-vue-next";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  ArcElement,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend,
  Title,
);

const auth = useAuthStore();
const rStore = useReimbursementStore();
const caStore = useCashAdvanceStore();
const liqStore = useLiquidationStore();

onMounted(async () => {
  await Promise.all([rStore.fetchAll(), caStore.fetchAll(), liqStore.fetchSettlements()]);
});

const isDashboardLoading = computed(
  () => rStore.isLoading || caStore.isLoading || liqStore.isLoading,
);

// ══════════════════════════════════════════════════
// EMPLOYEE DASHBOARD
// ══════════════════════════════════════════════════

const employeeKpis = computed(() => [
  {
    label: "Total Reimbursement",
    value: `₱${rStore.items
      .filter((i) => i.status === "approved" || i.status === "granted")
      .reduce((s, i) => s + parseFloat(i.amount || 0), 0)
      .toLocaleString()}`,
    sub: "Approved claims",
    icon: Wallet,
    iconBg: "bg-emerald-100",
    iconColor: "text-emerald-600",
    accent: "bg-emerald-500",
  },
  {
    label: "Total Reimbursement Request",
    value: rStore.items.length,
    sub: "All filed requests",
    icon: FileText,
    iconBg: "bg-accent-100",
    iconColor: "text-accent-600",
    accent: "bg-accent",
  },
  {
    label: "Total Filed Cash Advance",
    value: `₱${caStore.totalOutstanding.toLocaleString()}`,
    sub: "Total amount",
    icon: Banknote,
    iconBg: "bg-amber-100",
    iconColor: "text-amber-600",
    accent: "bg-amber-500",
  },
  {
    label: "Total Filed Liquidation",
    value: `₱${liqStore.settlements
      .reduce((s, i) => s + (Number(i.total_expense_amount) || 0), 0)
      .toLocaleString()}`,
    sub: "Total amount",
    icon: ReceiptText,
    iconBg: "bg-violet-100",
    iconColor: "text-violet-600",
    accent: "bg-violet-500",
  },
]);

// Employee Bar Chart
const employeeBarData = computed(() => ({
  labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
  datasets: [
    {
      label: "My Expense",
      data: [32000, 45000, 38000, 52000, 41000, 60000],
      backgroundColor: "#059669",
      borderRadius: 4,
      barPercentage: 0.6,
      categoryPercentage: 0.7,
    },
    {
      label: "Reimbursement",
      data: [28000, 35000, 42000, 39000, 55000, 48000],
      backgroundColor: "#2E85D8",
      borderRadius: 4,
      barPercentage: 0.6,
      categoryPercentage: 0.7,
    },
  ],
}));

// Employee Pie Chart
const employeePieData = {
  labels: ["Lab Supplies", "Transport", "Client Meeting", "Maintenance", "Office", "Reimbursement"],
  datasets: [
    {
      data: [25, 15, 15, 15, 10, 20],
      backgroundColor: ["#252578", "#2E85D8", "#059669", "#D97706", "#64748b", "#7c3aed"],
      borderWidth: 2,
      borderColor: "#ffffff",
      hoverOffset: 6,
    },
  ],
};

// ══════════════════════════════════════════════════
// ADMIN DASHBOARD
// ══════════════════════════════════════════════════

const adminKpis = computed(() => {
  const pendingApproval = rStore.items.filter((i) => i.status === "pending").length;
  const pendingCashAdvance = caStore.items.filter((i) => i.status === "pending").length;
  const pendingLiquidating = liqStore.settlements.filter(
    (i) => i.status === "pending"
  ).length;
  const overdueLiquidation = caStore.items.filter((i) => {
    if (!i.expected_liquidation_date && !i.dueDate) return false;
    const due = new Date(i.expected_liquidation_date || i.dueDate);
    return due < new Date() && ["disbursed", "signed", "approved"].includes(i.status);
  }).length;

  return [
    {
      label: "Pending Approval",
      value: pendingApproval,
      sub: "Awaiting sign-off",
      icon: Clock,
      iconBg: "bg-amber-100",
      iconColor: "text-amber-600",
      accent: "bg-amber-500",
    },
    {
      label: "Pending Cash Advance",
      value: pendingCashAdvance,
      sub: "Unreleased requests",
      icon: Banknote,
      iconBg: "bg-accent-100",
      iconColor: "text-accent-600",
      accent: "bg-accent",
    },
    {
      label: "Pending Liquidating",
      value: pendingLiquidating,
      sub: "Under processing",
      icon: ReceiptText,
      iconBg: "bg-violet-100",
      iconColor: "text-violet-600",
      accent: "bg-violet-500",
    },
    {
      label: "Overdue Liquidation",
      value: overdueLiquidation,
      sub: "Past due date",
      icon: AlertTriangle,
      iconBg: "bg-red-100",
      iconColor: "text-red-500",
      accent: "bg-red-500",
    },
  ];
});

// Admin Bar Chart: Monthly Spending Trend (Reimbursement only)
const adminBarData = computed(() => ({
  labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
  datasets: [
    {
      label: "Reimbursement",
      data: [85000, 120000, 95000, 140000, 110000, 130000],
      backgroundColor: "#2E85D8",
      borderRadius: 4,
      barPercentage: 0.5,
      categoryPercentage: 0.6,
    },
  ],
}));

// Admin Pie Chart: Cash Advance Status Distribution
const adminCaStatusPieData = computed(() => {
  const statuses = ["pending", "approved", "rejected", "disbursed", "signed", "liquidated"];
  const labels = ["Pending", "Approved", "Rejected", "Disbursed", "Signed", "Liquidated"];
  const colors = ["#D97706", "#059669", "#DC2626", "#2E85D8", "#252578", "#64748b"];
  const data = statuses.map(
    (s) => caStore.items.filter((i) => i.status === s).length
  );

  return {
    labels,
    datasets: [
      {
        data,
        backgroundColor: colors,
        borderWidth: 2,
        borderColor: "#ffffff",
        hoverOffset: 6,
      },
    ],
  };
});

// Admin Pie Chart: Advance vs Outstanding
const totalCashAdvanceAmount = computed(() =>
  caStore.items.reduce((s, i) => s + (Number(i.amount) || 0), 0)
);
const totalOutstandingBalance = computed(() => caStore.totalOutstanding);
const remainingOutstanding = computed(() => totalOutstandingBalance.value);

const adminBalancePieData = computed(() => ({
  labels: ["Total Cash Advance", "Outstanding Balance"],
  datasets: [
    {
      data: [totalCashAdvanceAmount.value, totalOutstandingBalance.value],
      backgroundColor: ["#252578", "#DC2626"],
      borderWidth: 2,
      borderColor: "#ffffff",
      hoverOffset: 6,
    },
  ],
}));

// ══════════════════════════════════════════════════
// SHARED
// ══════════════════════════════════════════════════

// Liquidation Line Chart (shared)
const liquidationGranularity = ref("month");

const lineData = computed(() => {
  const labels = {
    day: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
    week: ["Week 1", "Week 2", "Week 3", "Week 4"],
    month: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
  };
  const data = {
    day: [8000, 12000, 5000, 15000, 9000, 3000, 6000],
    week: [45000, 62000, 38000, 55000],
    month: [120000, 95000, 140000, 88000, 110000, 130000],
  };

  return {
    labels: labels[liquidationGranularity.value],
    datasets: [
      {
        label: "Liquidation Volume",
        data: data[liquidationGranularity.value],
        borderColor: "#252578",
        backgroundColor: "rgba(37, 37, 120, 0.05)",
        borderWidth: 2,
        pointBackgroundColor: "#252578",
        pointBorderColor: "#ffffff",
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.3,
        fill: true,
      },
    ],
  };
});

const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: "#1e293b",
      titleFont: { family: "Poppins", size: 11, weight: "600" },
      bodyFont: { family: "Poppins", size: 12 },
      cornerRadius: 8,
      padding: 12,
      callbacks: {
        label: (ctx) => ` ₱${ctx.raw.toLocaleString()}`,
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: "#94a3b8", font: { family: "Poppins", size: 11 } },
    },
    y: {
      grid: { color: "rgba(148,163,184,0.1)", borderDash: [4, 4] },
      ticks: {
        color: "#94a3b8",
        font: { family: "Poppins", size: 11 },
        callback: (v) => `₱${(v / 1000).toFixed(0)}K`,
      },
    },
  },
};

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "top",
      align: "end",
      labels: {
        boxWidth: 12,
        boxHeight: 12,
        padding: 16,
        color: "#475569",
        font: { family: "Poppins", size: 11 },
        usePointStyle: true,
        pointStyle: "rectRounded",
      },
    },
    tooltip: {
      backgroundColor: "#1e293b",
      titleFont: { family: "Poppins", size: 11, weight: "600" },
      bodyFont: { family: "Poppins", size: 12 },
      cornerRadius: 8,
      padding: 12,
      callbacks: {
        label: (ctx) => ` ${ctx.dataset.label}: ₱${ctx.raw.toLocaleString()}`,
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: "#94a3b8", font: { family: "Poppins", size: 11 } },
    },
    y: {
      grid: { color: "rgba(148,163,184,0.1)", borderDash: [4, 4] },
      ticks: {
        color: "#94a3b8",
        font: { family: "Poppins", size: 11 },
        callback: (v) => `₱${(v / 1000).toFixed(0)}K`,
      },
    },
  },
};

const pieOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "bottom",
      labels: {
        boxWidth: 10,
        boxHeight: 10,
        padding: 14,
        color: "#475569",
        font: { family: "Poppins", size: 11 },
        usePointStyle: true,
        pointStyle: "circle",
      },
    },
    tooltip: {
      backgroundColor: "#1e293b",
      titleFont: { family: "Poppins", size: 11 },
      bodyFont: { family: "Poppins", size: 12 },
      cornerRadius: 8,
      callbacks: {
        label: (ctx) => ` ${ctx.label}: ${ctx.raw}${typeof ctx.raw === 'number' && ctx.raw > 100 ? '' : '%'}`,
      },
    },
  },
};

const pieOptionsWithCurrency = {
  ...pieOptions,
  plugins: {
    ...pieOptions.plugins,
    tooltip: {
      ...pieOptions.plugins.tooltip,
      callbacks: {
        label: (ctx) => ` ${ctx.label}: ₱${ctx.raw.toLocaleString()}`,
      },
    },
  },
};
</script>

<template>
  <div class="flex flex-col gap-6 animate-fade-up">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-slate-800">
        Welcome back, <span class="text-primary">{{ auth.user?.name?.split(" ")[0] }}</span>
      </h1>
      <p class="mt-1 text-sm text-slate-400">
        {{ auth.isAdmin ? "Here's your administrative overview." : "Here's your financial overview." }}
      </p>
    </div>

    <!-- ══════════════════════════════════════════ -->
    <!-- EMPLOYEE DASHBOARD -->
    <!-- ══════════════════════════════════════════ -->
    <template v-if="!auth.isAdmin">
      <!-- KPI Cards -->
      <BaseKpiGrid
        :kpis="employeeKpis"
        gridClasses="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
        :isLoading="isDashboardLoading"
        :skeletonCount="4"
      />

      <!-- Charts Row 1: Bar + Pie -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card p-6 lg:col-span-2">
          <div class="mb-5">
            <h3 class="text-sm font-medium text-slate-700">
              Monthly Spending Trend for Reimbursement and My Expense
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">January – June 2026</p>
          </div>
          <div v-if="isDashboardLoading" class="h-56">
            <SkeletonLoader variant="chart" />
          </div>
          <div v-else class="h-56">
            <Bar :data="employeeBarData" :options="barOptions" />
          </div>
        </div>

        <div class="card p-6">
          <div class="mb-5">
            <h3 class="text-sm font-medium text-slate-700">Spending by Category</h3>
            <p class="text-xs text-slate-400 mt-0.5">Reimbursement Spending by Category</p>
          </div>
          <div v-if="isDashboardLoading" class="flex h-56 items-center justify-center">
            <div class="h-36 w-36 animate-pulse rounded-full bg-slate-200"></div>
          </div>
          <div v-else class="h-56">
            <Pie :data="employeePieData" :options="pieOptions" />
          </div>
        </div>
      </div>

      <!-- Liquidation Line Chart -->
      <div class="card p-6">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-sm font-medium text-slate-700">Liquidation Trend</h3>
            <p class="text-xs text-slate-400 mt-0.5">Total liquidated volume over time</p>
          </div>
          <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
            <button
              v-for="option in ['day', 'week', 'month']"
              :key="option"
              class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors capitalize"
              :class="liquidationGranularity === option
                ? 'bg-white text-primary shadow-sm'
                : 'text-slate-500 hover:text-slate-700'"
              @click="liquidationGranularity = option"
            >
              {{ option }}
            </button>
          </div>
        </div>
        <div v-if="isDashboardLoading" class="h-56">
          <SkeletonLoader variant="chart" />
        </div>
        <div v-else class="h-56">
          <Line :data="lineData" :options="lineOptions" />
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════ -->
    <!-- ADMIN DASHBOARD -->
    <!-- ══════════════════════════════════════════ -->
    <template v-else>
      <!-- KPI Cards -->
      <BaseKpiGrid
        :kpis="adminKpis"
        gridClasses="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4"
        :isLoading="isDashboardLoading"
        :skeletonCount="4"
      />

      <!-- Charts Row 1: Bar Chart -->
      <div class="card p-6">
        <div class="mb-5">
          <h3 class="text-sm font-medium text-slate-700">Monthly Spending Trend</h3>
          <p class="text-xs text-slate-400 mt-0.5">Total reimbursement disbursed across all employees</p>
        </div>
        <div v-if="isDashboardLoading" class="h-56">
          <SkeletonLoader variant="chart" />
        </div>
        <div v-else class="h-56">
          <Bar :data="adminBarData" :options="barOptions" />
        </div>
      </div>

      <!-- Charts Row 2: CA Status Pie + Liquidation Line -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Cash Advance Status Pie -->
        <div class="card p-6">
          <div class="mb-5">
            <h3 class="text-sm font-medium text-slate-700">Cash Advance Status Distribution</h3>
            <p class="text-xs text-slate-400 mt-0.5">Breakdown by current status</p>
          </div>
          <div v-if="isDashboardLoading" class="flex h-56 items-center justify-center">
            <div class="h-36 w-36 animate-pulse rounded-full bg-slate-200"></div>
          </div>
          <div v-else class="h-56">
            <Pie :data="adminCaStatusPieData" :options="pieOptions" />
          </div>
        </div>

        <!-- Liquidation Volume Line Chart -->
        <div class="card p-6 lg:col-span-2">
          <div class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-sm font-medium text-slate-700">Liquidation Volume</h3>
              <p class="text-xs text-slate-400 mt-0.5">Completed liquidations over time</p>
            </div>
            <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5">
              <button
                v-for="option in ['day', 'week', 'month']"
                :key="option"
                class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors capitalize"
                :class="liquidationGranularity === option
                  ? 'bg-white text-primary shadow-sm'
                  : 'text-slate-500 hover:text-slate-700'"
                @click="liquidationGranularity = option"
              >
                {{ option }}
              </button>
            </div>
          </div>
          <div v-if="isDashboardLoading" class="h-56">
            <SkeletonLoader variant="chart" />
          </div>
          <div v-else class="h-56">
            <Line :data="lineData" :options="lineOptions" />
          </div>
        </div>
      </div>

      <!-- Charts Row 3: Advance vs Outstanding Pie -->
      <div class="card p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-center">
          <div>
            <h3 class="text-sm font-medium text-slate-700 mb-1">Advance vs Outstanding Balance</h3>
            <p class="text-xs text-slate-400 mb-4">Total cash advance compared to outstanding balances</p>
            <div v-if="isDashboardLoading" class="flex h-48 items-center justify-center">
              <div class="h-32 w-32 animate-pulse rounded-full bg-slate-200"></div>
            </div>
            <div v-else class="h-48">
              <Pie :data="adminBalancePieData" :options="pieOptionsWithCurrency" />
            </div>
          </div>
          <div class="flex flex-col gap-4 p-5 rounded-xl bg-slate-50 border border-slate-100">
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Total Cash Advance</p>
              <p class="text-lg font-semibold text-primary">₱{{ totalCashAdvanceAmount.toLocaleString() }}</p>
            </div>
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Total Outstanding Balance</p>
              <p class="text-lg font-semibold text-red-600">₱{{ totalOutstandingBalance.toLocaleString() }}</p>
            </div>
            <div class="pt-3 border-t border-slate-200">
              <p class="text-xs text-slate-400 mb-0.5">Remaining Outstanding Amount</p>
              <p class="text-xl font-bold text-slate-800">₱{{ remainingOutstanding.toLocaleString() }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
