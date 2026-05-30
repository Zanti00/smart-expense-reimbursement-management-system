<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useCashAdvanceStore } from '@/stores/cashAdvance'
import { useAuthStore } from '@/stores/auth'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import {
  Plus,
  X,
  Wallet,
  Activity,
  ShieldAlert,
  FileMinus,
  FileText,
  Eye,
  ArrowLeft,
  Calendar,
  UploadCloud,
  Info,
  MessageSquare,
  Send,
  Download,
  FileDown,
  RotateCcw,
  ShieldCheck,
  Trash2,
  RefreshCw
} from 'lucide-vue-next'

const store = useCashAdvanceStore()
const auth = useAuthStore()

onMounted(() => store.fetchAll())

const showModal = ref(false)
const submitting = ref(false)
const form = reactive({ purpose: '', amount: '', dueDate: '' })

const columns = []

const statusTabs = ['All', 'Pending', 'Approved', 'Rejected', 'Disbursed']
const activeStatus = ref('All')

const cashAdvanceRows = [
  {
    id: 'CA001',
    purpose: 'Client meeting expenses and travel costs for Cebu ...',
    amount: 15000,
    status: 'approved',
    requested: '1/10/2025'
  },
  {
    id: 'CA002',
    purpose: 'Medical equipment demonstration supplies and tr...',
    amount: 8000,
    status: 'pending',
    requested: '1/28/2025'
  },
  {
    id: 'CA003',
    purpose: 'Conference registration and accommodation for ...',
    amount: 12500,
    status: 'disbursed',
    requested: '12/15/2024'
  },
  {
    id: 'CA004',
    purpose: 'Office supplies and printing materials for quarterly...',
    amount: 5000,
    status: 'rejected',
    requested: '11/20/2024'
  },
  {
    id: 'CA005',
    purpose: 'Training workshop materials and venue rental for s...',
    amount: 18000,
    status: 'disbursed',
    requested: '10/5/2024'
  },
  {
    id: 'CA006',
    purpose: 'Emergency repair tools and equipment maintenance',
    amount: 3500,
    status: 'rejected',
    requested: '9/12/2024'
  }
]

const adminCashAdvanceRows = [
  {
    id: 'CA001',
    fileDescription: 'Client_Meeting_Expenses.pdf',
    purpose: 'Client meeting expenses for Cebu business trip',
    requested: '1/10/2025',
    dueDate: '1/25/2025',
    amount: 15000,
    outstanding: 0,
    user: 'Juan Dela Cruz',
    initials: 'JD',
    status: 'approved'
  },
  {
    id: 'CA002',
    fileDescription: 'Med_Supplies_Demo.zip',
    purpose: 'Medical equipment demonstration supplies',
    requested: '1/28/2025',
    dueDate: '2/15/2025',
    amount: 8000,
    outstanding: 8000,
    user: 'Maria Clara',
    initials: 'MC',
    status: 'pending'
  },
  {
    id: 'CA003',
    fileDescription: 'Conference_Reg.pdf',
    purpose: 'Conference registration and accommodation',
    requested: '12/15/2024',
    dueDate: '1/05/2025',
    amount: 12500,
    outstanding: 0,
    user: 'Jose Rizal',
    initials: 'JR',
    status: 'liquidated'
  },
  {
    id: 'CA004',
    fileDescription: 'Office_Supplies_Q1.pdf',
    purpose: 'Office supplies and printing materials',
    requested: '11/20/2024',
    dueDate: '12/10/2024',
    amount: 5000,
    outstanding: 5000,
    user: 'Leonor Rivera',
    initials: 'LR',
    status: 'rejected'
  },
  {
    id: 'CA005',
    fileDescription: 'Training_Materials.pdf',
    purpose: 'Training workshop materials and venue rental',
    requested: '10/05/2024',
    dueDate: '10/20/2024',
    amount: 18000,
    outstanding: 18000,
    user: 'Andres Bonifacio',
    initials: 'AB',
    status: 'unliquidated'
  },
  {
    id: 'CA006',
    fileDescription: 'Emergency_Repair_Tools.pdf',
    purpose: 'Emergency repair tools and equipment maintenance',
    requested: '9/12/2024',
    dueDate: '9/30/2024',
    amount: 3500,
    outstanding: 3500,
    user: 'Gabriela Silang',
    initials: 'GS',
    status: 'granted'
  }
]

const filteredRows = computed(() => {
  if (activeStatus.value === 'All') return cashAdvanceRows
  return cashAdvanceRows.filter(row => row.status === activeStatus.value.toLowerCase())
})

const adminMetrics = computed(() => {
  const outstandingRows = adminCashAdvanceRows.filter(row => row.outstanding > 0)

  return {
    pending: adminCashAdvanceRows.filter(row => row.status === 'pending').length,
    approved: adminCashAdvanceRows.filter(row => row.status === 'approved').length,
    rejected: adminCashAdvanceRows.filter(row => row.status === 'rejected').length,
    outstanding: outstandingRows.reduce((sum, row) => sum + row.outstanding, 0),
    outstandingEmployees: outstandingRows.length
  }
})

function formatPeso(value) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP'
  }).format(value)
}

function statusClass(status) {
  const classes = {
    unliquidated: 'bg-amber-50 text-amber-700 border border-amber-200',
    liquidated: 'bg-blue-50 text-blue-700 border border-blue-200',
    pending: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    granted: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
    approved: 'bg-success text-white border border-success',
    disbursed: 'bg-[#F0FDFA] text-[#0D9488] border border-teal-100',
    rejected: 'bg-[#FEF2F2] text-[#B91C1C] border border-red-200'
  }
  return classes[status] || 'bg-slate-100 text-slate-600'
}

