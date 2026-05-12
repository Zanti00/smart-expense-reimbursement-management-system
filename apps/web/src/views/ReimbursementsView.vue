<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useReimbursementStore } from '@/stores/reimbursement'
import { useAuthStore } from '@/stores/auth'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { Plus, FileText, Activity, ShieldCheck, X } from 'lucide-vue-next'

const store = useReimbursementStore()
const auth = useAuthStore()
const router = useRouter()

const rejectingId = ref(null)
const rejectionComment = ref('')

const viewingRecord = ref(null)

function closeDetails() {
  viewingRecord.value = null
}

onMounted(() => store.fetchAll())

const columns = [
  { key: 'id',          label: 'Ref #',        sortable: true, cellClass: 'text-slate-400 font-mono' },
  { key: 'description', label: 'DESCRIPTION',  sortable: true, cellClass: '!font-sans' },
  { key: 'category',    label: 'CATEGORY',     sortable: true },
  { key: 'amount',      label: 'AMOUNT (PHP)', sortable: true, cellClass: 'font-bold text-primary' },
  { key: 'date',        label: 'DATE',         sortable: true },
  { key: 'submittedBy', label: 'SUBMITTED BY', sortable: true },
  { key: 'status',      label: 'STATUS',       sortable: true },
  { key: 'actions',     label: 'ACTIONS',      sortable: false },
]

async function quickApprove(id) { await store.approve(id) }

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
  <div class="flex flex-col gap-6 font-sans">
    <!-- Component Header -->
    <div class="flex items-end justify-between border-b border-slate-200 pb-5">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <Activity class="w-3.5 h-3.5 text-primary" />
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">History: Claim Records</span>
        </div>
        <h1 class="text-xl font-bold text-primary uppercase tracking-widest">Reimbursements</h1>
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1">List of all submitted claims</p>
      </div>
      <BaseButton id="new-reimbursement-btn" variant="cta" @click="router.push('/reimbursements/new')">
        <Plus class="w-5 h-5 mr-1" /> CREATE NEW REQUEST
      </BaseButton>
    </div>

    <!-- Summary Telemetry -->
    <div class="flex flex-wrap gap-2">
      <div class="flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 shadow-sm">
        <div class="w-1.5 h-1.5 bg-warning rounded-full" />
        <span class="text-[10px] font-bold text-slate-600 uppercase tracking-widest">Submitted: {{ store.pending.length }}</span>
      </div>
      <div class="flex items-center gap-2 px-3 py-1 bg-white border border-slate-200 shadow-sm">
        <div class="w-1.5 h-1.5 bg-success rounded-full" />
        <span class="text-[10px] font-bold text-slate-600 uppercase tracking-widest">Approved: {{ store.approved.length }}</span>
      </div>
      <div class="flex items-center gap-2 px-3 py-1 bg-primary/5 border border-primary/20 shadow-sm">
        <Activity class="w-3 h-3 text-primary" />
        <span class="text-[10px] font-bold text-primary uppercase tracking-widest">Total Amount: ₱{{ store.total.toLocaleString() }}</span>
      </div>
    </div>

    <!-- Main Spreadsheet Module -->
    <BaseTable
      :columns="columns"
      :rows="store.items"
      :loading="store.isLoading"
      :page-size="10"
      @row-click="viewingRecord = $event"
    >
      <template #cell-description="{ row }">
        <div class="flex flex-col">
          <span class="font-bold text-xs uppercase tracking-tight text-slate-700">{{ row.description }}</span>
          <span class="text-[9px] text-slate-400 uppercase font-medium">Ref: {{ row.id }}-INF</span>
        </div>
      </template>

      <template #cell-category="{ value }">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ value }}</span>
      </template>

      <template #cell-amount="{ value }">
        <span class="font-bold">₱{{ value.toLocaleString() }}</span>
      </template>

      <template #cell-status="{ value }">
        <StatusBadge :status="value" />
      </template>

      <template #cell-actions="{ row }">
        <div v-if="auth.isAdmin && row.status === 'submitted'" class="flex gap-1" @click.stop>
          <button class="btn btn-secondary btn-sm !text-success !border-success/20 hover:!bg-success/5" @click="quickApprove(row.id)">
            APPROVE
          </button>
          <button class="btn btn-secondary btn-sm !text-danger !border-danger/20 hover:!bg-danger/5" @click="openRejectModal(row.id)">
            REJECT
          </button>
        </div>
        <div v-else class="flex justify-center">
          <ShieldCheck class="w-3.5 h-3.5 text-slate-200" />
        </div>
      </template>
    </BaseTable>

    <!-- Rejection Modal -->
    <div v-if="rejectingId" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
      <div class="card p-0 w-full max-w-md shadow-2xl border border-danger/20 overflow-hidden transform transition-all">
        <div class="bg-danger text-white px-4 py-3 flex items-center gap-2">
          <Activity class="w-4 h-4" />
          <h3 class="text-xs font-bold uppercase tracking-widest">REJECT SETTLEMENT</h3>
        </div>
        <div class="p-5 flex flex-col gap-4">
          <p class="text-[11px] font-medium text-slate-600 uppercase tracking-wide">
            Please provide a valid justification for rejecting Ref #{{ rejectingId }}-INF.
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
              <h3 class="text-xs font-bold uppercase tracking-widest">Reimbursement Documentation</h3>
              <p class="text-[10px] text-white/60 tracking-wider">REF: {{ viewingRecord.id }}</p>
            </div>
          </div>
          <button class="text-white/50 hover:text-white transition-none" @click="closeDetails"><X class="w-5 h-5" /></button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6 flex flex-col md:flex-row gap-6">
          <div class="flex-1 space-y-4">
               <div>
                 <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">DESCRIPTION</p>
                 <p class="text-sm font-bold text-slate-800 uppercase">{{ viewingRecord.description }}</p>
               </div>
               <div class="grid grid-cols-2 gap-6 pt-2 border-t border-slate-100 mt-2">
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">CATEGORY</p>
                   <p class="text-sm font-bold text-slate-700 uppercase">{{ viewingRecord.category }}</p>
                 </div>
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">AMOUNT TARGET</p>
                   <p class="text-lg font-bold text-primary font-mono tracking-tighter">₱{{ viewingRecord.amount?.toLocaleString() }}</p>
                 </div>
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">SUBMITTED BY</p>
                   <p class="text-sm font-bold text-slate-700 uppercase">{{ viewingRecord.submittedBy }}</p>
                 </div>
                 <div>
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">DATE LOGGED</p>
                   <p class="text-sm font-bold text-slate-700 uppercase">{{ viewingRecord.date }}</p>
                 </div>
                 <div class="col-span-2 mt-4 flex flex-col items-start gap-1">
                   <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">VERDICT / STATUS</p>
                   <StatusBadge :status="viewingRecord.status" />
                 </div>
               </div>
          </div>
          <div class="w-full md:w-80 border border-slate-200 bg-clinical flex flex-col h-[400px]">
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
          <button class="btn btn-secondary !border-danger/30 !text-danger hover:!bg-danger/5 px-6" @click="closeDetails(); openRejectModal(viewingRecord.id)">
            REJECT CLAIM
          </button>
          <button class="btn btn-cta px-6" @click="quickApprove(viewingRecord.id); closeDetails()">
            APPROVE & AUTHORIZE
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
