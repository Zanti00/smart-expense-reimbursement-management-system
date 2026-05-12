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

// KPIs (Instrument Modules)
const kpis = computed(() => [
  {
    label: 'Total Reimbursed',
    value: `₱${rStore.approved.reduce((s, i) => s + i.amount, 0).toLocaleString()}`,
    meter: 'MONTHLY',
    icon: TrendingUp,
    color: 'text-success',
    border: 'border-l-success'
  },
  {
    label: 'Awaiting Approval',
    value: rStore.pending.length,
    meter: 'PENDING',
    icon: Clock,
    color: 'text-warning',
    border: 'border-l-warning'
  },
  {
    label: 'Open Advances',
    value: `₱${caStore.totalOutstanding.toLocaleString()}`,
    meter: 'OUTSTANDING',
    icon: Wallet,
    color: 'text-primary',
    border: 'border-l-primary'
  },
  {
    label: 'Issues Found',
    value: 1,
    meter: 'ALERTS',
    icon: AlertTriangle,
    color: 'text-danger',
    border: 'border-l-danger'
  }
])

const cutoffDays = ref(5)
const cutoffHours = ref(14)

// Bar chart — Monthly Spend (Clinical Telemetry)
const barData = {
  labels: ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN'],
  datasets: [{
    label: 'DATASET-01',
    data: [42000, 68000, 55000, 91000, 47000, 73000],
    backgroundColor: '#252578',
    hoverBackgroundColor: '#1D1D61',
    borderRadius: 0,
    barPercentage: 0.6
  }]
}
const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { 
    legend: { display: false }, 
    tooltip: { 
      backgroundColor: '#0E0E33',
      titleFont: { family: 'JetBrains Mono', size: 10 },
      bodyFont: { family: 'JetBrains Mono', size: 12 },
      cornerRadius: 0,
      padding: 12,
      displayColors: false,
      callbacks: {
        label: (ctx) => ` ₱${ctx.raw.toLocaleString()} (${((ctx.raw / 333000) * 100).toFixed(1)}%)`
      }
    } 
  },
  scales: {
    x: { 
      grid: { display: false }, 
      ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 9 } } 
    },
    y: {
      grid: { color: '#E8EBF5', borderDash: [2, 2] },
      ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 9 }, callback: v => `₱${(v/1000).toFixed(0)}K` }
    }
  }
}

