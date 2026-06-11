<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useReimbursementStore } from '@/stores/reimbursement'
import { useAuthStore } from '@/stores/auth'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseKpiGrid from '@/components/base/BaseKpiGrid.vue'
import BaseUtilityToolbar from '@/components/base/BaseUtilityToolbar.vue'
import { formatPeso } from '@/utils/formatters'
import { Plus, FileText, Activity, ShieldCheck, X, CheckCircle, XCircle, Clock, Wallet, Send, CreditCard, Eye, Download, ArrowLeft, CalendarDays, Sparkles, MapPin, ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next'

const store = useReimbursementStore()
const auth = useAuthStore()
const router = useRouter()

const rejectingId = ref(null)
const rejectionComment = ref('')
const viewingRecord = ref(null)
const approvingId = ref(null)
const receiptDetailsOpen = ref(false)
const selectedReceipt = ref(null)
const reviewerNotes = ref('')
const pendingReceiptDecision = ref(null)
const isReviewSubmitting = ref(false)
const searchQuery = ref('')
const activeStatus = ref('All')
const activeCategory = ref('All')
const sortKey = ref('')
const sortDirection = ref('asc')

const statusFilters = computed(() =>
  auth.isAdmin
    ? ['All', 'Submitted', 'Pending', 'Approved', 'Rejected', 'Granted']
    : ['All', 'Submitted', 'Pending', 'Approved', 'Rejected', 'Granted'],
)
const employeeReimbursementColumns = [
  { key: 'id', label: 'Id' },
  { key: 'reportDescription', label: 'Report Description' },
  { key: 'cutoffPeriod', label: 'Cutoff Period' },
  { key: 'category', label: 'Category' },
  { key: 'receiptQuantity', label: 'Receipt Quantity', align: 'center' },
  { key: 'quantityReport', label: 'Quantity Report', align: 'center' },
  { key: 'amount', label: 'Amount', align: 'right' },
  { key: 'dateSubmitted', label: 'Date Submitted' },
  { key: 'displayStatus', label: 'Status', align: 'center' },
  { key: 'action', sortKey: 'id', label: 'Action', align: 'center' },
]

const adminReimbursementColumns = [
  { key: 'id', label: 'Id' },
  { key: 'reportDescription', label: 'Report Description' },
  { key: 'cutoffPeriod', label: 'Cutoff Period' },
  { key: 'category', label: 'Category' },
  { key: 'dateSubmitted', label: 'Date Submitted' },
  { key: 'submittedBy', label: 'Submitted By' },
  { key: 'displayStatus', label: 'Status', align: 'center' },
  { key: 'action', sortKey: 'id', label: 'Action', align: 'center' },
]

const reimbursementColumns = computed(() => (auth.isAdmin ? adminReimbursementColumns : employeeReimbursementColumns))
const reimbursementColumnCount = computed(() => reimbursementColumns.value.length)
const reimbursementTableMinWidth = computed(() => (auth.isAdmin ? 'min-w-[1040px]' : 'min-w-[1320px]'))

const reimbursementKpis = computed(() => [
  {
    label: 'Pending',
    value: store.items.filter(item => normalizeStatus(item.status) === 'pending').length,
    sub: 'Awaiting review',
    icon: Clock,
    iconBg: 'bg-amber-500/10',
    iconColor: 'text-amber-500',
    accent: 'from-amber-400 to-amber-600',
  },
  {
    label: 'Approved',
    value: store.items.filter(item => item.status === 'approved').length,
    sub: 'Ready for payment',
    icon: ShieldCheck,
    iconBg: 'bg-emerald-500/10',
    iconColor: 'text-emerald-500',
    accent: 'from-emerald-400 to-emerald-600',
  },
  {
    label: 'Rejected',
    value: store.items.filter(item => normalizeStatus(item.status) === 'rejected').length,
    sub: 'Denied claims',
    icon: XCircle,
    iconBg: 'bg-red-500/10',
    iconColor: 'text-red-500',
    accent: 'from-red-400 to-red-600',
  },
  {
    label: 'Granted',
    value: store.items.filter(item => normalizeStatus(item.status) === 'granted').length,
    sub: 'Settled claims',
    icon: CreditCard,
    iconBg: 'bg-blue-900/10',
    iconColor: 'text-blue-900',
    accent: 'from-blue-900 to-blue-700',
  },
  {
    label: 'Total Amount',
    value: formatPeso(store.total),
    sub: 'All claims',
    icon: Wallet,
    iconBg: 'bg-accent/10',
    iconColor: 'text-accent',
    accent: 'from-accent-400 to-accent',
  },
  {
    label: 'Total Submitted',
    value: store.items.length,
    sub: 'Claim records',
    icon: Send,
    iconBg: 'bg-slate-500/10',
    iconColor: 'text-slate-500',
    accent: 'from-slate-400 to-slate-600',
  },
])

function normalizeStatus(status) {
  const normalized = String(status || '').toLowerCase()
  const statusMap = {
    submitted: 'submitted',
    review: 'pending',
    draft: 'pending',
    reject: 'rejected',
    rejected: 'rejected',
    paid: 'granted',
  }

  return statusMap[normalized] || normalized
}

function statusLabel(status) {
  const labels = {
    submitted: 'Submitted',
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
    granted: 'Granted',
  }

  return labels[normalizeStatus(status)] || 'Submitted'
}

function getCutoffPeriod(date) {
  const submittedDate = new Date(date)
  if (Number.isNaN(submittedDate.getTime())) return date || '--'

  return submittedDate.toLocaleDateString('en-US', {
    month: 'short',
    year: 'numeric',
  })
}

const tableRows = computed(() =>
  store.items.map(item => ({
    ...item,
    originalStatus: item.status,
    reportDescription: item.description,
    cutoffPeriod: getCutoffPeriod(item.date),
    receiptQuantity: Array.isArray(item.receipts) ? item.receipts.length : (Number(item.receipts) || 0),
    quantityReport: 1,
    dateSubmitted: item.date,
    displayStatus: normalizeStatus(item.status),
    displayStatusLabel: statusLabel(item.status),
  }))
)

const categoryFilters = computed(() => [
  'All',
  ...new Set(tableRows.value.map(row => row.category).filter(Boolean)),
])

const receiptTemplates = [
  {
    merchantName: 'Vikings Luxury Buffet',
    location: 'SM Megamall, Mandaluyong City',
    category: 'Food',
    invoicePrefix: 'VIK',
    transactionDate: 'January 5, 2025',
    items: [
      { name: 'Buffet Dinner - 2 pax', quantity: 1, price: 2816 },
      { name: 'Beverages', quantity: 2, price: 384 },
    ],
  },
  {
    merchantName: 'Grab Transport',
    location: 'Makati City',
    category: 'Transportation',
    invoicePrefix: 'GRB',
    transactionDate: 'January 6, 2025',
    items: [
      { name: 'Ride fare', quantity: 1, price: 640 },
      { name: 'Platform fee', quantity: 1, price: 25 },
    ],
  },
  {
    merchantName: 'National Book Store',
    location: 'BGC, Taguig City',
    category: 'Office Supplies',
    invoicePrefix: 'NBS',
    transactionDate: 'January 7, 2025',
    items: [
      { name: 'Paper ream', quantity: 3, price: 780 },
      { name: 'Pens and markers', quantity: 1, price: 420 },
    ],
  },
  {
    merchantName: 'Ace Hardware',
    location: 'Pasig City',
    category: 'Equipment',
    invoicePrefix: 'ACE',
    transactionDate: 'January 8, 2025',
    items: [
      { name: 'Tool kit', quantity: 1, price: 1850 },
      { name: 'Safety gloves', quantity: 2, price: 520 },
    ],
  },
]

const activeReceiptItems = computed(() => (viewingRecord.value ? getReceiptItems(viewingRecord.value) : []))

const filteredTableRows = computed(() => {
  let rows = tableRows.value
  if (activeStatus.value !== 'All') {
    rows = rows.filter(row => row.displayStatus === normalizeStatus(activeStatus.value))
  }
  if (activeCategory.value !== 'All') {
    rows = rows.filter(row => row.category === activeCategory.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    rows = rows.filter(row =>
      [
        row.id,
        row.reportDescription,
        row.cutoffPeriod,
        row.category,
        row.submittedBy,
        row.amount,
        row.dateSubmitted,
        row.displayStatus,
        row.displayStatusLabel,
      ].some(value => String(value || '').toLowerCase().includes(q))
    )
  }
  return rows
})

const sortedTableRows = computed(() => {
  const rows = [...filteredTableRows.value]
  if (!sortKey.value) return rows

  return rows.sort((a, b) => {
    const aValue = getSortValue(a, sortKey.value)
    const bValue = getSortValue(b, sortKey.value)
    if (aValue === bValue) return 0
    const result = aValue > bValue ? 1 : -1
    return sortDirection.value === 'asc' ? result : -result
  })
})

function getSortValue(row, key) {
  const value = row[key]
  if (['amount', 'receiptQuantity', 'quantityReport'].includes(key)) {
    return Number(value || 0)
  }
  if (['dateSubmitted'].includes(key)) {
    const timestamp = new Date(value).getTime()
    return Number.isNaN(timestamp) ? String(value || '').toLowerCase() : timestamp
  }
  return String(value || '').toLowerCase()
}

function toggleSort(column) {
  const key = column.sortKey || column.key
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    return
  }
  sortKey.value = key
  sortDirection.value = 'asc'
}

function isSorted(column) {
  return sortKey.value === (column.sortKey || column.key)
}

function statusClass(status) {
  const classes = {
    approved: 'bg-success text-white border border-success',
    submitted: 'bg-slate-100 text-slate-700 border border-slate-200',
    pending: 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    rejected: 'bg-[#FEF2F2] text-[#B91C1C] border border-red-200',
    granted: 'bg-[#F0FDFA] text-[#0D9488] border border-teal-100',
  }

  return classes[normalizeStatus(status)] || 'bg-slate-100 text-slate-600 border border-slate-200'
}

function closeDetails() {
  viewingRecord.value = null
  receiptDetailsOpen.value = false
  selectedReceipt.value = null
  reviewerNotes.value = ''
  pendingReceiptDecision.value = null
  isReviewSubmitting.value = false
}

function viewReceiptDetails(receipt) {
  selectedReceipt.value = receipt
  reviewerNotes.value = receipt.notes || ''
  pendingReceiptDecision.value = null
  receiptDetailsOpen.value = true
}

function openDetails(row) {
  viewingRecord.value = row
  selectedReceipt.value = null
  reviewerNotes.value = row.reviewerNotes || ''
  pendingReceiptDecision.value = null
  isReviewSubmitting.value = false
  receiptDetailsOpen.value = false
}

function getReceiptItems(record) {
  const receiptCount = Array.isArray(record.receipts) ? record.receipts.length : Number(record.receipts)
  const count = Number.isFinite(receiptCount) && receiptCount > 0 ? receiptCount : 1
  const amount = Number(record.amount) || 0
  const baseAmount = count > 0 ? amount / count : amount

  return Array.from({ length: count }, (_, index) => {
    const template = receiptTemplates[index % receiptTemplates.length]
    const id = `${record.id}-RCPT-${String(index + 1).padStart(2, '0')}`
    const review = record.receiptReviews?.[id] || {}
    const receiptAmount = index === count - 1
      ? amount - (Math.round(baseAmount * 100) / 100) * (count - 1)
      : Math.round(baseAmount * 100) / 100

    return {
      id,
      merchantName: template.merchantName,
      location: template.location,
      category: record.category || template.category,
      invoiceNumber: `${template.invoicePrefix}-${new Date(record.date || Date.now()).getFullYear()}-${String(index + 2345).padStart(6, '0')}`,
      transactionDate: template.transactionDate,
      amount: receiptAmount,
      items: template.items,
      status: review.status || 'pending',
      notes: review.notes || '',
      reviewedAt: review.reviewedAt || '',
    }
  })
}

function requestReceiptDecision(receipt, action) {
  pendingReceiptDecision.value = {
    receiptId: receipt.id,
    action,
  }
}

function cancelReceiptDecision() {
  pendingReceiptDecision.value = null
}

function isReceiptDecisionPending(receipt) {
  return pendingReceiptDecision.value?.receiptId === receipt.id
}

async function confirmReceiptDecision() {
  if (!viewingRecord.value || !pendingReceiptDecision.value) return

  isReviewSubmitting.value = true
  const { receiptId, action } = pendingReceiptDecision.value
  const status = action === 'Approve' ? 'approved' : 'rejected'
  const review = {
    status,
    notes: reviewerNotes.value,
    reviewedAt: new Date().toISOString(),
  }

  await store.setReceiptDecision(viewingRecord.value.id, receiptId, review)
  viewingRecord.value.receiptReviews = {
    ...(viewingRecord.value.receiptReviews || {}),
    [receiptId]: review,
  }
  if (selectedReceipt.value?.id === receiptId) {
    selectedReceipt.value = {
      ...selectedReceipt.value,
      ...review,
    }
  }
  pendingReceiptDecision.value = null
  isReviewSubmitting.value = false
}

onMounted(() => store.fetchAll())

function openApproveModal(id) {
  approvingId.value = id
}

function cancelApprove() {
  approvingId.value = null
}

async function confirmApprove() {
  if (!approvingId.value) return
  await store.approve(approvingId.value)
  cancelApprove()
}

function openRejectModal(id) {
  rejectingId.value = id
  rejectionComment.value = ''
}

function cancelReject() {
  rejectingId.value = null
  rejectionComment.value = ''
}

async function confirmReject() {
  if (rejectionComment.value.length < 10) return
  await store.reject(rejectingId.value)
  cancelReject()
}
</script>

<template>
  <div class="flex flex-col gap-6 font-sans animate-fade-up">

    <!-- ── Page Header ── -->
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Activity class="w-3.5 h-3.5 text-accent" />
          <span class="section-label">Claim Records</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Reimbursements
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Manage and track all submitted expense claims.
        </p>
      </div>
    </div>

    <!-- ── KPI Cards ── -->
    <BaseKpiGrid
      :kpis="reimbursementKpis"
      gridClasses="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4"
      :isLoading="store.isLoading"
      :skeletonCount="6"
    />
    <BaseUtilityToolbar
      v-model:search="searchQuery"
      v-model:status-value="activeStatus"
      v-model:category-value="activeCategory"
      :statuses="statusFilters"
      :categories="categoryFilters"
    >
      <template v-if="!auth.isAdmin" #actions>
        <BaseButton
          id="new-reimbursement-btn"
          variant="cta"
          class="min-h-[42px] w-full sm:w-fit"
          @click="router.push('/reimbursements/new')"
        >
          <Plus class="w-4 h-4" /> New Request
        </BaseButton>
      </template>
    </BaseUtilityToolbar>
    <div v-if="false" class="flex flex-wrap gap-2">
      <div class="flex items-center gap-2 px-3.5 py-2 bg-amber-50 border border-amber-200 rounded-full shadow-sm">
        <div class="w-1.5 h-1.5 bg-amber-400 rounded-full" />
        <span class="text-xs font-semibold text-amber-700"
              style="font-family: 'Open Sans', sans-serif;">
          Pending: {{ store.pending.length }}
        </span>
      </div>
      <div class="flex items-center gap-2 px-3.5 py-2 bg-emerald-50 border border-emerald-200 rounded-full shadow-sm">
        <div class="w-1.5 h-1.5 bg-emerald-400 rounded-full" />
        <span class="text-xs font-semibold text-emerald-700"
              style="font-family: 'Open Sans', sans-serif;">
          Approved: {{ store.approved.length }}
        </span>
      </div>
      <div class="flex items-center gap-2 px-3.5 py-2 bg-primary/5 border border-primary/20 rounded-full shadow-sm">
        <Activity class="w-3 h-3 text-primary" />
        <span class="text-xs font-semibold text-primary"
              style="font-family: 'Open Sans', sans-serif;">
          Total: ₱{{ store.total.toLocaleString() }}
        </span>
      </div>
    </div>

    <!-- ── Main Table ── -->
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      <div class="flex flex-col gap-1 border-b border-slate-200 bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-heading text-base font-bold leading-tight text-slate-800">
            Reimbursement Requests
          </h2>
          <p class="mt-0.5 text-xs text-slate-400">
            Your reimbursement report records
          </p>
        </div>
        <span class="kpi-label text-slate-400">
          <template v-if="store.isLoading">Loading...</template>
          <template v-else>Showing {{ sortedTableRows.length }} records</template>
        </span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left" :class="reimbursementTableMinWidth">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th
                v-for="column in reimbursementColumns"
                :key="column.key"
                class="px-5 py-4 text-[11px] font-bold uppercase tracking-[0.08em]"
                :class="[
                  column.align === 'right' ? 'text-right' : column.align === 'center' ? 'text-center' : 'text-left',
                  isSorted(column) ? 'text-accent' : 'text-slate-500',
                ]"
              >
                <button
                  class="inline-flex items-center gap-1.5 transition-colors hover:text-accent"
                  :class="column.align === 'right' ? 'justify-end' : column.align === 'center' ? 'justify-center' : 'justify-start'"
                  type="button"
                  @click="toggleSort(column)"
                >
                  <span>{{ column.label }}</span>
                  <ChevronUp v-if="isSorted(column) && sortDirection === 'asc'" class="h-3.5 w-3.5" />
                  <ChevronDown v-else-if="isSorted(column)" class="h-3.5 w-3.5" />
                  <ChevronsUpDown v-else class="h-3.5 w-3.5 text-slate-300" />
                </button>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-if="store.isLoading">
              <tr v-for="i in 5" :key="`reimbursement-skeleton-${i}`" class="whitespace-nowrap">
                <td v-for="col in reimbursementColumnCount" :key="col" class="px-5 py-5">
                  <div class="h-4 animate-pulse rounded bg-slate-200" :class="col === reimbursementColumnCount ? 'mx-auto w-9 !h-9 rounded-full' : 'w-24'" />
                </td>
              </tr>
            </template>
            <template v-else-if="sortedTableRows.length === 0">
              <tr>
                <td :colspan="reimbursementColumnCount" class="px-5 py-8 text-center text-sm text-slate-500">
                  No reimbursement records found.
                </td>
              </tr>
            </template>
            <template v-else>
              <tr
                v-for="row in sortedTableRows"
                :key="row.id"
                class="group whitespace-nowrap transition-colors duration-200 ease-out hover:bg-slate-50/80"
              >
                <td class="px-5 py-5 font-mono text-sm font-bold text-slate-900">{{ row.id }}</td>
                <td class="max-w-[240px] px-5 py-5 text-sm text-slate-600">
                  <span class="block truncate">{{ row.reportDescription }}</span>
                </td>
                <td class="px-5 py-5 text-sm text-slate-500">{{ row.cutoffPeriod }}</td>
                <td class="px-5 py-5">
                  <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    {{ row.category }}
                  </span>
                </td>
                <template v-if="!auth.isAdmin">
                  <td class="px-5 py-5 text-center text-sm font-semibold text-slate-600">{{ row.receiptQuantity }}</td>
                  <td class="px-5 py-5 text-center text-sm font-semibold text-slate-600">{{ row.quantityReport }}</td>
                  <td class="px-5 py-5 text-right text-sm font-bold text-primary">{{ formatPeso(row.amount) }}</td>
                </template>
                <td class="px-5 py-5 text-sm text-slate-500">{{ row.dateSubmitted }}</td>
                <td v-if="auth.isAdmin" class="px-5 py-5 text-sm font-semibold text-slate-600">{{ row.submittedBy }}</td>
                <td class="px-5 py-5 text-center">
                  <span
                    :class="[
                      'inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                      statusClass(row.displayStatus),
                    ]"
                  >
                    {{ row.displayStatusLabel }}
                  </span>
                </td>
                <td class="px-5 py-5 text-center">
                  <button
                    class="inline-flex min-h-9 items-center justify-center gap-2 rounded-lg border border-accent/15 bg-accent/5 px-3 text-xs font-bold text-accent transition-all duration-200 ease-out hover:bg-accent/10 hover:scale-[1.02] focus:outline-none"
                    title="View reimbursement"
                    @click="router.push(`/reimbursements/${row.id}`)"
                  >
                    <Eye class="h-3.5 w-3.5" />
                    <span>View</span>
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</template>
