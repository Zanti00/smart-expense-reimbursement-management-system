<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useReimbursementStore } from '@/stores/reimbursement'
import { useCashAdvanceStore } from '@/stores/cashAdvance'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement,
  ArcElement, Tooltip, Legend, Title
} from 'chart.js'
import {
  TrendingUp, Clock, AlertTriangle,
  ArrowRight, Plus, Wallet, Activity
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, BarElement, ArcElement, Tooltip, Legend, Title)

const auth = useAuthStore()
const rStore = useReimbursementStore()
const caStore = useCashAdvanceStore()
const router = useRouter()

onMounted(async () => {
  await Promise.all([rStore.fetchAll(), caStore.fetchAll()])
})

// KPIs
const kpis = computed(() => [
  {
    label: 'Total Reimbursed',
    value: `₱${rStore.approved.reduce((s, i) => s + i.amount, 0).toLocaleString()}`,
    sub: 'This month',
    icon: TrendingUp,
    iconBg: 'bg-emerald-100',
    iconColor: 'text-emerald-600',
    accent: 'from-emerald-400 to-emerald-600',
  },
  {
    label: 'Awaiting Approval',
    value: rStore.pending.length,
    sub: 'Pending review',
    icon: Clock,
    iconBg: 'bg-amber-100',
    iconColor: 'text-amber-600',
    accent: 'from-amber-400 to-amber-600',
  },
  {
    label: 'Open Advances',
    value: `₱${caStore.totalOutstanding.toLocaleString()}`,
    sub: 'Outstanding',
    icon: Wallet,
    iconBg: 'bg-accent-100',
    iconColor: 'text-accent-600',
    accent: 'from-accent-400 to-accent',
  },
  {
    label: 'Issues Found',
    value: 1,
    sub: 'Requires attention',
    icon: AlertTriangle,
    iconBg: 'bg-red-100',
    iconColor: 'text-red-500',
    accent: 'from-red-400 to-red-600',
  }
])

const cutoffDays = ref(5)
const cutoffHours = ref(14)

// Bar chart
const barData = {
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
  datasets: [{
    label: 'Monthly Spend',
    data: [42000, 68000, 55000, 91000, 47000, 73000],
    backgroundColor: 'rgba(46,133,216,0.85)',
    hoverBackgroundColor: '#252578',
    borderRadius: 6,
    barPercentage: 0.55,
  }]
}
const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: '#1D1D61',
      titleFont: { family: 'Poppins', size: 11, weight: '600' },
      bodyFont: { family: 'Open Sans', size: 12 },
      cornerRadius: 8,
      padding: 12,
      displayColors: false,
      callbacks: {
        label: (ctx) => ` ₱${ctx.raw.toLocaleString()}`
      }
    }
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#94a3b8', font: { family: 'Open Sans', size: 11 } }
    },
    y: {
      grid: { color: 'rgba(148,163,184,0.12)', borderDash: [4, 4] },
      ticks: {
        color: '#94a3b8',
        font: { family: 'Open Sans', size: 11 },
        callback: v => `₱${(v / 1000).toFixed(0)}K`
      }
    }
  }
}

// Doughnut chart
const doughnutData = {
  labels: ['Lab Supplies', 'Transport', 'Client Meeting', 'Maintenance', 'Office'],
  datasets: [{
    data: [35, 20, 15, 20, 10],
    backgroundColor: ['#252578', '#2E85D8', '#2F2F7E', '#93c5fd', '#dbeafe'],
    borderWidth: 2,
    borderColor: '#ffffff',
    hoverOffset: 4,
  }]
}
const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: {
      position: 'right',
      labels: {
        boxWidth: 8,
        boxHeight: 8,
        padding: 14,
        color: '#475569',
        font: { family: 'Open Sans', size: 11, weight: '500' }
      }
    },
    tooltip: {
      backgroundColor: '#1D1D61',
      titleFont: { family: 'Poppins', size: 11 },
      bodyFont: { family: 'Open Sans', size: 12 },
      cornerRadius: 8,
      callbacks: {
        label: (ctx) => ` ${ctx.raw}% of total spend`
      }
    }
  }
}

const recentItems = computed(() => rStore.items.slice(0, 5))
const activeAdvances = computed(() => caStore.items.slice(0, 5))

function isOverdue(dateStr) {
  const diff = (new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24)
  return diff < 0
}
function isAmberWarning(dateStr) {
  const diff = (new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24)
  return diff >= 0 && diff <= 5
}
</script>