function openDetails(row) {
  viewingRecord.value = {
    id: row.id,
    purpose: row.purpose.replace(/\s\.\.\.$|\.\.\.$/, ''),
    amount: row.amount,
    balance: row.outstanding ?? (['approved', 'disbursed', 'granted', 'unliquidated'].includes(row.status) ? row.amount : 0),
    status: row.status,
    date: row.requested,
    updatedAt: row.status === 'pending' ? row.requested : '11/01/2025',
    requestedBy: row.user || auth.user?.name || 'Employee',
    dueDate: row.dueDate || (row.status === 'pending' || row.status === 'rejected' ? '--' : '02/15/2025'),
    documentFileName: row.fileDescription || `Cash_Advance_Request_${row.id}.pdf`,
    adminNotes: row.status === 'rejected' ? 'Request rejected. Please review the submitted business purpose and documents.' : 'Approved for business trip'
  }
  clearSignature()
}

async function handleRequest() {
  if (!form.purpose || !form.amount || !form.dueDate) return
  submitting.value = true
  await store.request({ ...form, requestedBy: auth.user?.name })
  submitting.value = false
  showModal.value = false
  Object.assign(form, { purpose: '', amount: '', dueDate: '' })
}

const rejectingId = ref(null)
const rejectionType = ref('')
const rejectionComment = ref('')

const viewingRecord = ref(null)
const signatureCanvas = ref(null)
const isSigning = ref(false)
const signatureStarted = ref(false)
const adminReviewNotes = ref('')
const confirmationAction = ref('')

function closeDetails() {
  viewingRecord.value = null
  adminReviewNotes.value = ''
  confirmationAction.value = ''
  clearSignature()
}

function requestConfirmation(action) {
  confirmationAction.value = action
}

function cancelConfirmation() {
  confirmationAction.value = ''
}

function confirmAdminDecision() {
  if (!viewingRecord.value) return

  viewingRecord.value.status = confirmationAction.value === 'approve' ? 'approved' : 'rejected'
  viewingRecord.value.adminNotes = adminReviewNotes.value || 'No notes provided.'
  confirmationAction.value = ''
  closeDetails()
}

function statusPillClass(status) {
  const classes = {
    unliquidated: 'bg-amber-50 text-amber-700 border-amber-200',
    liquidated: 'bg-blue-50 text-blue-700 border-blue-200',
    granted: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    approved: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
    disbursed: 'bg-[#DCFCE7] text-[#166534] border-[#BBF7D0]',
    pending: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
    rejected: 'bg-[#FEE2E2] text-[#991B1B] border-[#FECACA]'
  }
  return classes[status] || 'bg-slate-100 text-slate-600 border-slate-200'
}

function outstandingBalance(record) {
  if (typeof record.balance === 'number') return record.balance
  return ['approved', 'disbursed'].includes(record.status) ? record.amount : 0
}

function formatDetailDate(value, fallbackTime = '09:00:00') {
  if (!value) return '--'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return `${value}, ${fallbackTime}`

  return new Intl.DateTimeFormat('en-PH', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  }).format(date)
}

function prepareSignatureCanvas() {
  const canvas = signatureCanvas.value
  if (!canvas) return null

  const rect = canvas.getBoundingClientRect()
  const scale = window.devicePixelRatio || 1

  if (canvas.width !== Math.floor(rect.width * scale) || canvas.height !== Math.floor(rect.height * scale)) {
    canvas.width = Math.floor(rect.width * scale)
    canvas.height = Math.floor(rect.height * scale)
    const resizedContext = canvas.getContext('2d')
    resizedContext.scale(scale, scale)
    resizedContext.lineWidth = 2
    resizedContext.lineCap = 'round'
    resizedContext.lineJoin = 'round'
    resizedContext.strokeStyle = '#14532D'
  }

  return canvas.getContext('2d')
}

function signaturePoint(event) {
  const rect = signatureCanvas.value.getBoundingClientRect()
  return {
    x: event.clientX - rect.left,
    y: event.clientY - rect.top
  }
}

function startSignature(event) {
  const context = prepareSignatureCanvas()
  if (!context) return

  isSigning.value = true
  signatureStarted.value = true
  signatureCanvas.value.setPointerCapture?.(event.pointerId)

  const point = signaturePoint(event)
  context.beginPath()
  context.moveTo(point.x, point.y)
}

function drawSignature(event) {
  if (!isSigning.value) return
  const context = prepareSignatureCanvas()
  if (!context) return

  const point = signaturePoint(event)
  context.lineTo(point.x, point.y)
  context.stroke()
}

function stopSignature() {
  isSigning.value = false
}

function clearSignature() {
  const canvas = signatureCanvas.value
  if (!canvas) {
    signatureStarted.value = false
    return
  }

  const context = canvas.getContext('2d')
  context.clearRect(0, 0, canvas.width, canvas.height)
  signatureStarted.value = false
}

async function quickApproveAdvance(id) { await store.approveRequest(id) }
async function quickApproveSettlement(id) { await store.approveSettlement(id) }

function openRejectModal(id, type) {
  rejectingId.value = id
  rejectionType.value = type
  rejectionComment.value = ''
}

function cancelReject() {
  rejectingId.value = null
  rejectionType.value = ''
  rejectionComment.value = ''
}

async function confirmReject() {
  if (rejectionComment.value.length < 10) return
  if (rejectionType.value === 'advance') {
    await store.rejectRequest(rejectingId.value)
  } else if (rejectionType.value === 'settlement') {
    await store.rejectSettlement(rejectingId.value)
  }
  cancelReject()
}
</script>

