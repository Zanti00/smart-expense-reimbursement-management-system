<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useReimbursementStore } from '@/stores/reimbursement'
import { useAuthStore } from '@/stores/auth'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseKpiGrid from '@/components/base/BaseKpiGrid.vue'
import BasePagination from '@/components/base/BasePagination.vue'
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
const pageSize = 10
const currentPage = ref(1)

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
    accent: 'bg-amber-500',
  },
  {
    label: 'Approved',
    value: store.items.filter(item => item.status === 'approved').length,
    sub: 'Ready for payment',
    icon: ShieldCheck,
    iconBg: 'bg-emerald-500/10',
    iconColor: 'text-emerald-500',
    accent: 'bg-emerald-500',
  },
  {
    label: 'Rejected',
    value: store.items.filter(item => normalizeStatus(item.status) === 'rejected').length,
    sub: 'Denied claims',
    icon: XCircle,
    iconBg: 'bg-red-500/10',
    iconColor: 'text-red-500',
    accent: 'bg-red-500',
  },
  {
    label: 'Granted',
    value: store.items.filter(item => normalizeStatus(item.status) === 'granted').length,
    sub: 'Settled claims',
    icon: CreditCard,
    iconBg: 'bg-blue-900/10',
    iconColor: 'text-blue-900',
    accent: 'bg-blue-900',
  },
  {
    label: 'Total Amount',
    value: formatPeso(store.total),
    sub: 'All claims',
    icon: Wallet,
    iconBg: 'bg-accent/10',
    iconColor: 'text-accent',
    accent: 'bg-accent',
  },
  {
    label: 'Total Submitted',
    value: store.items.length,
    sub: 'Claim records',
    icon: Send,
    iconBg: 'bg-slate-500/10',
    iconColor: 'text-slate-500',
    accent: 'bg-slate-500',
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
    receiptQuantity: item.receipts || 0,
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

const totalPages = computed(() =>
  Math.max(1, Math.ceil(sortedTableRows.value.length / pageSize)),
)
const paginatedTableRows = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return sortedTableRows.value.slice(start, start + pageSize)
})

watch([searchQuery, activeStatus, activeCategory], () => {
  currentPage.value = 1
})

