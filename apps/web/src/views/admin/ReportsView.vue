<script setup>
import { ref } from 'vue'
import { Line } from 'vue-chartjs'
import BaseButton from '@/components/base/BaseButton.vue'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler } from 'chart.js'
import { FileBarChart2, Download, FileType2, FileSpreadsheet, Activity, ChevronDown } from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)

const selectedPeriod = ref('Q1 2024')
const generating = ref('')

const periods = ['Q1 2024', 'Q2 2024', 'Q3 2024', 'Q4 2024', 'FY 2024']

const reports = [
  { id: 'expense-summary',    title: 'Expense Summary',           desc: 'Total spending by category and department.', icon: FileBarChart2 },
  { id: 'reimbursement-list', title: 'Claim History',             desc: 'Detailed list of all submitted claims.', icon: FileType2 },
  { id: 'cash-advance',       title: 'Cash Advance Report',       desc: 'Status of all outstanding advances.', icon: FileSpreadsheet },
  { id: 'overdue',            title: 'Overdue Report',            desc: 'Advances that are past their due date.', icon: FileSpreadsheet },
]

async function generate(id, format) {
  generating.value = `${id}-${format}`
  await new Promise(r => setTimeout(r, 1200))
  generating.value = ''
}

// Line chart — cumulative telemetry
const lineData = {
  labels: ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN'],
  datasets: [
    {
      label: 'REIMBURSEMENTS',
      data: [42000, 68000, 55000, 91000, 47000, 73000],
      borderColor: '#252578',
      backgroundColor: 'rgba(37,37,120,0.03)',
      tension: 0,
      fill: true,
      pointBackgroundColor: '#252578',
      pointRadius: 2,
      borderWidth: 1.5
    },
    {
      label: 'Cash Advances',
      data: [20000, 35000, 28000, 60000, 22000, 45000],
      borderColor: '#94a3b8',
      backgroundColor: 'rgba(148,163,184,0.03)',
      tension: 0,
      fill: true,
      pointBackgroundColor: '#94a3b8',
      pointRadius: 2,
      borderWidth: 1.5,
      borderDash: [4, 4]
    }
  ]
}
const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { 
    legend: { 
      position: 'bottom', 
      labels: { 
        boxWidth: 8, 
        padding: 20, 
        font: { family: 'Inter', size: 10, weight: 'bold' }, 
        color: '#475569',
        usePointStyle: true
      } 
    },
    tooltip: {
      backgroundColor: '#0E0E33',
      titleFont: { family: 'JetBrains Mono', size: 10 },
      bodyFont: { family: 'JetBrains Mono', size: 12 },
      cornerRadius: 0
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
</script>

<template>
  <div class="flex flex-col gap-6 font-sans">
    <!-- Page Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Activity class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">General Reports</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Reports
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Monthly spending overview
        </p>
      </div>
      <!-- Period Calibration -->
      <div class="flex flex-col gap-1.5 items-end">
        <label class="text-[9px] text-slate-400 font-bold uppercase tracking-widest">Period_Calibration</label>
        <div class="relative">
          <select v-model="selectedPeriod" class="input !py-1 !pr-8 text-[11px] font-bold bg-slate-50 border-slate-200 min-w-[120px] appearance-none">
            <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
          </select>
          <ChevronDown class="absolute right-2 top-1.5 w-3 h-3 text-slate-400 pointer-events-none" />
        </div>
      </div>
    </div>

    <!-- Telemetry Trend Module -->
    <div class="card p-6 shadow-sm">
      <div class="flex items-center justify-between mb-6 border-b border-slate-50 pb-4">
        <h3 class="text-[11px] font-bold uppercase tracking-widest text-primary flex items-center gap-2">
          <span class="w-1.5 h-1.5 bg-primary" /> Monthly Spending Trend — {{ selectedPeriod }}
        </h3>
        <span class="text-[10px] font-mono text-slate-400 uppercase tracking-tighter">DATA_OVERVIEW_2024</span>
      </div>
      <div class="h-64">
        <Line :data="lineData" :options="lineOptions" />
      </div>
    </div>

    <!-- Generation Registry -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="report in reports"
        :key="report.id"
        class="card p-5 border-l-4 border-l-slate-100 hover:border-l-primary transition-none"
      >
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 border border-slate-100 bg-clinical flex items-center justify-center flex-shrink-0">
            <component :is="report.icon" class="w-5 h-5 text-slate-400" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-primary uppercase tracking-widest">{{ report.title }}</p>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-wider mt-1.5 leading-relaxed">{{ report.desc }}</p>
          </div>
        </div>
        <div class="flex gap-2 mt-6">
          <BaseButton
            variant="secondary"
            class="flex-1"
            :disabled="generating === `${report.id}-pdf`"
            @click="generate(report.id, 'pdf')"
          >
            <span v-if="generating === `${report.id}-pdf`">Generating PDF...</span>
            <span v-else class="flex items-center gap-2"><Download class="w-3 h-3" /> PDF</span>
          </BaseButton>
          <BaseButton
            variant="secondary"
            class="flex-1"
            :disabled="generating === `${report.id}-xlsx`"
            @click="generate(report.id, 'xlsx')"
          >
            <span v-if="generating === `${report.id}-xlsx`">Generating XLSX...</span>
            <span v-else class="flex items-center gap-2"><Download class="w-3 h-3" /> XLSX</span>
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>
