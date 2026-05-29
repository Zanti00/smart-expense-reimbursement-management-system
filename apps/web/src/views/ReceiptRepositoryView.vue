<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useReceiptStore } from '@/stores/receipts'
import { useNotificationStore } from '@/stores/notification'
import { useRouter } from 'vue-router'
import StatusBadge from '@/components/base/StatusBadge.vue'
import ReimbursementFormView from '@/views/ReimbursementFormView.vue'
import {
  Search, FileText, Image as ImageIcon, Trash2, X, Download,
  UploadCloud, Eye, Send, Receipt, Wallet, CheckSquare, DatabaseZap,
  Sparkles, MapPin, Clock, CheckCircle2, Calendar, Save, ChevronDown
} from 'lucide-vue-next'

const auth = useAuthStore()
const receiptsStore = useReceiptStore()
const notif = useNotificationStore()
const router = useRouter()

// ── File Upload ──────────────────────────────────────────────────
const dragOver = ref(false)
const fileInput = ref(null)
const fileError = ref('')

async function computeSHA256(file) {
  const buffer = await file.arrayBuffer()
  const hashBuffer = await crypto.subtle.digest('SHA-256', buffer)
  const hashArray = Array.from(new Uint8Array(hashBuffer))
  return hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
}

async function processFile(file) {
  fileError.value = ''
  const validTypes = ['image/jpeg', 'image/png', 'application/pdf']
  if (!validTypes.includes(file.type)) {
    fileError.value = 'Invalid file type. Only JPEG, PNG, or PDF allowed.'
    notif.error(fileError.value)
    return
  }
  if (file.size > 10 * 1024 * 1024) {
    fileError.value = 'File size exceeds 10MB.'
    notif.error(fileError.value)
    return
  }
  const hash = await computeSHA256(file)
  try {
    notif.info('Processing receipt upload...')
    await receiptsStore.simulateUpload(file, hash)
    notif.success('Receipt uploaded successfully.')
  } catch (e) {
    fileError.value = e.message
    notif.error(e.message)
  }
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file) processFile(file)
}

function handleDrop(event) {
  dragOver.value = false
  const file = event.dataTransfer.files[0]
  if (file) processFile(file)
}

// ── Selection ────────────────────────────────────────────────────
const selectedIds = ref(new Set())

function toggleSelect(id) {
  const s = new Set(selectedIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedIds.value = s
}

const selectedCount = computed(() => selectedIds.value.size)

const showReimbursementForm = ref(false)

const selectedReceiptsData = computed(() =>
  receiptsStore.visibleReceipts.filter(r => selectedIds.value.has(r.id))
)

function forwardSelected() {
  if (selectedCount.value === 0) return
  showReimbursementForm.value = true
}

// ── Category Filter ───────────────────────────────────────────────
const CATEGORIES = ['all', 'Lodging', 'Transportation', 'Meals', 'Supplies', 'Uncategorized']
const activeCategory = ref('all')

const filteredReceipts = computed(() => {
  const base = receiptsStore.visibleReceipts
  if (activeCategory.value === 'all') return base
  return base.filter(r => r.category === activeCategory.value)
})

// ── Metrics ───────────────────────────────────────────────────────
const totalExpenses = computed(() =>
  receiptsStore.visibleReceipts.reduce((s, r) => s + (r.amount || 0), 0)
)

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i]
}

// ── Delete Modal ──────────────────────────────────────────────────
const deleteModalOpen = ref(false)
const selectedReceiptId = ref(null)
const confirmCode = ref('')

function promptDelete(id) {
  selectedReceiptId.value = id
  deleteModalOpen.value = true
  confirmCode.value = ''
}

function confirmDelete() {
  if (confirmCode.value === 'DELETE') {
    receiptsStore.softDelete(selectedReceiptId.value)
    const s = new Set(selectedIds.value)
    s.delete(selectedReceiptId.value)
    selectedIds.value = s
    notif.success('Receipt removed.')
    deleteModalOpen.value = false
  } else {
    notif.error('Invalid confirmation code.')
  }
}

// ── View Modal ────────────────────────────────────────────────────
const viewModalOpen = ref(false)
const viewedReceipt = ref(null)

function openViewModal(receipt) {
  viewedReceipt.value = receipt
  viewModalOpen.value = true
}