watch(totalPages, pages => {
  if (currentPage.value > pages) currentPage.value = pages
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
    currentPage.value = 1
    return
  }
  sortKey.value = key
  sortDirection.value = 'asc'
  currentPage.value = 1
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
  const receiptCount = Number(record.receipts)
  const count = Number.isFinite(receiptCount) ? Math.max(0, receiptCount) : 1
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
              <tr v-for="i in pageSize" :key="`reimbursement-skeleton-${i}`" class="whitespace-nowrap">
                <td v-for="col in reimbursementColumnCount" :key="col" class="px-5 py-5">
                  <div
                    v-if="col === reimbursementColumnCount"
                    class="mx-auto flex h-8 w-16 max-w-full animate-pulse items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 sm:h-9 sm:w-20 sm:gap-2"
                  >
                    <div class="h-3 w-3 shrink-0 rounded bg-slate-200 sm:h-3.5 sm:w-3.5"></div>
                    <div class="h-2.5 w-5 rounded bg-slate-200 sm:w-7"></div>
                  </div>
                  <div
                    v-else
                    class="h-3.5 max-w-full animate-pulse rounded bg-slate-200"
                    :class="[
                      col === reimbursementColumnCount - 1 ? 'mx-auto h-5 w-16 rounded-full sm:w-20' : '',
                      col === 1 ? 'w-12 sm:w-14' : '',
                      col === 2 ? 'w-28 sm:w-40' : '',
                      col === 5 ? 'mx-auto w-8 sm:w-10' : '',
                      col === 7 ? 'ml-auto w-20 sm:w-24' : '',
                      ![1, 2, 5, 7, reimbursementColumnCount - 1, reimbursementColumnCount].includes(col) ? 'w-20 sm:w-24' : '',
                    ]"
                  />
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
                v-for="row in paginatedTableRows"
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
                    @click="openDetails(row)"
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
      <BasePagination
        v-if="!store.isLoading && sortedTableRows.length > pageSize"
        v-model:page="currentPage"
        :page-size="pageSize"
        :total="sortedTableRows.length"
        label="records"
      />
    </section>

    <BaseTable
      v-if="false"
      :columns="columns"
      :rows="tableRows"
      :loading="store.isLoading"
      :page-size="10"
      @row-click="viewingRecord = $event"
    >
      <template #cell-reportDescription="{ row }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-700 text-xs">{{ row.reportDescription }}</span>
          <span class="text-[10px] text-slate-400 mt-0.5">Ref: #{{ row.id }}</span>
        </div>
      </template>

      <template #cell-category="{ value }">
        <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">
          {{ value }}
        </span>
      </template>

      <template #cell-amount="{ value }">
        <span class="font-semibold font-mono text-primary">₱{{ value.toLocaleString() }}</span>
      </template>

      <template #cell-dateSubmitted="{ value }">
        <span class="font-mono text-slate-500">{{ value }}</span>
      </template>

      <template #cell-displayStatus="{ value }">
        <StatusBadge :status="value" />
      </template>

      <template #cell-actions="{ row }">
        <div v-if="auth.isAdmin && row.originalStatus === 'submitted'" class="flex gap-1.5" @click.stop>
          <button
            class="btn btn-sm bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100"
            @click="openApproveModal(row.id)"
          >
            <CheckCircle class="w-3 h-3" /> Approve
          </button>
          <button
            class="btn btn-sm bg-red-50 border border-red-200 text-red-600 hover:bg-red-100"
            @click="openRejectModal(row.id)"
          >
            <XCircle class="w-3 h-3" /> Reject
          </button>
        </div>
        <div v-else class="flex justify-center">
          <ShieldCheck class="w-4 h-4 text-slate-200" />
        </div>
      </template>
    </BaseTable>

    <!-- ── Approval Modal ── -->
    <BaseModal
      :isOpen="!!approvingId"
      @close="cancelApprove"
      contentClass="text-center p-8"
    >
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-accent-50 text-accent mb-6">
        <CheckCircle class="h-10 w-10" />
      </div>
      <h3 class="font-heading text-xl font-bold text-slate-900">
        Approve Claim Request?
      </h3>
      <p class="mt-2 text-sm leading-relaxed text-slate-500">
        Confirming this action will finalize the approval for Claim Ref #{{ approvingId }}.
      </p>
      <div class="mt-8 flex gap-3">
        <BaseButton variant="secondary" class="flex-1" @click="cancelApprove">Go Back</BaseButton>
        <BaseButton variant="primary" class="flex-1" @click="confirmApprove">
          Confirm Approval
        </BaseButton>
      </div>
    </BaseModal>

    <!-- ── Rejection Modal ── -->
    <BaseModal
      :isOpen="!!rejectingId"
      @close="cancelReject"
      contentClass="!p-0"
    >
      <!-- Header -->
      <div class="px-6 py-4 flex items-center gap-3 border-b border-slate-200 bg-slate-50/80">
        <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center">
          <XCircle class="w-5 h-5 text-red-500" />
        </div>
        <div>
          <h3 class="font-heading text-sm font-semibold text-slate-800">
            Reject Claim
          </h3>
          <p class="text-xs text-slate-400 mt-0.5">Ref #{{ rejectingId }}</p>
        </div>
      </div>

      <!-- Body -->
      <div class="p-6 flex flex-col gap-4">
        <p class="text-sm text-slate-600">
          Please provide a reason for rejecting this reimbursement claim.
        </p>
        <div class="input-wrapper">
          <label class="input-label">Rejection Reason</label>
          <textarea
            v-model="rejectionComment"
            rows="3"
            class="input resize-none"
            :class="rejectionComment.length > 0 && rejectionComment.length < 10 ? 'input-error' : ''"
            placeholder="Describe the reason (minimum 10 characters)…"
          />
          <div class="flex justify-between items-center mt-1"
               :class="rejectionComment.length < 10 ? 'text-danger' : 'text-accent'">
            <span class="text-[10px] font-medium">Minimum 10 characters required</span>
            <span class="text-[10px] font-semibold">{{ rejectionComment.length }} / 10+</span>
          </div>
        </div>
        <div class="flex items-center justify-end gap-2.5 mt-1">
          <BaseButton variant="secondary" @click="cancelReject">Cancel</BaseButton>
          <BaseButton
            variant="danger"
            :disabled="rejectionComment.length < 10"
            @click="confirmReject"
          >
            <XCircle class="w-4 h-4" /> Confirm Rejection
          </BaseButton>
        </div>
      </div>
    </BaseModal>

    <!-- ── Record Detail Panel ── -->
    <Transition name="modal">
      <div
        v-if="viewingRecord && !receiptDetailsOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
      >
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h3 class="font-heading text-base font-bold text-slate-900">
              Reimbursement Details
            </h3>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
              title="Close details"
              @click="closeDetails"
            >
              <X class="h-5 w-5" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 scrollbar-thin">
            <div class="mb-8 grid grid-cols-1 gap-x-5 gap-y-6 sm:grid-cols-2">
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Reimbursement ID</span>
                <span class="font-heading text-sm font-bold text-slate-900">{{ viewingRecord.id }}</span>
              </div>
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Status</span>
                <div>
                  <span
                    :class="[
                      'inline-flex rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide',
                      statusClass(viewingRecord.displayStatus || normalizeStatus(viewingRecord.status)),
                    ]"
                  >
                    {{ statusLabel(viewingRecord.displayStatus || viewingRecord.status).toUpperCase() }}
                  </span>
                </div>
              </div>
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Cutoff Period</span>
                <span class="text-sm font-semibold text-slate-700">{{ viewingRecord.cutoffPeriod || getCutoffPeriod(viewingRecord.date) }}</span>
              </div>
              <div class="flex flex-col gap-1">
                <span class="kpi-label text-slate-400">Total Amount</span>
                <span class="font-heading text-xl font-bold text-primary">{{ formatPeso(viewingRecord.amount || 0) }}</span>
              </div>
            </div>

            <div class="mb-8">
              <h4 class="mb-2 text-xs font-semibold text-slate-500">Report Attachment</h4>
              <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 transition-colors hover:border-slate-300">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-red-50 text-red-600">
                    <FileText class="h-5 w-5" />
                  </span>
                  <span class="truncate text-sm font-semibold text-slate-700">Client_Dinner_Report.pdf</span>
                </div>
                <button
                  class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-accent transition-colors hover:bg-accent-50"
                  title="Download report"
                >
                  <Download class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div>
              <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-2">
                <h4 class="font-heading text-base font-bold text-slate-900">Receipts ({{ activeReceiptItems.length }})</h4>
                <span class="text-xs font-medium text-slate-400">Each receipt is reviewed and decided individually</span>
              </div>

              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div
                  v-for="receipt in activeReceiptItems"
                  :key="receipt.id"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-white transition-shadow hover:shadow-md"
                >
                  <div class="aspect-[4/5] overflow-hidden bg-slate-100">
                    <img
                      src="/mock_receipt.png"
                      alt="Scanned receipt"
                      class="h-full w-full object-cover object-top transition-transform duration-500 hover:scale-105"
                    />
                  </div>
                  <div class="flex flex-col gap-3 p-5">
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <h5 class="truncate font-heading text-sm font-bold text-slate-900">{{ receipt.merchantName }}</h5>
                        <p class="truncate text-xs text-slate-400">{{ receipt.location }}</p>
                      </div>
                      <span
                        :class="[
                          'shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                          statusClass(receipt.status),
                        ]"
                      >
                        {{ statusLabel(receipt.status) }}
                      </span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <span class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent">{{ receipt.category }}</span>
                      <span class="font-heading text-sm font-bold text-primary">{{ formatPeso(receipt.amount || 0) }}</span>
                    </div>
                    <button
                      class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-accent-50 px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-100"
                      @click="viewReceiptDetails(receipt)"
                    >
                      <Eye class="h-4 w-4" />
                      View Receipt Details
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <Transition name="modal">
      <div
        v-if="viewingRecord && receiptDetailsOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4 backdrop-blur-[1px]"
      >
        <div class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl">
          <header class="flex items-center justify-between border-b border-primary/10 bg-primary px-5 py-4 text-white">
            <div class="flex min-w-0 items-center gap-4">
              <button
                class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-xs font-bold text-white/90 transition-colors hover:bg-white/10"
                @click="receiptDetailsOpen = false"
              >
                <ArrowLeft class="h-4 w-4" />
                Back
              </button>
              <div class="h-6 w-px bg-white/20" />
              <div class="flex min-w-0 items-center gap-2">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
                  <CalendarDays class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                  <h3 class="truncate font-heading text-lg font-bold text-white">
                    Receipt Details
                  </h3>
                  <p class="truncate text-xs font-semibold text-white/65">
                    AI-scanned reimbursement receipt extraction
                  </p>
                </div>
              </div>
            </div>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white/85 transition-colors hover:bg-white/10 hover:text-white"
              title="Close receipt details"
              @click="closeDetails"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="flex-1 overflow-y-auto bg-slate-50 p-5 scrollbar-thin">
            <div class="mb-4 flex flex-col gap-3 rounded-lg border border-accent/20 bg-accent-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-center gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-accent shadow-sm">
                  <Sparkles class="h-4 w-4" />
                </span>
                <div>
                  <p class="text-xs font-bold uppercase tracking-[0.12em] text-accent">AI Scanned</p>
                  <p class="text-sm font-semibold text-primary">Details automatically extracted from the uploaded receipt.</p>
                </div>
              </div>
              <span class="inline-flex w-fit items-center gap-1 rounded-full bg-white px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-accent shadow-sm">
                <CheckCircle class="h-3.5 w-3.5" />
                Verified fields
              </span>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="border-b border-slate-200 bg-slate-100/70 p-5 lg:border-b-0 lg:border-r">
                  <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                      <p class="kpi-label text-slate-400">Receipt Preview</p>
                      <h4 class="mt-1 font-heading text-base font-bold text-slate-900">{{ selectedReceipt?.merchantName }}</h4>
                    </div>
                    <span class="inline-flex rounded-md bg-accent-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-accent">
                      {{ selectedReceipt?.category }}
                    </span>
                  </div>
                  <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <img
                      src="/mock_receipt.png"
                      alt="Scanned receipt"
                      class="h-full max-h-[520px] w-full object-cover object-top"
                    />
                  </div>
                  <button
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-accent/20 bg-white px-3 py-2.5 text-xs font-bold text-accent transition-colors hover:bg-accent-50"
                    type="button"
                  >
                    <Download class="h-4 w-4" />
                    Download Receipt
                  </button>
                </aside>

                <section class="space-y-5 p-5">
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="space-y-1">
                      <span class="input-label">Invoice Number</span>
                      <input class="input" readonly :value="selectedReceipt?.invoiceNumber" />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Transaction Date</span>
                      <span class="relative block">
                        <input class="input pr-10" readonly :value="selectedReceipt?.transactionDate" />
                        <CalendarDays class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                      </span>
                    </label>
                    <label class="space-y-1">
                      <span class="flex items-center justify-between gap-2">
                        <span class="input-label">TIN Number</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                          <Sparkles class="h-3 w-3" />
                          AI Read
                        </span>
                      </span>
                      <input class="input" readonly value="--" />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Merchant Name</span>
                      <input class="input" readonly :value="selectedReceipt?.merchantName" />
                    </label>
                    <label class="space-y-1 md:col-span-2">
                      <span class="input-label">Location</span>
                      <span class="relative block">
                        <input class="input pl-9" readonly :value="selectedReceipt?.location" />
                        <MapPin class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-accent" />
                      </span>
                    </label>
                    <label class="space-y-1 md:col-span-2">
                      <span class="flex items-center justify-between gap-2">
                        <span class="input-label">Category (AI Auto-Detected)</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                          <Sparkles class="h-3 w-3" />
                          AI Detected
                        </span>
                      </span>
                      <select class="input" disabled>
                        <option selected>{{ selectedReceipt?.category }}</option>
                      </select>
                    </label>
                  </div>

                  <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                      <h4 class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Order Items</h4>
                    </div>
                    <table class="w-full border-collapse text-left text-sm">
                      <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">
                          <th class="px-4 py-3">Items</th>
                          <th class="px-4 py-3 text-center">Qty</th>
                          <th class="px-4 py-3 text-right">Price</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in selectedReceipt?.items || []" :key="item.name">
                          <td class="px-4 py-3 font-semibold text-slate-700">{{ item.name }}</td>
                          <td class="px-4 py-3 text-center text-slate-500">{{ item.quantity }}</td>
                          <td class="px-4 py-3 text-right font-semibold text-slate-700">{{ formatPeso(item.price) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div class="grid grid-cols-1 gap-3 border-t border-slate-200 pt-4 sm:grid-cols-3">
                    <label class="space-y-1">
                      <span class="input-label">Subtotal</span>
                      <input class="input font-semibold" readonly :value="formatPeso((selectedReceipt?.amount || 0) * 0.88)" />
                    </label>
                    <label class="space-y-1">
                      <span class="input-label">Tax (VAT)</span>
                      <input class="input font-semibold" readonly :value="formatPeso((selectedReceipt?.amount || 0) * 0.12)" />
                    </label>
                    <div class="rounded-lg border border-accent/20 bg-accent-50 p-3">
                      <p class="input-label text-accent">Orders Total</p>
                      <p class="mt-1 font-heading text-xl font-bold text-primary">{{ formatPeso(selectedReceipt?.amount || 0) }}</p>
                    </div>
                  </div>

                  <footer class="flex items-center gap-2 text-xs font-semibold text-slate-400">
                    <Clock class="h-4 w-4" />
                    Uploaded with receipt {{ selectedReceipt?.id }}
                  </footer>

                  <div v-if="auth.isAdmin" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                    <label class="space-y-2">
                      <span class="input-label">Add Notes</span>
                      <textarea
                        v-model="reviewerNotes"
                        class="input min-h-24 resize-none bg-white"
                        placeholder="Leave comments or reviewer feedback for this receipt..."
                      />
                    </label>
                  </div>
                </section>
              </div>
            </div>
          </div>

          <div
            v-if="auth.isAdmin && selectedReceipt"
            class="border-t border-slate-200 bg-white px-5 py-4"
          >
            <div
              v-if="isReceiptDecisionPending(selectedReceipt)"
              class="flex flex-col gap-3 rounded-lg border border-accent/20 bg-accent-50 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <p class="text-sm font-semibold text-primary">
                Are you sure you want to {{ pendingReceiptDecision.action }} this receipt?
              </p>
              <div class="flex shrink-0 items-center gap-2">
                <button
                  class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-50"
                  type="button"
                  :disabled="isReviewSubmitting"
                  @click="cancelReceiptDecision"
                >
                  Cancel
                </button>
                <button
                  class="inline-flex min-h-9 items-center justify-center rounded-lg bg-accent px-4 text-xs font-bold text-white transition-colors hover:bg-accent/90 disabled:cursor-not-allowed disabled:opacity-60"
                  type="button"
                  :disabled="isReviewSubmitting"
                  @click="confirmReceiptDecision"
                >
                  Confirm
                </button>
              </div>
            </div>
            <div v-else class="flex flex-col gap-2 sm:flex-row sm:justify-end">
              <button
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-bold text-red-700 transition-colors hover:bg-red-100"
                type="button"
                @click="requestReceiptDecision(selectedReceipt, 'Reject')"
              >
                <XCircle class="h-4 w-4" />
                Reject
              </button>
              <button
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-sm font-bold text-white transition-colors hover:bg-accent/90"
                type="button"
                @click="requestReceiptDecision(selectedReceipt, 'Approve')"
              >
                <CheckCircle class="h-4 w-4" />
                Approve
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <Transition v-if="false" name="modal">
      <div v-if="viewingRecord"
           class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 backdrop-blur-[1px] p-4">
        <div class="w-full max-w-3xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="px-6 py-4 flex items-center justify-between border-b border-slate-200 bg-slate-50/80">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                <FileText class="w-4.5 h-4.5 w-[18px] h-[18px] text-primary" />
              </div>
              <div>
                <h3 class="font-heading text-sm font-semibold text-primary">
                  Reimbursement Details
                </h3>
                <p class="text-slate-500 text-xs mt-0.5">Ref #{{ viewingRecord.id }}</p>
              </div>
            </div>
            <button
              class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-danger"
              @click="closeDetails"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto p-6 flex flex-col md:flex-row gap-6">

            <!-- Left: Details -->
            <div class="flex-1 space-y-5">
              <div>
                <p class="section-label mb-1.5">Description</p>
                <p class="text-sm font-semibold text-slate-800">{{ viewingRecord.description }}</p>
              </div>

              <div class="grid grid-cols-2 gap-5 pt-4 border-t border-slate-100">
                <div>
                  <p class="section-label mb-1">Category</p>
                  <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ viewingRecord.category }}
                  </span>
                </div>
                <div>
                  <p class="section-label mb-1">Amount</p>
                  <p class="text-lg font-bold text-primary font-mono" style="font-family: 'Poppins', sans-serif;">
                    ₱{{ viewingRecord.amount?.toLocaleString() }}
                  </p>
                </div>
                <div>
                  <p class="section-label mb-1">Submitted By</p>
                  <p class="text-sm font-medium text-slate-700">{{ viewingRecord.submittedBy }}</p>
                </div>
                <div>
                  <p class="section-label mb-1">Date Filed</p>
                  <p class="text-sm font-medium text-slate-700">{{ viewingRecord.date }}</p>
                </div>
                <div class="col-span-2 pt-3 border-t border-slate-100">
                  <p class="section-label mb-2">Status</p>
                  <StatusBadge :status="viewingRecord.status" />
                </div>
              </div>
            </div>

            <!-- Right: Receipt Preview -->
            <div class="w-full md:w-72 rounded-xl border border-slate-100 bg-slate-50 flex flex-col overflow-hidden">
              <div class="px-3 py-2.5 border-b border-slate-100 bg-white flex items-center gap-2">
                <div class="w-2 h-2 bg-accent rounded-full" />
                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
                  Receipt Attachment
                </span>
              </div>
              <div class="flex-1 p-3 flex items-center justify-center bg-slate-100/50 overflow-hidden">
                <img
                  src="/mock_receipt.png"
                  alt="Receipt Attachment"
                  class="max-w-full max-h-full object-contain rounded-lg border border-slate-200 shadow-sm hover:scale-[1.02] transition-transform duration-300"
                />
              </div>
            </div>
          </div>

          <!-- Admin Actions Footer -->
          <div
            v-if="auth.isAdmin && viewingRecord.status === 'submitted'"
            class="p-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-end gap-3"
          >
            <button
              class="btn btn-sm bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 px-5 py-2"
              @click="closeDetails(); openRejectModal(viewingRecord.id)"
            >
              <XCircle class="w-4 h-4" /> Reject Claim
            </button>
            <button
              class="btn btn-cta px-5"
              @click="openApproveModal(viewingRecord.id); closeDetails()"
            >
              <CheckCircle class="w-4 h-4" /> Approve & Authorize
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.modal-enter-active { transition: opacity 0.2s ease-out; }
.modal-leave-active { transition: opacity 0.15s ease-in; }
.modal-enter-from, .modal-leave-to { opacity: 0; }

.modal-enter-active > div {
  animation: modal-pop 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}
@keyframes modal-pop {
  from { transform: scale(0.95) translateY(8px); opacity: 0; }
  to   { transform: scale(1) translateY(0); opacity: 1; }
}
</style>