<template>
  <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 font-sans">
    <!-- Page Header -->
    <section class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Wallet class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">Advance Requests</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Cash Advance
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          {{ auth.isAdmin ? 'Review and manage employee cash advance activity' : 'Request and track cash advance payments' }}
        </p>
      </div>

      <button
        v-if="!auth.isAdmin"
        id="request-advance-btn"
        class="inline-flex min-h-[44px] w-fit items-center justify-center gap-2 rounded-lg bg-accent px-6 py-3 font-heading text-sm font-bold text-white shadow-sm transition-all duration-200 ease-out hover:bg-accent-600 hover:shadow-card-hover hover:scale-[1.01] active:scale-[0.98]"
        @click="showModal = true"
      >
        <Plus class="h-4 w-4" />
        New Request
      </button>
    </section>

    <!-- Admin Analytics Metrics -->
    <section v-if="auth.isAdmin" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="kpi-card border-l-2 border-l-warning">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Pending Advances</span>
          <Activity class="h-5 w-5 text-amber-500/35" />
        </div>
        <p class="kpi-value">
          {{ adminMetrics.pending }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-success">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Approved Advances</span>
          <ShieldCheck class="h-5 w-5 text-success/35" />
        </div>
        <p class="kpi-value">
          {{ adminMetrics.approved }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-danger">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Rejected Advances</span>
          <X class="h-5 w-5 text-danger/35" />
        </div>
        <p class="kpi-value">
          {{ adminMetrics.rejected }}
        </p>
      </div>

      <div class="kpi-card border-l-2 border-l-primary">
        <div class="mb-4 flex items-center justify-between">
          <span class="kpi-label">Total Outstanding Balance</span>
          <Wallet class="h-5 w-5 text-blue-900/35" />
        </div>
        <p class="kpi-value">
          {{ formatPeso(adminMetrics.outstanding) }}
        </p>
        <p class="kpi-label mt-1 normal-case tracking-normal">
          (Total employees with outstanding balance: {{ adminMetrics.outstandingEmployees }})
        </p>
      </div>
    </section>

    <!-- Admin Management Data Table -->
    <section v-if="auth.isAdmin" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-1 border-b border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-heading text-base font-bold leading-tight text-slate-800">Cash Advance Management</h2>
          <p class="mt-0.5 text-xs text-slate-400">Administrative review queue</p>
        </div>
        <span class="kpi-label text-slate-400">Showing {{ adminCashAdvanceRows.length }} records</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[1180px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">ID</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">File Description</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Purpose</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Date Requested</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Due Date</th>
              <th class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Amount</th>
              <th class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Outstanding</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">User</th>
              <th class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Status</th>
              <th class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in adminCashAdvanceRows"
              :key="row.id"
              class="whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
            >
              <td class="px-5 py-5 font-mono text-sm font-bold text-slate-900">{{ row.id }}</td>
              <td class="max-w-[170px] px-5 py-5 text-sm text-slate-500">
                <span class="block truncate">{{ row.fileDescription }}</span>
              </td>
              <td class="max-w-[220px] px-5 py-5 text-sm text-slate-600">
                <span class="block truncate">{{ row.purpose }}</span>
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">{{ row.requested }}</td>
              <td class="px-5 py-5 text-sm text-slate-500">{{ row.dueDate }}</td>
              <td class="px-5 py-5 text-right text-sm font-bold text-primary">{{ formatPeso(row.amount) }}</td>
              <td class="px-5 py-5 text-right text-sm font-semibold text-slate-600">{{ formatPeso(row.outstanding) }}</td>
              <td class="px-5 py-5">
                <div class="flex items-center gap-2">
                  <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                    {{ row.initials }}
                  </span>
                  <span class="text-sm font-medium text-slate-700">{{ row.user }}</span>
                </div>
              </td>
              <td class="px-5 py-5 text-center">
                <span :class="['inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide', statusClass(row.status)]">
                  {{ row.status }}
                </span>
              </td>
              <td class="px-5 py-5 text-center">
                <button
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-accent/15 bg-accent/5 text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.04] focus:outline-none"
                  title="View cash advance"
                  @click="openDetails(row)"
                >
                  <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-accent/30">
                    <Eye class="h-3.5 w-3.5" />
                  </span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Filter Status Tabs -->
    <section v-if="!auth.isAdmin" class="overflow-x-auto">
      <div class="flex w-fit items-center gap-1 rounded-xl border border-black/5 bg-white/70 p-1 shadow-sm">
        <button
          v-for="tab in statusTabs"
          :key="tab"
          class="rounded-lg px-5 py-2 text-sm font-semibold transition-all duration-200 ease-out"
          :class="activeStatus === tab
            ? 'bg-primary text-white shadow-sm'
            : 'text-slate-700 hover:bg-accent-50 hover:text-accent-700'"
          @click="activeStatus = tab"
        >
          {{ tab }}
        </button>
      </div>
    </section>

    <!-- Cash Advance Data Table Module -->
    <section v-if="!auth.isAdmin" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[880px] border-collapse text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">ID</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Purpose</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Amount</th>
              <th class="px-5 py-4 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Status</th>
              <th class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Requested</th>
              <th class="px-5 py-4 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in filteredRows"
              :key="row.id"
              class="group transition-colors duration-200 ease-out hover:bg-slate-50/80"
            >
              <td class="px-5 py-5 font-mono text-sm font-bold text-slate-900">
                {{ row.id }}
              </td>
              <td class="max-w-sm px-5 py-5 text-sm text-slate-600">
                <span class="block truncate">{{ row.purpose }}</span>
              </td>
              <td class="px-5 py-5 text-sm font-bold text-primary">
                {{ formatPeso(row.amount) }}
              </td>
              <td class="px-5 py-5 text-center">
                <span
                  :class="['inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase', statusClass(row.status)]"
                >
                  {{ row.status }}
                </span>
              </td>
              <td class="px-5 py-5 text-sm text-slate-500">
                {{ row.requested }}
              </td>
              <td class="px-5 py-5 text-right">
                <button
                  class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-accent/15 bg-accent/5 text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.04] focus:outline-none"
                  title="View cash advance"
                  @click="openDetails(row)"
                >
                  <span class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-accent/30">
                    <Eye class="h-3.5 w-3.5" />
                  </span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Component Header -->
    <div v-if="false" class="flex items-end justify-between border-b border-slate-200 pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <Wallet class="w-3.5 h-3.5 text-primary" />
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-700">Section: Cash Advances</span>
        </div>
        <h1 class="text-xl font-bold text-primary uppercase tracking-widest">Cash Advance Requests</h1>
        <p class="text-[10px] font-bold text-slate-700 uppercase tracking-wider mt-1">Manage your advance requests</p>
      </div>
      <BaseButton id="request-advance-btn" variant="cta" @click="showModal = true">
        <Plus class="w-5 h-5 mr-1" /> NEW REQUEST
      </BaseButton>
    </div>

    <!-- Summary Telemetry -->
    <div v-if="false" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="kpi-card border-l-2 border-l-primary">
        <div class="flex items-center justify-between mb-4">
          <span class="text-[9px] font-mono font-bold text-slate-600 tracking-tighter">SUMMARY</span>
          <Activity class="w-4 h-4 text-primary" />
        </div>
        <p class="kpi-value text-2xl text-primary">₱{{ store.totalOutstanding.toLocaleString() }}</p>
        <p class="kpi-label mt-1 opacity-80 uppercase tracking-widest text-[9px]">Total Outstanding</p>
      </div>
      <div class="kpi-card border-l-2 border-l-warning">
        <div class="flex items-center justify-between mb-4">
          <span class="text-[9px] font-mono font-bold text-slate-600 tracking-tighter">AWAITING</span>
          <ShieldAlert class="w-4 h-4 text-warning" />
        </div>
        <p class="kpi-value text-2xl text-warning">{{ store.pendingCount }}</p>
        <p class="kpi-label mt-1 opacity-80 uppercase tracking-widest text-[9px]">Pending Requests</p>
      </div>
      <div class="kpi-card border-l-2 border-l-danger">
        <div class="flex items-center justify-between mb-4">
          <span class="text-[9px] font-mono font-bold text-slate-600 tracking-tighter">ALERTS</span>
          <FileMinus class="w-4 h-4 text-danger" />
        </div>
        <p class="kpi-value text-2xl text-danger">1</p>
        <p class="kpi-label mt-1 opacity-80 uppercase tracking-widest text-[9px]">Overdue Settlements</p>
      </div>
    </div>

    <!-- Allocation Spreadsheet -->
    <BaseTable v-if="false" :columns="columns" :rows="store.items" :loading="store.isLoading" :page-size="10" @row-click="viewingRecord = $event">
      <template #cell-amount="{ value }">
        <span class="font-bold">₱{{ value.toLocaleString() }}</span>
      </template>
      <template #cell-balance="{ value }">
        <span :class="['font-bold font-mono', value > 0 ? 'text-danger' : 'text-success']">
          ₱{{ value.toLocaleString() }}
        </span>
      </template>
      <template #cell-status="{ value }">
        <StatusBadge :status="value" />
      </template>

      <template #cell-actions="{ row }">
        <div v-if="auth.isAdmin && row.status === 'submitted'" class="flex gap-1" @click.stop>
          <button class="btn btn-secondary btn-sm !text-success !border-success/20 hover:!bg-success/5" @click="quickApproveAdvance(row.id)">
            APPROVE
          </button>
          <button class="btn btn-secondary btn-sm !text-danger !border-danger/20 hover:!bg-danger/5" @click="openRejectModal(row.id, 'advance')">
            REJECT
          </button>
        </div>
        <div v-else-if="auth.isAdmin && row.status === 'pending'" class="flex gap-1" @click.stop>
          <button class="btn btn-secondary btn-sm !text-success !border-success/20 hover:!bg-success/5" @click="quickApproveSettlement(row.id)">
            APPROVE
          </button>
          <button class="btn btn-secondary btn-sm !text-danger !border-danger/20 hover:!bg-danger/5" @click="openRejectModal(row.id, 'settlement')">
            REJECT
          </button>
        </div>
        <div v-else class="flex justify-center">
          <ShieldAlert class="w-3.5 h-3.5 text-slate-200" />
        </div>
      </template>
    </BaseTable>

    <!-- New Request Workspace -->
    <Transition name="slide-up">
      <div v-if="showModal" class="fixed inset-0 z-[60] flex flex-col overflow-hidden bg-clinical">
        <header
          class="sticky top-0 z-10 flex flex-shrink-0 items-center gap-3 px-6 py-3 text-white shadow-sm"
          style="background: linear-gradient(135deg, #252578 0%, #2F2F7E 100%);"
        >
          <button
            class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
            type="button"
            title="Close request form"
            @click="showModal = false"
          >
            <X class="h-4 w-4" />
          </button>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-white/60">
              Cash Advances
            </p>
            <h2 class="font-heading text-sm font-bold leading-tight text-white">
              New Cash Advance Request
            </h2>
          </div>
        </header>

        <main class="flex-1 overflow-y-auto">
          <div class="mx-auto flex w-full max-w-5xl flex-col gap-8 p-6">
          <section class="card p-5 md:p-6">
            <form id="cashAdvanceForm" class="space-y-6" @submit.prevent="handleRequest">
              <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="input-wrapper">
                  <label class="input-label" for="ca-amount">Amount Requested *</label>
                  <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-mono text-slate-400">PHP</span>
                    <input
                      id="ca-amount"
                      v-model="form.amount"
                      class="input !pl-14 text-base"
                      min="0"
                      placeholder="0.00"
                      type="number"
                    />
                  </div>
                </div>

                <div class="input-wrapper">
                  <label class="input-label" for="ca-due">Due Date *</label>
                  <div class="relative">
                    <input
                      id="ca-due"
                      v-model="form.dueDate"
                      class="input !pr-12 text-base"
                      type="date"
                    />
                    <Calendar class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  </div>
                </div>
              </div>

              <div class="input-wrapper">
                <label class="input-label" for="ca-purpose">Purpose *</label>
                <textarea
                  id="ca-purpose"
                  v-model="form.purpose"
                  class="input min-h-[132px] resize-none text-base leading-relaxed"
                  placeholder="Describe the purpose of this cash advance request..."
                  rows="4"
                />
              </div>

              <div class="input-wrapper">
                <label class="input-label">Request Document (Required) *</label>
                <button
                  class="group flex w-full flex-col items-center justify-between gap-4 rounded-lg border-2 border-dashed border-slate-200 bg-slate-50/80 p-6 text-left transition-all duration-200 ease-out hover:border-accent/30 hover:bg-accent-50/50 md:flex-row"
                  type="button"
                >
                  <div class="flex flex-col items-center gap-4 md:flex-row">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm transition-colors group-hover:text-accent">
                      <UploadCloud class="h-6 w-6" />
                    </div>
                    <div class="text-center md:text-left">
                      <h3 class="font-heading text-base font-bold text-slate-800">
                        No file selected
                      </h3>
                      <p class="text-sm text-slate-500">
                        Upload your cash advance request form (PDF, DOC, DOCX)
                      </p>
                    </div>
                  </div>
                  <span class="rounded-md border border-black/5 bg-white px-5 py-2 text-sm font-bold text-primary shadow-sm transition-colors group-hover:border-accent/20 group-hover:text-accent">
                    Browse
                  </span>
                </button>
                <p class="mt-1 flex items-center gap-1 text-xs font-semibold text-danger">
                  <Info class="h-3.5 w-3.5" />
                  Request document is required to process your cash advance.
                </p>
              </div>

              <div class="input-wrapper">
                <label class="input-label">Attached Files (Preview)</label>
                <div class="flex flex-col items-center justify-between gap-4 rounded-lg border border-accent/15 bg-accent/5 p-4 md:flex-row">
                  <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-accent/15 text-accent">
                      <FileText class="h-5 w-5" />
                    </div>
                    <div>
                      <h4 class="text-sm font-bold text-primary">
                        Cash_Advance_Request_V1.pdf
                      </h4>
                      <p class="text-xs text-slate-500">
                        Uploaded on Oct 24, 2023 - 1.2 MB
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-4">
                    <button class="inline-flex items-center gap-1 text-xs font-bold text-accent hover:underline" type="button">
                      <RefreshCw class="h-3.5 w-3.5" />
                      Change File
                    </button>
                    <button class="inline-flex items-center gap-1 text-xs font-bold text-danger hover:underline" type="button">
                      <Trash2 class="h-3.5 w-3.5" />
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </form>
          </section>

          <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="rounded-lg border border-accent/20 bg-accent-50 p-5">
              <div class="mb-4 flex items-start gap-3">
                <Info class="mt-0.5 h-5 w-5 text-accent" />
                <h3 class="font-heading text-base font-bold text-accent-800">
                  Important Information
                </h3>
              </div>
              <ul class="list-inside list-disc space-y-2 text-sm leading-relaxed text-accent-800">
                <li>Cash advance requests are subject to approval by the accounting department</li>
                <li>Approved amounts will be disbursed within 3-5 business days</li>
                <li>You must submit reimbursement with receipts after using the cash advance</li>
                <li>Unused amounts must be returned to the company</li>
              </ul>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
              <div class="mb-4 flex items-start gap-3">
                <MessageSquare class="mt-0.5 h-5 w-5 text-amber-600" />
                <h3 class="font-heading text-base font-bold text-amber-900">
                  Admin Notes / Instructions
                </h3>
              </div>
              <div class="rounded-md border border-amber-100 bg-white/60 p-3 text-sm italic leading-relaxed text-amber-800">
                "Please ensure all travel-related requests include the project code in the purpose field to avoid delays in processing."
              </div>
              <div class="mt-4 flex items-center gap-2 text-xs font-semibold text-amber-700">
                <ShieldAlert class="h-3.5 w-3.5" />
                Last updated by Finance Admin
              </div>
            </div>
          </section>

          <footer class="flex flex-col items-center justify-end gap-3 border-t border-black/5 pt-6 sm:flex-row">
            <button
              class="btn btn-secondary w-full px-8 py-3 sm:w-auto"
              type="button"
              @click="showModal = false"
            >
              Cancel
            </button>
            <button
              id="submit-advance-btn"
              class="btn btn-primary w-full px-8 py-3 sm:w-auto"
              :disabled="submitting || !form.purpose || !form.amount || !form.dueDate"
              form="cashAdvanceForm"
              type="submit"
            >
              <Send class="h-4 w-4" />
              {{ submitting ? 'Submitting...' : 'Submit Request' }}
            </button>
          </footer>
          </div>
        </main>
      </div>
    </Transition>

    <!-- Rejection Modal -->
    <div v-if="rejectingId" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 backdrop-blur-sm p-4">
      <div class="w-full max-w-md overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl transform transition-all">
        <div class="border-b border-slate-200 bg-slate-50/80 px-5 py-4 flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-danger">
            <Activity class="w-4 h-4" />
          </div>
          <h3 class="font-heading text-sm font-bold text-slate-800">
            Reject {{ rejectionType === 'advance' ? 'Advance Request' : 'Liquidation' }}
          </h3>
        </div>
        <div class="p-5 flex flex-col gap-4">
          <p class="text-sm font-medium text-slate-600">
            Please provide a valid justification for rejecting Ref #{{ rejectingId }}.
          </p>
          <div class="input-wrapper">
            <textarea 
              v-model="rejectionComment" 
              rows="3" 
              class="input !font-sans resize-none" 
              :class="rejectionComment.length > 0 && rejectionComment.length < 10 ? 'border-danger focus:border-danger focus:ring-danger' : ''"
              placeholder="REJECTION REASON (MIN 10 CHARACTERS)" 
            />
            <div class="text-[10px] font-bold uppercase tracking-widest flex justify-between mt-1"
                 :class="rejectionComment.length < 10 ? 'text-danger' : 'text-success'">
              <span>Requirement: >= 10 Chars</span>
              <span>{{ rejectionComment.length }} / 10+</span>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 mt-2">
            <BaseButton variant="secondary" @click="cancelReject">CANCEL</BaseButton>
            <BaseButton variant="primary" :disabled="rejectionComment.length < 10" class="!bg-danger !border-danger" @click="confirmReject">
              CONFIRM REJECTION
            </BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Cash Advance Details Modal -->
    <div v-if="viewingRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 p-4 backdrop-blur-sm">
      <div v-if="auth.isAdmin" class="flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-6 py-4">
          <h2 class="font-heading text-xl font-bold text-primary">Review Cash Advance Request</h2>
          <button
            class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
            type="button"
            title="Close review"
            @click="closeDetails"
          >
            <X class="h-5 w-5 stroke-[1.75]" />
          </button>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto bg-slate-50/40 px-6 py-5">
          <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
              <div class="flex h-14 w-14 items-center justify-center rounded-full border-2 border-emerald-100 bg-primary/10 font-heading text-sm font-bold text-primary">
                {{ viewingRecord.requestedBy?.split(' ').map(part => part[0]).join('').slice(0, 2) || 'EA' }}
              </div>
              <div>
                <p class="section-label mb-1">Requestor Name</p>
                <h3 class="font-heading text-xl font-bold text-primary">{{ viewingRecord.requestedBy }}</h3>
              </div>
            </div>

            <div class="sm:text-right">
              <p class="section-label mb-2">Status</p>
              <span :class="['inline-flex items-center rounded-full border px-4 py-1.5 text-xs font-bold uppercase tracking-wide', statusPillClass(viewingRecord.status)]">
                {{ viewingRecord.status === 'pending' ? 'Pending Review' : viewingRecord.status }}
              </span>
            </div>
          </section>

          <section class="rounded-lg border border-emerald-100 bg-primary/5 p-6 text-center">
            <p class="section-label mb-2">Amount Requested</p>
            <p class="font-heading text-[40px] font-extrabold leading-tight text-primary">
              {{ formatPeso(viewingRecord.amount) }}
            </p>
          </section>

          <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-lg border border-slate-200 bg-white p-4 md:col-span-2">
              <p class="section-label mb-1">Purpose</p>
              <p class="text-base leading-relaxed text-slate-800">{{ viewingRecord.purpose }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
              <p class="section-label mb-1">Date Requested</p>
              <p class="text-base font-bold text-slate-800">{{ viewingRecord.date }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4">
              <p class="section-label mb-1">Settlement Due Date</p>
              <p class="text-base font-bold text-slate-800">{{ viewingRecord.dueDate }}</p>
            </div>
          </section>

          <section class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-100 px-4 py-3">
            <div class="flex items-center gap-3">
              <Wallet class="h-5 w-5 text-accent" />
              <span class="text-sm font-semibold text-slate-700">Current Outstanding Balance</span>
            </div>
            <span class="font-heading text-xl font-bold text-primary">{{ formatPeso(outstandingBalance(viewingRecord)) }}</span>
          </section>

          <section class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex min-w-0 items-center gap-4">
              <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md bg-red-50 text-danger">
                <FileDown class="h-6 w-6" />
              </span>
              <div class="min-w-0">
                <p class="truncate font-heading text-sm font-bold text-primary">{{ viewingRecord.documentFileName }}</p>
                <p class="text-xs font-semibold text-slate-400">2.4 MB - Signed & Verified</p>
              </div>
            </div>
            <button
              class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-primary transition-colors hover:bg-slate-200"
              type="button"
              title="Download request document"
            >
              <Download class="h-5 w-5" />
            </button>
          </section>

          <section class="rounded-r-lg border-l-4 border-accent bg-accent/10 p-4">
            <div class="mb-2 flex items-center gap-2">
              <Info class="h-4 w-4 text-accent" />
              <h3 class="font-heading text-sm font-bold text-accent">Important Information Guidelines</h3>
            </div>
            <ul class="list-inside list-disc space-y-1 text-sm text-slate-700">
              <li>Advances over PHP 10,000 require Department Head digital countersign.</li>
              <li>Liquidation must be submitted within 5 business days post-settlement.</li>
              <li>Unliquidated advances will be deducted from next payroll cycle.</li>
            </ul>
          </section>

          <section class="space-y-2">
            <p class="section-label">Employee Signature Verification Pad</p>
            <div class="relative flex h-32 w-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-slate-200 bg-white">
              <span class="font-heading text-3xl font-bold italic text-primary/75">{{ viewingRecord.requestedBy }}</span>
              <div class="absolute bottom-2 right-3 flex items-center gap-1 text-success">
                <ShieldCheck class="h-4 w-4" />
                <span class="text-[10px] font-bold uppercase tracking-widest">Digitally Verified</span>
              </div>
            </div>
          </section>

          <section class="space-y-2 pb-2">
            <label class="section-label" for="adminReviewNotes">Add Admin Notes / Instructions</label>
            <textarea
              id="adminReviewNotes"
              v-model="adminReviewNotes"
              class="input min-h-[96px] resize-none !font-sans"
              placeholder="Enter comments or reason for decision..."
            />
          </section>
        </div>

        <footer class="flex flex-col gap-3 border-t border-slate-200 bg-white p-5 sm:flex-row sm:justify-end">
          <button
            class="btn btn-secondary w-full !border-danger/30 !text-danger hover:!bg-danger/5 sm:w-auto"
            type="button"
            @click="requestConfirmation('reject')"
          >
            Reject
          </button>
          <button
            class="btn btn-primary w-full sm:w-auto"
            type="button"
            @click="requestConfirmation('approve')"
          >
            Approve
          </button>
        </footer>
      </div>

      <div v-else class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
          <h2 class="font-heading text-xl font-bold text-[#003527]">
            {{ auth.isAdmin ? 'Admin Details Review' : 'Cash Advance Details' }}
          </h2>
          <button
            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
            type="button"
            title="Close details"
            @click="closeDetails"
          >
            <X class="h-5 w-5 stroke-[1.75]" />
          </button>
        </header>

        <div class="flex-1 space-y-6 overflow-y-auto px-6 py-5">
          <section class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="space-y-5">
              <div>
                <p class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Request ID</p>
                <p class="font-heading text-xl font-bold text-[#003527]">{{ viewingRecord.id }}</p>
              </div>
              <div>
                <p class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Amount Requested</p>
                <p class="font-heading text-3xl font-bold leading-tight text-[#006C49]">{{ formatPeso(viewingRecord.amount) }}</p>
              </div>
            </div>

            <div class="flex flex-col items-start sm:items-end">
              <p class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-500">Status</p>
              <span :class="['inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-wide', statusPillClass(viewingRecord.status)]">
                {{ viewingRecord.status }}
              </span>
            </div>
          </section>

          <section class="space-y-5 rounded-lg border border-slate-200 bg-slate-50/60 p-5">
            <div>
              <p class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Purpose</p>
              <p class="text-sm font-medium leading-relaxed text-slate-800">{{ viewingRecord.purpose }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <div>
                <p class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Current Outstanding Balance</p>
                <p class="font-heading text-lg font-bold text-[#003527]">{{ formatPeso(outstandingBalance(viewingRecord)) }}</p>
              </div>
              <div>
                <p class="mb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">Settlement Due Date</p>
                <p class="text-sm font-semibold text-slate-800">{{ viewingRecord.dueDate }}</p>
              </div>
            </div>

            <div>
              <p class="mb-2 text-[11px] font-bold uppercase tracking-widest text-slate-500">Request Document</p>
              <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-100 px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md bg-red-50 text-danger">
                    <FileDown class="h-5 w-5" />
                  </span>
                  <p class="truncate text-sm font-semibold text-slate-800">{{ viewingRecord.documentFileName }}</p>
                </div>
                <button
                  class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-[#006C49] transition-colors hover:bg-[#006C49]/10"
                  type="button"
                  title="Download request document"
                >
                  <Download class="h-5 w-5" />
                </button>
              </div>
            </div>
          </section>

          <section>
            <h3 class="mb-2 font-heading text-sm font-bold text-[#003527]">Admin Notes</h3>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
              <p class="text-sm font-medium leading-relaxed text-slate-800">{{ viewingRecord.adminNotes }}</p>
            </div>
          </section>

          <section v-if="['approved', 'disbursed'].includes(viewingRecord.status)" class="space-y-4 rounded-lg border border-[#006C49]/15 bg-[#F0FDF4] p-5">
            <div class="flex items-start gap-3">
              <ShieldCheck class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#006C49]" />
              <p class="text-sm leading-relaxed text-slate-800">
                This certifies that I received the cash advance with amount of
                <span class="font-bold text-[#006C49]">{{ formatPeso(viewingRecord.amount) }}</span>.
              </p>
            </div>

            <div>
              <div class="relative h-36 overflow-hidden rounded-lg border border-slate-300 bg-white">
                <canvas
                  ref="signatureCanvas"
                  class="h-full w-full touch-none"
                  @pointerdown="startSignature"
                  @pointermove="drawSignature"
                  @pointerup="stopSignature"
                  @pointerleave="stopSignature"
                  @pointercancel="stopSignature"
                />
                <span v-if="!signatureStarted" class="pointer-events-none absolute inset-0 flex items-center justify-center text-xs font-semibold text-slate-400">
                  Draw your signature here using your mouse
                </span>
              </div>
              <div class="mt-2 flex justify-end">
                <button
                  class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide text-slate-600 transition-colors hover:bg-slate-100"
                  type="button"
                  @click="clearSignature"
                >
                  <RotateCcw class="h-4 w-4" />
                  Clear Signature
                </button>
              </div>
            </div>
          </section>
        </div>

        <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
          <p>
            <span class="font-bold uppercase tracking-widest">Requested Date</span>
            <span class="ml-2 font-semibold text-slate-800">{{ formatDetailDate(viewingRecord.date) }}</span>
          </p>
          <p>
            <span class="font-bold uppercase tracking-widest">Last Updated</span>
            <span class="ml-2 font-semibold text-slate-800">{{ formatDetailDate(viewingRecord.updatedAt, '14:30:00') }}</span>
          </p>
        </footer>
      </div>
    </div>

    <!-- Admin Decision Confirmation -->
    <div
      v-if="confirmationAction"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/35 p-4 backdrop-blur-sm"
    >
      <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 text-center shadow-2xl">
        <div
          :class="[
            'mx-auto flex h-20 w-20 items-center justify-center rounded-full',
            confirmationAction === 'approve' ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'
          ]"
        >
          <ShieldCheck v-if="confirmationAction === 'approve'" class="h-10 w-10" />
          <X v-else class="h-10 w-10" />
        </div>

        <h3 class="mt-6 font-heading text-xl font-bold text-slate-900">
          {{ confirmationAction === 'approve' ? 'Approve Advance Request?' : 'Reject Advance Request?' }}
        </h3>
        <p class="mt-2 text-sm leading-relaxed text-slate-500">
          Confirming this action will finalize the request status for {{ viewingRecord?.id }}.
        </p>

        <div class="mt-6 rounded-lg bg-slate-50 p-4 text-left">
          <p class="section-label mb-1">Final Admin Notes</p>
          <p class="text-sm font-medium italic text-primary">
            {{ adminReviewNotes || 'No notes provided.' }}
          </p>
        </div>

        <div class="mt-6 flex gap-3">
          <button
            class="btn btn-secondary flex-1"
            type="button"
            @click="cancelConfirmation"
          >
            Go Back
          </button>
          <button
            :class="[
              'btn flex-1 text-white',
              confirmationAction === 'approve' ? 'btn-primary' : '!bg-danger !border-danger hover:!bg-red-700'
            ]"
            type="button"
            @click="confirmAdminDecision"
          >
            {{ confirmationAction === 'approve' ? 'Confirm Approval' : 'Confirm Rejection' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Previous record details panel retained inactive for reference -->
    <div v-if="false && viewingRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
      <div class="card p-0 w-full max-w-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-primary text-white px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <FileText class="w-5 h-5" />
            <div>
              <h3 class="text-xs font-bold uppercase tracking-widest">Cash Advance / Settlement Documentation</h3>
              <p class="text-[10px] text-white/60 tracking-wider">REF: {{ viewingRecord.id }}</p>
            </div>
          </div>
          <button class="text-white/50 hover:text-white transition-none" @click="closeDetails"><X class="w-5 h-5" /></button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6 flex flex-col md:flex-row gap-6">
          <div class="flex-1 space-y-4">
               <div>
                 <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">PURPOSE / DESCRIPTION</p>
                 <p class="text-sm font-bold text-slate-800 uppercase">{{ viewingRecord.purpose }}</p>
               </div>
               <div class="grid grid-cols-2 gap-6 pt-2 border-t border-slate-100 mt-2">
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">AMOUNT ISSUED</p>
                   <p class="text-lg font-bold text-primary font-mono tracking-tighter">₱{{ viewingRecord.amount?.toLocaleString() }}</p>
                 </div>
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">CURRENT OUTSTANDING</p>
                   <p :class="['text-lg font-bold font-mono tracking-tighter', viewingRecord.balance > 0 ? 'text-danger' : 'text-success']">
                     ₱{{ viewingRecord.balance?.toLocaleString() }}
                   </p>
                 </div>
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">REQUESTED BY</p>
                   <p class="text-sm font-bold text-slate-700 uppercase">{{ viewingRecord.requestedBy }}</p>
                 </div>
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">SETTLEMENT DUE DATE</p>
                   <p class="text-sm font-bold text-slate-700 uppercase">{{ viewingRecord.dueDate }}</p>
                 </div>
                 <div class="col-span-2 mt-4 flex flex-col items-start gap-1">
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">VERDICT / STATUS</p>
                   <StatusBadge :status="viewingRecord.status" />
                 </div>
               </div>
          </div>
          <div v-if="['pending', 'completed'].includes(viewingRecord.status)" class="w-full md:w-80 border border-slate-200 bg-clinical flex flex-col h-[400px]">
            <div class="p-2 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 bg-primary"></span>
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">SCAN_TARGET: RECEIPT_01.PNG</span>
              </div>
            </div>
            <div class="flex-1 p-2 flex items-center justify-center bg-slate-200/50 overflow-hidden relative group">
              <img src="/mock_receipt.png" alt="Receipt Attachment" class="max-w-full max-h-full object-contain border border-slate-300 shadow-md transform transition-transform duration-300 hover:scale-[1.02]" />
            </div>
          </div>
        </div>

        <div v-if="auth.isAdmin && viewingRecord.status === 'submitted'" class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shadow-inner">
          <button class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6" @click="closeDetails(); openRejectModal(viewingRecord.id, 'advance')">
            REJECT ADVANCE
          </button>
          <button class="btn btn-cta px-6" @click="quickApproveAdvance(viewingRecord.id); closeDetails()">
            APPROVE ADVANCE
          </button>
        </div>
        <div v-else-if="auth.isAdmin && viewingRecord.status === 'pending'" class="p-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3 shadow-inner">
          <button class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6" @click="closeDetails(); openRejectModal(viewingRecord.id, 'settlement')">
            REJECT LIQUIDATION
          </button>
          <button class="btn btn-cta px-6" @click="quickApproveSettlement(viewingRecord.id); closeDetails()">
            APPROVE LIQUIDATION
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.1s linear; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