function closeViewModal() {
  viewModalOpen.value = false
  setTimeout(() => { viewedReceipt.value = null }, 200)
}

// ── Receipt Detail Helpers ────────────────────────────────────────
const MOCK_ITEMS = {
  Lodging:        ['1 Night – Deluxe Room', 'Breakfast Buffet (x2)', 'Airport Transfer'],
  Transportation: ['Grab Ride – NAIA to BGC', 'Toll Fee – SLEX', 'Parking Fee'],
  Meals:          ['Set Meal A (x2)', 'Drinks & Dessert', 'Service Charge'],
  Supplies:       ['Bond Paper (5 reams)', 'Ballpens & Markers', 'Correction Tape'],
  Uncategorized:  ['Miscellaneous Item 1', 'Miscellaneous Item 2'],
}

function getMockItems(category) {
  return MOCK_ITEMS[category] || MOCK_ITEMS.Uncategorized
}

function getVat(amount) {
  return amount > 0 ? (amount * 0.12) / 1.12 : 0
}
function getSubtotal(amount) {
  return amount > 0 ? amount - getVat(amount) : 0
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

// ── Upload Modal ──────────────────────────────────────────────────
const uploadModalOpen = ref(false)

// KPI definitions matching dashboard pattern
const kpis = computed(() => [
  {
    label: 'Total Receipts',
    value: receiptsStore.visibleReceipts.length,
    sub: 'In repository',
    icon: Receipt,
    iconBg: 'bg-accent-100',
    iconColor: 'text-accent-600',
    accent: 'from-accent-400 to-accent',
  },
  {
    label: 'Total Expenses',
    value: formatCurrency(totalExpenses.value),
    sub: 'Cumulative amount',
    icon: Wallet,
    iconBg: 'bg-emerald-100',
    iconColor: 'text-emerald-600',
    accent: 'from-emerald-400 to-emerald-600',
  },
  {
    label: 'Selected',
    value: selectedCount.value,
    sub: 'Ready to forward',
    icon: CheckSquare,
    iconBg: 'bg-primary-100',
    iconColor: 'text-primary-600',
    accent: 'from-primary-400 to-primary',
  },
])
</script>

<template>
  <div class="flex flex-col gap-6 max-w-7xl mx-auto pb-12 animate-fade-up">

    <!-- ── Page Header ── -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <DatabaseZap class="w-3.5 h-3.5 text-accent" />
          <span class="section-label">Expense Validation Module</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 leading-tight" style="font-family:'Poppins',sans-serif; letter-spacing:-0.02em;">My Expense</h1>
        <p class="text-sm text-slate-400 mt-1" style="font-family:'Open Sans',sans-serif;">Organize and manage your receipts</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <!-- Forward to Reimbursement -->
        <button
          @click="forwardSelected"
          :disabled="selectedCount === 0"
          class="btn"
          :class="selectedCount > 0 ? 'btn-primary' : 'btn-secondary opacity-50 cursor-not-allowed'"
        >
          <Send class="w-4 h-4" />
          Forward to Reimbursement{{ selectedCount > 0 ? ` (${selectedCount})` : '' }}
        </button>
        <!-- Upload Receipt -->
        <button @click="uploadModalOpen = true" class="btn btn-cta">
          <UploadCloud class="w-4 h-4" />
          Upload Receipt
        </button>
      </div>
    </div>

    <!-- ── KPI Cards — identical pattern to DashboardView ── -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div
        v-for="kpi in kpis"
        :key="kpi.label"
        class="kpi-card group"
      >
        <!-- Colored accent top strip (overrides the default ::before with per-card gradient) -->
        <div :class="['absolute top-0 left-0 right-0 h-0.5 rounded-t-xl bg-gradient-to-r', kpi.accent]" />

        <div class="flex items-center justify-between mb-4">
          <span class="text-xs text-slate-400" style="font-family:'Open Sans',sans-serif;">{{ kpi.sub }}</span>
          <div :class="['w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0', kpi.iconBg]">
            <component :is="kpi.icon" :class="['w-4 h-4', kpi.iconColor]" />
          </div>
        </div>
        <p class="kpi-value">{{ kpi.value }}</p>
        <p class="kpi-label">{{ kpi.label }}</p>
      </div>
    </div>

    <!-- ── Category Filter Tabs ── -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="cat in CATEGORIES"
        :key="cat"
        @click="activeCategory = cat"
        class="px-5 py-2 rounded-full text-[13px] font-semibold transition-all capitalize"
        :class="activeCategory === cat
          ? 'bg-gradient-to-r from-primary to-secondary text-white shadow-sm'
          : 'bg-white border border-slate-200 text-slate-500 hover:border-primary/30 hover:text-primary hover:bg-primary-50'"
      >
        {{ cat }}
      </button>
    </div>

    <!-- ── Receipt Card Grid ── -->
    <TransitionGroup 
      v-if="filteredReceipts.length > 0" 
      tag="div" 
      name="list" 
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
    >
      <div
        v-for="receipt in filteredReceipts"
        :key="receipt.id"
        class="bg-white rounded-xl overflow-hidden flex flex-col group transition-all hover:shadow-card-hover relative cursor-pointer"
        :class="selectedIds.has(receipt.id)
          ? 'border-2 border-primary shadow-md'
          : 'border border-slate-100 shadow-card'"
        @click="toggleSelect(receipt.id)"
      >
        <!-- Selected Badge -->
        <Transition name="pop">
          <div
            v-if="selectedIds.has(receipt.id)"
            class="absolute top-3 right-3 z-10 w-7 h-7 bg-primary rounded-full flex items-center justify-center shadow-md"
          >
            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 00-1.414 0L8 12.586 4.707 9.293a1 1 0 00-1.414 1.414l4 4a1 1 0 001.414 0l8-8a1 1 0 000-1.414z" clip-rule="evenodd" />
            </svg>
          </div>
        </Transition>

        <!-- Receipt Image Preview -->
        <div class="aspect-square w-full bg-slate-50 overflow-hidden flex-shrink-0 border-b border-slate-100">
          <img
            v-if="receipt.thumbnail"
            :src="receipt.thumbnail"
            :alt="receipt.fileName"
            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 opacity-75"
          />
          <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2">
            <div class="w-12 h-12 rounded-2xl bg-primary/5 flex items-center justify-center">
              <FileText v-if="receipt.fileType === 'application/pdf' || receipt.fileType === 'pdf'" class="w-6 h-6 text-primary/40" />
              <ImageIcon v-else class="w-6 h-6 text-primary/40" />
            </div>
            <p class="text-[10px] text-slate-300 font-semibold uppercase tracking-widest" style="font-family:'Poppins',sans-serif;">No Preview</p>
          </div>
        </div>

        <!-- Card Body -->
        <div class="p-4 flex flex-col flex-1">
          <!-- File / Merchant Info -->
          <div class="mb-3">
            <h3 class="font-bold text-slate-800 text-[13px] leading-snug truncate" style="font-family:'Poppins',sans-serif;">
              {{ receipt.fileName.replace(/\.[^.]+$/, '').replace(/_/g, ' ') }}
            </h3>
            <p class="text-slate-400 text-[11px] mt-0.5 font-mono">{{ receipt.id }}</p>
            <p class="text-slate-400 text-[11px] mt-0.5">{{ receipt.date }}</p>
          </div>

          <!-- Category + Amount -->
          <div class="mt-auto flex items-center justify-between mb-3">
            <span class="px-2.5 py-1 bg-primary/5 text-primary-600 rounded-md text-[11px] font-semibold border border-primary/10 truncate max-w-[55%]" style="font-family:'Poppins',sans-serif;">
              {{ receipt.category }}
            </span>
            <span class="font-bold text-[14px] text-success font-mono">
              {{ receipt.amount > 0 ? formatCurrency(receipt.amount) : '—' }}
            </span>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-2">
            <button
              class="btn btn-primary flex-1 !py-2 !text-xs"
              @click.stop="openViewModal(receipt)"
            >
              <Eye class="w-3.5 h-3.5" /> View
            </button>
            <button
              class="px-3 py-2 rounded-lg border transition-all flex items-center justify-center"
              :class="selectedIds.has(receipt.id)
                ? 'border-danger/30 text-danger hover:bg-red-50'
                : 'border-slate-200 text-slate-400 hover:border-danger/30 hover:text-danger hover:bg-red-50'"
              @click.stop="promptDelete(receipt.id)"
              title="Delete"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </TransitionGroup>

    <!-- Empty State -->
    <div v-else class="card p-16 flex flex-col items-center gap-4 text-center">
      <div class="w-16 h-16 rounded-2xl bg-primary/5 flex items-center justify-center">
        <Search class="w-7 h-7 text-primary/30" />
      </div>
      <div>
        <p class="text-sm font-semibold text-slate-600" style="font-family:'Poppins',sans-serif;">No receipts found</p>
        <p class="text-xs text-slate-400 mt-1">Try a different category filter or upload a new receipt.</p>
      </div>
      <button @click="uploadModalOpen = true" class="btn btn-cta mt-2">
        <UploadCloud class="w-4 h-4" /> Upload Receipt
      </button>
    </div>

  </div>

  <!-- ── Upload / Receipt Scanned Modal ── -->
  <Transition name="modal">
    <div v-if="uploadModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4" @click="uploadModalOpen = false">
      <div class="card w-full max-w-5xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300" @click.stop>
        
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-primary" style="font-family:'Poppins',sans-serif;">Receipt Scanned</h2>
            <span class="bg-emerald-50 text-emerald-600 px-3 py-1 rounded-full text-[11px] font-bold flex items-center gap-1.5 border border-emerald-100">
              <Sparkles class="w-3.5 h-3.5 fill-emerald-600" />
              AI Read
            </span>
          </div>
          <button @click="uploadModalOpen = false" class="p-2 text-slate-400 hover:text-primary transition-colors">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Modal Content (Two Columns) -->
        <div class="flex flex-col md:flex-row flex-1 overflow-y-auto max-h-[75vh] md:max-h-[80vh]">
          <!-- Left Column: Receipt Preview -->
          <div class="w-full md:w-[340px] p-6 bg-slate-50 border-r border-slate-100 flex flex-col items-center">
            <div class="w-full aspect-[3/4] bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col overflow-hidden group relative">
              <img 
                alt="Receipt" 
                class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity" 
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDITsjEqJchoCxU9UW--TCt90l5K6P0vPOOl6dIsbszaJ_AhXIO9AVTHOmE0McMNPKOXLvVgmHxNQn21QZOsvp9CpKod1zLt-th6WHVS0FJjt229xLbcr-VdyA4Oa4djM5D1speHqn4EguwZRVcQZ-wTDVqST94JWTUO9z4UvqlvcY08DDjl0OPeFVYMYJH1U4Ji-1EYS21ZV7qEfDA7_bInz9lywG9a9-IgLjlsY0aCRBgLPMazwztRkKVzZ1bQ3tLpLRv1IyyoWi3"
              />
              <div class="absolute inset-0 bg-primary/10 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                <Search class="w-10 h-10 text-primary" />
              </div>
            </div>
            <p class="mt-4 text-[11px] font-mono text-slate-400">receipt_2025_56849.jpg</p>
          </div>

          <!-- Right Column: Form Data -->
          <div class="flex-1 p-6 space-y-6">
            <!-- Form Grid -->
            <div class="grid grid-cols-2 gap-4">
              <div class="input-wrapper">
                <label class="input-label">Invoice Number</label>
                <input class="input" type="text" value="INV-2025-56849" />
              </div>
              <div class="input-wrapper">
                <label class="input-label">Date</label>
                <div class="relative">
                  <input class="input pr-10" type="text" value="05/25/2026" />
                  <Calendar class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
                </div>
              </div>
            </div>
            
            <div class="input-wrapper">
              <label class="input-label">TIN Number</label>
              <input class="input" type="text" value="654-321-123-000" />
            </div>

            <div class="input-wrapper">
              <label class="input-label">Merchant Name</label>
              <input class="input" type="text" value="Grab - Airport Transfer" />
            </div>

            <div class="input-wrapper">
              <label class="input-label">Location</label>
              <div class="relative">
                <input class="input pr-10" type="text" value="NAIA Terminal 3, Pasay City to Makati CBD" />
                <MapPin class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400" />
              </div>
            </div>

            <div class="input-wrapper">
              <label class="input-label">Category</label>
              <div class="flex gap-3">
                <div class="relative flex-1">
                  <select class="input appearance-none cursor-pointer">
                    <option>Transportation</option>
                    <option>Food & Dining</option>
                    <option>Lodging</option>
                    <option>Utilities</option>
                  </select>
                  <ChevronDown class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                </div>
                <button class="bg-emerald-500 text-white px-4 py-2 rounded-lg flex items-center gap-2 text-[11px] font-bold whitespace-nowrap shadow-sm hover:bg-emerald-600 transition-all">
                  <Sparkles class="w-3.5 h-3.5 fill-white" />
                  AI Detected
                </button>
              </div>
            </div>

            <!-- Order Items Table -->
            <div class="space-y-3 mt-8">
              <h3 class="input-label !text-primary !mb-0">Order Items</h3>
              <div class="border border-slate-100 rounded-lg overflow-hidden shadow-sm">
                <table class="w-full text-left border-collapse">
                  <thead class="bg-slate-50 text-[11px] text-slate-500 uppercase">
                    <tr>
                      <th class="px-4 py-2.5 font-bold">Item</th>
                      <th class="px-4 py-2.5 font-bold text-center">Qty</th>
                      <th class="px-4 py-2.5 font-bold text-right">Price</th>
                    </tr>
                  </thead>
                  <tbody class="text-sm divide-y divide-slate-50">
                    <tr>
                      <td class="px-4 py-3 text-slate-700 font-medium font-heading">GrabCar Ride</td>
                      <td class="px-4 py-3 text-center text-slate-500">1</td>
                      <td class="px-4 py-3 text-right text-primary font-bold font-mono">₱380.00</td>
                    </tr>
                    <tr>
                      <td class="px-4 py-3 text-slate-700 font-medium font-heading">Toll Fee</td>
                      <td class="px-4 py-3 text-center text-slate-500">1</td>
                      <td class="px-4 py-3 text-right text-primary font-bold font-mono">₱68.00</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Totals Section -->
            <div class="flex items-end justify-between gap-4 pt-4 border-t border-slate-100">
              <div class="flex gap-4">
                <div class="input-wrapper">
                  <label class="input-label">Subtotal</label>
                  <input class="input !w-32 !bg-slate-50 border-slate-200" readonly type="text" value="448.00" />
                </div>
                <div class="input-wrapper">
                  <label class="input-label">Tax (VAT)</label>
                  <input class="input !w-32 !bg-slate-50 border-slate-200" readonly type="text" value="53.76" />
                </div>
              </div>
              <div class="bg-emerald-50 px-6 py-3 rounded-xl border border-emerald-100 flex flex-col items-end shadow-sm">
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Amount</span>
                <span class="text-xl font-black text-emerald-600 font-mono">₱501.76</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-6 border-t border-slate-100 bg-slate-50/50 flex justify-end gap-3 sticky bottom-0">
          <button @click="uploadModalOpen = false" class="btn btn-secondary !px-8">
            Discard All
          </button>
          <button @click="uploadModalOpen = false" class="btn btn-primary !px-8">
            <Save class="w-4 h-4" />
            Save Receipt
          </button>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ── Delete Confirmation Modal ── -->
  <Transition name="modal">
    <div v-if="deleteModalOpen" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="card w-full max-w-sm shadow-2xl overflow-hidden">
        <div class="px-6 py-4 flex items-center gap-3 border-b border-red-100" style="background: linear-gradient(135deg, #FEF2F2 0%, #FFF5F5 100%);">
          <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center">
            <Trash2 class="w-4 h-4 text-danger" />
          </div>
          <div>
            <h3 class="text-sm font-semibold text-slate-800" style="font-family:'Poppins',sans-serif;">Delete Receipt</h3>
            <p class="text-xs text-slate-400 mt-0.5">This action cannot be undone.</p>
          </div>
        </div>
        <div class="p-6 flex flex-col gap-4">
          <p class="text-sm text-slate-600" style="font-family:'Open Sans',sans-serif;">
            Type <strong class="text-slate-900 font-bold">DELETE</strong> to confirm this action.
          </p>
          <div class="input-wrapper">
            <label class="input-label">Confirmation Code</label>
            <input type="text" class="input w-full uppercase tracking-widest" v-model="confirmCode" placeholder="DELETE" />
          </div>
          <div class="flex gap-2.5">
            <button class="btn btn-secondary flex-1" @click="deleteModalOpen = false">Cancel</button>
            <button
              class="flex-1 px-4 py-2 rounded-lg text-xs font-bold text-white transition-all duration-200"
              :class="confirmCode === 'DELETE' ? 'bg-danger hover:bg-red-700 shadow-sm' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
              :disabled="confirmCode !== 'DELETE'"
              @click="confirmDelete"
            >Confirm Delete</button>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ── View Receipt Modal ── -->
  <Transition name="modal">
    <div v-if="viewModalOpen && viewedReceipt" class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4 lg:p-8 backdrop-blur-sm" @click="closeViewModal">
      <div class="card w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col shadow-2xl" @click.stop>
        
        <!-- HEADER -->
        <header class="px-6 py-4 flex items-center justify-between sticky top-0 z-20 text-white" style="background: linear-gradient(135deg, #252578 0%, #2F2F7E 100%);">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center">
              <Receipt class="w-5 h-5 text-white" />
            </div>
            <div>
              <h2 class="text-lg font-bold leading-tight" style="font-family:'Poppins',sans-serif;">Receipt Details</h2>
              <p class="text-xs text-white/70">{{ viewedReceipt.fileName }}</p>
            </div>
          </div>
          <button @click="closeViewModal" class="w-10 h-10 rounded-full hover:bg-white/10 flex items-center justify-center transition-colors">
            <X class="w-5 h-5" />
          </button>
        </header>

        <div class="overflow-y-auto p-6 space-y-6">
          <!-- Receipt Image Preview -->
          <div class="relative w-full aspect-[21/9] rounded-xl overflow-hidden border border-slate-100 bg-slate-50 group">
            <img 
              v-if="viewedReceipt.thumbnail && viewedReceipt.fileType !== 'application/pdf'" 
              :src="viewedReceipt.thumbnail" 
              class="w-full h-full object-cover opacity-80"
            />
            <div v-else class="w-full h-full flex flex-col items-center justify-center gap-2 text-slate-300">
               <FileText v-if="viewedReceipt.fileType === 'application/pdf' || viewedReceipt.fileType === 'pdf'" class="w-12 h-12 opacity-50" />
               <ImageIcon v-else class="w-12 h-12 opacity-50" />
               <p class="text-xs font-semibold uppercase tracking-widest" style="font-family:'Poppins',sans-serif;">No Image Preview</p>
            </div>
          </div>

          <!-- AI BADGE -->
          <div class="flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-100 rounded-xl">
            <Sparkles class="w-4 h-4 text-emerald-600 fill-emerald-600" />
            <span class="text-xs font-semibold text-emerald-700" style="font-family:'Poppins',sans-serif;">AI Scanned — Details automatically extracted</span>
          </div>

          <!-- DATA GRID -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- ID / Invoice -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Receipt ID</p>
              <p class="text-sm font-bold text-slate-800 font-mono">{{ viewedReceipt.id }}</p>
            </div>
            <!-- Category -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-2">Category</p>
              <span class="badge bg-primary-100 border-primary-200 text-primary-700">
                {{ viewedReceipt.category }}
              </span>
            </div>
            <!-- Uploader -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Submitted By</p>
              <p class="text-sm font-bold text-slate-800">{{ viewedReceipt.uploader }}</p>
            </div>
            <!-- Date -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30">
              <p class="section-label mb-1">Transaction Date</p>
              <p class="text-sm font-bold text-slate-800">{{ formatDate(viewedReceipt.date) }}</p>
            </div>
            <!-- Hash / Security -->
            <div class="p-4 rounded-xl border border-slate-100 bg-slate-50/30 md:col-span-2">
              <p class="section-label mb-1">SHA-256 Audit Hash</p>
              <p class="text-[10px] font-mono text-slate-500 break-all leading-tight">{{ viewedReceipt.hash }}</p>
            </div>
          </div>

          <!-- ITEMS CHECKLIST -->
          <div class="space-y-3">
            <h3 class="text-sm font-bold text-slate-800 px-1" style="font-family:'Poppins',sans-serif;">Items / Orders</h3>
            <div class="border border-slate-100 rounded-xl overflow-hidden bg-white divide-y divide-slate-50">
              <div 
                v-for="(item, idx) in getMockItems(viewedReceipt.category)" 
                :key="idx"
                class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors"
              >
                <CheckCircle2 class="w-4 h-4 text-emerald-500 fill-emerald-50" />
                <span class="text-sm text-slate-700">{{ item }}</span>
              </div>
            </div>
          </div>

          <!-- AMOUNT BREAKDOWN -->
          <div class="rounded-xl border border-slate-100 overflow-hidden bg-slate-50/50">
            <div class="bg-primary px-5 py-3">
              <h3 class="text-xs font-bold text-white uppercase tracking-widest" style="font-family:'Poppins',sans-serif;">Amount Breakdown</h3>
            </div>
            <div class="p-5 space-y-3">
              <div class="flex justify-between items-center text-slate-500">
                <span class="text-sm">Subtotal</span>
                <span class="text-sm font-mono">{{ formatCurrency(getSubtotal(viewedReceipt.amount)) }}</span>
              </div>
              <div class="flex justify-between items-center text-slate-500 pb-3 border-b border-slate-200">
                <span class="text-sm">Tax (VAT 12%)</span>
                <span class="text-sm font-mono">{{ formatCurrency(getVat(viewedReceipt.amount)) }}</span>
              </div>
              <div class="flex justify-between items-center pt-1">
                <span class="text-base font-bold text-primary" style="font-family:'Poppins',sans-serif;">Total Amount</span>
                <span class="text-xl font-black text-primary font-mono">{{ formatCurrency(viewedReceipt.amount) }}</span>
              </div>
            </div>
          </div>

          <!-- FOOTER -->
          <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
            <div class="flex items-center gap-2 text-slate-400">
              <Clock class="w-4 h-4" />
              <span class="text-[11px] font-semibold uppercase tracking-wider">Processed locally on {{ viewedReceipt.date }}</span>
            </div>
            <div class="flex gap-2">
               <button class="btn btn-secondary !py-2 !text-xs">
                 <Download class="w-3.5 h-3.5" /> Download
               </button>
               <button 
                 @click="promptDelete(viewedReceipt.id); closeViewModal()"
                 class="flex items-center gap-2 px-5 py-2 rounded-lg bg-red-50 text-danger hover:bg-red-100 transition-all text-xs font-bold border border-red-100"
               >
                 <Trash2 class="w-3.5 h-3.5" />
                 Delete Receipt
               </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>

  <!-- ── In-Page Reimbursement Form Overlay ── -->
  <Transition name="slide-up">
    <div
      v-if="showReimbursementForm"
      class="fixed inset-0 z-[60] flex flex-col bg-clinical overflow-hidden"
    >
      <!-- Sticky top bar with back button -->
      <div
        class="flex-shrink-0 flex items-center gap-3 px-6 py-3 bg-white border-b border-slate-100 shadow-sm sticky top-0 z-10"
        style="background: linear-gradient(135deg, #252578 0%, #2F2F7E 100%);"
      >
        <button
          @click="showReimbursementForm = false"
          class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors text-white"
        >
          <X class="w-4 h-4" />
        </button>
        <div>
          <p class="text-[10px] font-semibold text-white/60 uppercase tracking-widest">My Expense</p>
          <h2 class="text-sm font-bold text-white leading-tight" style="font-family:'Poppins',sans-serif;">New Reimbursement</h2>
        </div>
        <div class="ml-auto flex items-center gap-2 text-white/60 text-[11px]">
          <Send class="w-3.5 h-3.5" />
          <span>{{ selectedCount }} receipt{{ selectedCount !== 1 ? 's' : '' }} forwarded</span>
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
</template>

<style scoped>
/* ── Card modal pop ── */
.modal-enter-active { transition: opacity 0.2s ease-out; }
.modal-leave-active { transition: opacity 0.15s ease-in; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active > div { animation: modal-pop 0.22s cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes modal-pop {
  from { transform: scale(0.95) translateY(8px); opacity: 0; }
  to   { transform: scale(1) translateY(0); opacity: 1; }
}

/* ── Full-page slide-up overlay ── */
.slide-up-enter-active {
  transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.25s ease-out;
}
.slide-up-leave-active {
  transition: transform 0.25s cubic-bezier(0.55, 0, 1, 0.45), opacity 0.2s ease-in;
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
.list-enter-active, .list-leave-active, .list-move {
  transition: all 0.4s cubic-bezier(0.25, 1, 0.5, 1);
}
.list-enter-from, .list-leave-to {
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
  0% { transform: scale(0); opacity: 0; }
  100% { transform: scale(1); opacity: 1; }
}
</style>
