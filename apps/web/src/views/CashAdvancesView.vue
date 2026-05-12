<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useCashAdvanceStore } from '@/stores/cashAdvance'
import { useAuthStore } from '@/stores/auth'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { Plus, X, Wallet, Activity, ShieldAlert, FileMinus, FileText } from 'lucide-vue-next'

const store = useCashAdvanceStore()
const auth = useAuthStore()

onMounted(() => store.fetchAll())

const showModal = ref(false)
const submitting = ref(false)
const form = reactive({ purpose: '', amount: '', dueDate: '' })

const columns = [
  { key: 'id',          label: 'Ref #',       sortable: true, cellClass: 'text-slate-400 font-mono' },
  { key: 'purpose',     label: 'DESCRIPTION', sortable: true, cellClass: '!font-sans font-bold text-slate-700 uppercase tracking-tight' },
  { key: 'amount',      label: 'AMOUNT (PHP)',sortable: true, cellClass: 'font-bold text-primary' },
  { key: 'balance',     label: 'OUTSTANDING', sortable: true },
  { key: 'dueDate',     label: 'DUE DATE',    sortable: true },
  { key: 'requestedBy', label: 'USER',        sortable: true },
  { key: 'status',      label: 'STATUS',      sortable: true },
  { key: 'actions',     label: 'ACTIONS',     sortable: false },
]

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

function closeDetails() {
  viewingRecord.value = null
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
  <div class="flex flex-col gap-6 font-sans">
    <!-- Component Header -->
    <div class="flex items-end justify-between border-b border-slate-200 pb-5">
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
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
    <BaseTable :columns="columns" :rows="store.items" :loading="store.isLoading" :page-size="10" @row-click="viewingRecord = $event">
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

    <!-- Allocation Request Module (Modal) -->
    <Transition name="fade">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="card w-full max-w-md p-6 flex flex-col gap-6 shadow-2xl border border-slate-200" @click.stop>
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-xs font-bold text-primary uppercase tracking-[0.2em] flex items-center gap-2">
              <Plus class="w-3.5 h-3.5" /> New Cash Advance Request
            </h3>
            <button class="text-slate-300 hover:text-danger transition-none" @click="showModal = false"><X class="w-4 h-4" /></button>
          </div>

          <form @submit.prevent="handleRequest" class="flex flex-col gap-5">
            <div class="input-wrapper">
              <label class="input-label" for="ca-purpose">PURPOSE *</label>
              <input id="ca-purpose" v-model="form.purpose" type="text" class="input !font-sans" placeholder="WHY DO YOU NEED THIS ADVANCE?" />
            </div>
            <div class="grid grid-cols-2 gap-5">
              <div class="input-wrapper">
                <label class="input-label">AMOUNT (PHP) *</label>
                <input id="ca-amount" v-model="form.amount" type="number" class="input" placeholder="0.00" />
              </div>
              <div class="input-wrapper">
                <label class="input-label">DUE DATE *</label>
                <input id="ca-due" v-model="form.dueDate" type="date" class="input" />
              </div>
            </div>

            <div class="flex gap-2">
              <BaseButton type="button" variant="secondary" class="flex-1" @click="showModal = false">CANCEL</BaseButton>
              <BaseButton
                id="submit-advance-btn"
                type="submit"
                variant="primary"
                class="flex-1"
                :disabled="submitting || !form.purpose || !form.amount || !form.dueDate"
              >
                {{ submitting ? 'SUBMITTING...' : 'SUBMIT REQUEST' }}
              </BaseButton>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- Rejection Modal -->
    <div v-if="rejectingId" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
      <div class="card p-0 w-full max-w-md shadow-2xl border border-danger/20 overflow-hidden transform transition-all">
        <div class="bg-danger text-white px-4 py-3 flex items-center gap-2">
          <Activity class="w-4 h-4" />
          <h3 class="text-xs font-bold uppercase tracking-widest">
            REJECT {{ rejectionType === 'advance' ? 'ADVANCE REQUEST' : 'LIQUIDATION' }}
          </h3>
        </div>
        <div class="p-5 flex flex-col gap-4">
          <p class="text-[11px] font-medium text-slate-600 uppercase tracking-wide">
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
            <div class="text-[9px] font-bold uppercase tracking-widest flex justify-between mt-1"
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

    <!-- Record Details Panel -->
    <div v-if="viewingRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
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