<template>
  <div class="flex flex-col gap-6 font-sans animate-fade-up">

    <!-- ── Page Header ── -->
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <div class="w-1.5 h-1.5 bg-success rounded-full animate-pulse" />
          <span class="section-label">Financial Overview</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Welcome back, <span class="text-primary">{{ auth.user?.name?.split(' ')[0] }}</span> 👋
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Here's what's happening with your finances today.
        </p>
      </div>
      <BaseButton v-if="!auth.isAdmin" id="dashboard-new-claim" variant="cta" @click="router.push('/reimbursements/new')">
        <Plus class="w-4 h-4" /> New Request
      </BaseButton>
      <BaseButton v-else variant="cta" @click="router.push('/reimbursements')">
        <Activity class="w-4 h-4" /> Review Queue
      </BaseButton>
    </div>

    <!-- ── Deadline Cutoff Widget ── -->
    <div class="rounded-xl overflow-hidden shadow-sm"
         style="background: linear-gradient(120deg, #252578 0%, #2F2F7E 60%, #2E85D8 100%);">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between p-5 gap-4">
        <div class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
            <Clock class="w-5 h-5 text-warning" />
          </div>
          <div>
            <h3 class="text-sm font-semibold text-warning" style="font-family: 'Poppins', sans-serif;">
              Upcoming Liquidation Deadline
            </h3>
            <p class="text-white/50 text-xs mt-0.5" style="font-family: 'Open Sans', sans-serif;">
              Next cutoff period is approaching. Submit your liquidations on time.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3 bg-white/10 border border-white/20 rounded-xl px-5 py-3 backdrop-blur-sm self-start sm:self-auto">
          <div class="flex flex-col items-center">
            <span class="text-2xl font-bold text-white leading-none" style="font-family: 'Poppins', sans-serif;">
              {{ String(cutoffDays).padStart(2, '0') }}
            </span>
            <span class="text-[10px] text-warning/80 font-semibold tracking-widest mt-0.5">DAYS</span>
          </div>
          <span class="text-2xl text-white/30 font-thin">:</span>
          <div class="flex flex-col items-center">
            <span class="text-2xl font-bold text-white leading-none" style="font-family: 'Poppins', sans-serif;">
              {{ String(cutoffHours).padStart(2, '0') }}
            </span>
            <span class="text-[10px] text-warning/80 font-semibold tracking-widest mt-0.5">HRS</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── KPI Cards ── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div
        v-for="kpi in kpis"
        :key="kpi.label"
        class="kpi-card group"
      >
        <!-- Colored top bar per card -->
        <div :class="['absolute top-0 left-0 right-0 h-0.5 rounded-t-xl bg-gradient-to-r', kpi.accent]" />

        <div class="flex items-center justify-between mb-4">
          <span class="text-xs text-slate-400" style="font-family: 'Open Sans', sans-serif;">{{ kpi.sub }}</span>
          <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', kpi.iconBg]">
            <component :is="kpi.icon" :class="['w-4 h-4', kpi.iconColor]" />
          </div>
        </div>
        <p class="kpi-value">{{ kpi.value }}</p>
        <p class="kpi-label">{{ kpi.label }}</p>
      </div>
    </div>

    <!-- ── Charts Row ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Bar Chart -->
      <div class="card p-6 lg:col-span-2">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-sm font-semibold text-slate-800" style="font-family: 'Poppins', sans-serif;">
              Monthly Spending Trend
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">January – June 2024</p>
          </div>
          <span class="text-[10px] font-semibold text-accent bg-accent/10 border border-accent/20 rounded-full px-2.5 py-1">
            2024
          </span>
        </div>
        <div class="h-56">
          <Bar :data="barData" :options="barOptions" />
        </div>
      </div>

      <!-- Doughnut Chart -->
      <div class="card p-6">
        <div class="mb-5">
          <h3 class="text-sm font-semibold text-slate-800" style="font-family: 'Poppins', sans-serif;">
            Spend by Category
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">Distribution breakdown</p>
        </div>
        <div class="h-56">
          <Doughnut :data="doughnutData" :options="doughnutOptions" />
        </div>
      </div>
    </div>

    <!-- ── Employee History Table ── -->
    <div v-if="!auth.isAdmin" class="card overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-800" style="font-family: 'Poppins', sans-serif;">
          My Recent Submissions
        </h3>
        <button
          class="text-xs font-semibold text-accent hover:text-accent-700 flex items-center gap-1 transition-colors"
          style="font-family: 'Open Sans', sans-serif;"
          @click="router.push('/reimbursements')"
        >
          View all <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>Ref #</th>
              <th>Description</th>
              <th>Category</th>
              <th>Amount (PHP)</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in recentItems" :key="item.id">
              <td class="font-mono text-slate-400 text-xs">#{{ item.id }}</td>
              <td class="font-semibold text-slate-700">{{ item.description }}</td>
              <td>
                <span class="text-xs font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                  {{ item.category }}
                </span>
              </td>
              <td class="font-semibold text-primary font-mono">₱{{ item.amount.toLocaleString() }}</td>
              <td class="text-slate-400 text-xs">{{ item.date }}</td>
              <td><StatusBadge :status="item.status" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Admin: Cash Advance Recovery Queue ── -->
    <div v-if="auth.isAdmin" class="card overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <div class="flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-warning" />
          <h3 class="text-sm font-semibold text-slate-800" style="font-family: 'Poppins', sans-serif;">
            Cash Advance Recovery Queue
          </h3>
        </div>
        <button
          class="text-xs font-semibold text-accent hover:text-accent-700 flex items-center gap-1 transition-colors"
          style="font-family: 'Open Sans', sans-serif;"
          @click="router.push('/cash-advances')"
        >
          View full table <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Purpose</th>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in activeAdvances"
              :key="item.id"
              :class="[
                isOverdue(item.dueDate) ? 'border-l-2 border-l-danger' :
                isAmberWarning(item.dueDate) ? 'border-l-2 border-l-warning' : ''
              ]"
            >
              <td class="font-semibold text-slate-700">{{ item.requestedBy }}</td>
              <td class="text-slate-500 text-xs">{{ item.purpose }}</td>
              <td class="font-semibold text-primary font-mono">₱{{ item.amount.toLocaleString() }}</td>
              <td class="font-mono text-slate-400 text-xs">{{ item.dueDate }}</td>
              <td class="flex items-center gap-2 flex-wrap">
                <span
                  v-if="isOverdue(item.dueDate)"
                  class="badge bg-red-50 border-red-200 text-red-600 animate-pulse"
                >
                  <span class="w-1.5 h-1.5 bg-red-400 rounded-full" />
                  Overdue
                </span>
                <span
                  v-else-if="isAmberWarning(item.dueDate)"
                  class="badge bg-amber-50 border-amber-200 text-amber-700"
                >
                  <span class="w-1.5 h-1.5 bg-amber-400 rounded-full" />
                  Due Soon
                </span>
                <StatusBadge :status="item.status" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>