// Doughnut — By Category
const doughnutData = {
  labels: ['Lab Supplies', 'Transport', 'Client Meeting', 'Maintenance', 'Office'],
  datasets: [{
    data: [35, 20, 15, 20, 10],
    backgroundColor: ['#252578', '#1e293b', '#475569', '#94a3b8', '#e2e8f0'],
    borderWidth: 1,
    borderColor: '#ffffff'
  }]
}
const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '75%',
  plugins: {
    legend: {
      position: 'right',
      labels: { boxWidth: 8, padding: 12, color: '#475569', font: { family: 'Inter', size: 10, weight: 'bold' } }
    },
    tooltip: {
      backgroundColor: '#0E0E33',
      titleFont: { family: 'JetBrains Mono', size: 10 },
      bodyFont: { family: 'JetBrains Mono', size: 12 },
      cornerRadius: 0,
      callbacks: {
        label: (ctx) => ` ${ctx.raw}% TOTAL SPEND`
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
  <div class="flex flex-col gap-6 font-sans">
    <!-- Header Console -->
    <div class="flex items-end justify-between border-b border-slate-200 pb-6">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <Activity class="w-3.5 h-3.5 text-success" />
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">System Monitoring</span>
        </div>
        <h1 class="text-2xl font-bold text-primary tracking-tight uppercase">
          Welcome, {{ auth.user?.name?.split(' ')[0] }}
        </h1>
        <p class="text-xs font-medium text-slate-400 mt-1 uppercase tracking-wider">
          Financial Overview — April 2024
        </p>
      </div>
      <BaseButton v-if="!auth.isAdmin" id="dashboard-new-claim" variant="cta" @click="router.push('/reimbursements/new')">
        <Plus class="w-5 h-5 mr-1" /> SUBMIT NEW REQUEST
      </BaseButton>
      <BaseButton v-else variant="cta" @click="router.push('/reimbursements')">
        <Activity class="w-5 h-5 mr-1" /> REVIEW QUEUE
      </BaseButton>
    </div>

    <!-- DEADLINE CUTOFF WIDGET -->
    <div class="bg-primary text-white p-4 flex flex-col sm:flex-row sm:items-center justify-between border-l-4 border-l-warning shadow-sm gap-4">
      <div class="flex items-center gap-3">
        <Clock class="w-6 h-6 text-warning shrink-0" />
        <div>
          <h3 class="text-xs font-bold uppercase tracking-widest text-warning">Active Cutoff Period</h3>
          <p class="text-[11px] font-mono text-slate-300">NEXT LIQUIDATION DEADLINE IS APPROACHING</p>
        </div>
      </div>
      <div class="flex items-center gap-4 border border-warning/30 bg-warning/5 px-4 py-2">
        <div class="flex flex-col items-center">
          <span class="text-xl font-mono font-bold leading-none text-white">{{ String(cutoffDays).padStart(2, '0') }}</span>
          <span class="text-[9px] uppercase font-bold tracking-widest text-warning">DAYS</span>
        </div>
        <span class="text-2xl font-mono text-warning/50">:</span>
        <div class="flex flex-col items-center">
          <span class="text-xl font-mono font-bold leading-none text-white">{{ String(cutoffHours).padStart(2, '0') }}</span>
          <span class="text-[9px] uppercase font-bold tracking-widest text-warning">HRS</span>
        </div>
      </div>
    </div>

    <!-- KPI Matrix -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
      <div
        v-for="kpi in kpis"
        :key="kpi.label"
        :class="['kpi-card border-l-2', kpi.border]"
      >
        <div class="flex items-center justify-between mb-4">
          <span class="text-[9px] font-mono font-bold text-slate-300 tracking-tighter">{{ kpi.meter }}</span>
          <component :is="kpi.icon" :class="['w-4 h-4', kpi.color]" />
        </div>
        <div>
          <p class="kpi-value text-2xl">{{ kpi.value }}</p>
          <p class="kpi-label mt-1 opacity-80">{{ kpi.label }}</p>
        </div>
      </div>
    </div>

    <!-- Analytical Modules -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card p-5 lg:col-span-2 shadow-sm">
        <div class="flex items-center justify-between mb-6 border-b border-slate-50 pb-4">
          <h3 class="text-[11px] font-bold uppercase tracking-widest text-primary flex items-center gap-2">
            <span class="w-1.5 h-1.5 bg-primary" /> Monthly Spending Trend
          </h3>
          <span class="text-[10px] font-mono text-slate-400 uppercase">MONTHLY_REPORT</span>
        </div>
        <div class="h-64">
          <Bar :data="barData" :options="barOptions" />
        </div>
      </div>

      <div class="card p-5 shadow-sm">
        <h3 class="text-[11px] font-bold uppercase tracking-widest text-primary mb-6 flex items-center gap-2">
          <span class="w-1.5 h-1.5 bg-primary" /> Spending by Category
        </h3>
        <div class="h-64">
          <Doughnut :data="doughnutData" :options="doughnutOptions" />
        </div>
      </div>
    </div>

    <!-- Dual Role Readouts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Employee Focus: History -->
      <div v-if="!auth.isAdmin" class="card shadow-sm border-t-2 border-t-primary lg:col-span-2">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/50">
          <h3 class="text-[11px] font-bold uppercase tracking-widest text-primary">Personal History Tracker</h3>
          <button class="text-[10px] font-bold uppercase tracking-widest text-primary hover:text-primary-700 flex items-center gap-1" @click="router.push('/reimbursements')">
            VIEW ALL RECORDS <ArrowRight class="w-3 h-3" />
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="table-base border-0">
            <thead>
              <tr>
                <th class="!border-l-0">Ref #</th>
                <th>Description</th>
                <th>Category</th>
                <th>Amount (PHP)</th>
                <th>Date</th>
                <th class="!border-r-0">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in recentItems"
                :key="item.id"
                class="hover:bg-slate-50"
              >
                <td class="!border-l-0 text-slate-400">#{{ item.id }}</td>
                <td class="!font-sans font-bold text-slate-700 uppercase tracking-tight text-xs">{{ item.description }}</td>
                <td>
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ item.category }}</span>
                </td>
                <td class="font-bold text-primary">₱{{ item.amount.toLocaleString() }}</td>
                <td class="text-slate-400 uppercase">{{ item.date }}</td>
                <td class="!border-r-0"><StatusBadge :status="item.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Admin Focus: Review Queue (Overdue Advances highlighted) -->
      <div v-if="auth.isAdmin" class="card shadow-sm border-t-2 border-t-warning lg:col-span-2">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100 bg-slate-50/50">
          <h3 class="text-[11px] font-bold uppercase tracking-widest text-warning-700 flex items-center gap-2">
            <AlertTriangle class="w-3.5 h-3.5" /> Cash Advance Recovery Queue
          </h3>
          <button class="text-[10px] font-bold uppercase tracking-widest text-primary hover:text-primary-700 flex items-center gap-1" @click="router.push('/cash-advances')">
            VIEW FULL TABLE <ArrowRight class="w-3 h-3" />
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="table-base border-0">
            <thead>
              <tr>
                <th class="!border-l-0">Employee</th>
                <th>Purpose</th>
                <th>Amount</th>
                <th>Due Date</th>
                <th class="!border-r-0">Status Flags</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in activeAdvances"
                :key="item.id"
                class="hover:bg-slate-50"
                :class="isOverdue(item.dueDate) ? 'bg-danger/5 border-l-4 border-l-danger' : isAmberWarning(item.dueDate) ? 'bg-warning/5 border-l-4 border-l-warning' : ''"
              >
                <td class="!border-l-0 font-bold uppercase text-slate-700">{{ item.requestedBy }}</td>
                <td class="!font-sans text-xs">{{ item.purpose }}</td>
                <td class="font-bold text-primary">₱{{ item.amount.toLocaleString() }}</td>
                <td class="font-mono text-slate-500 uppercase">{{ item.dueDate }}</td>
                <td class="!border-r-0 flex items-center gap-2">
                  <span v-if="isOverdue(item.dueDate)" class="badge bg-danger text-white border-0 animate-pulse">OVERDUE</span>
                  <span v-else-if="isAmberWarning(item.dueDate)" class="badge bg-warning text-white border-0">DUE SOON</span>
                  <StatusBadge :status="item.status" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
