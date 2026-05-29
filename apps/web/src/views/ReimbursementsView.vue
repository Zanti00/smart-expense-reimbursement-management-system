<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useReimbursementStore } from '@/stores/reimbursement'
import { useAuthStore } from '@/stores/auth'
import BaseTable from '@/components/base/BaseTable.vue'
import StatusBadge from '@/components/base/StatusBadge.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { Plus, FileText, Activity, ShieldCheck, X, CheckCircle, XCircle } from 'lucide-vue-next'

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
  { key: 'description', label: 'Description',  sortable: true, cellClass: '!font-sans' },
  { key: 'category',    label: 'Category',     sortable: true },
  { key: 'amount',      label: 'Amount (PHP)', sortable: true, cellClass: 'font-bold text-primary' },
  { key: 'date',        label: 'Date',         sortable: true },
  { key: 'submittedBy', label: 'Submitted By', sortable: true },
  { key: 'status',      label: 'Status',       sortable: true },
  { key: 'actions',     label: 'Actions',      sortable: false },
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
  <div class="flex flex-col gap-6 font-sans animate-fade-up">

    <!-- ── Page Header ── -->
    <div class="flex items-end justify-between">
      <div>
        <div class="flex items-center gap-2 mb-2">
          <Activity class="w-3.5 h-3.5 text-accent" />
          <span class="section-label">Claim Records</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-800 leading-tight"
            style="font-family: 'Poppins', sans-serif; letter-spacing: -0.02em;">
          Reimbursements
        </h1>
        <p class="text-sm text-slate-400 mt-1" style="font-family: 'Open Sans', sans-serif;">
          Manage and track all submitted expense claims.
        </p>
      </div>
      <BaseButton id="new-reimbursement-btn" variant="cta" @click="router.push('/reimbursements/new')">
        <Plus class="w-4 h-4" /> New Request
      </BaseButton>
    </div>

    <!-- ── Summary Pills ── -->
    <div class="flex flex-wrap gap-2">
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
    <BaseTable
      :columns="columns"
      :rows="store.items"
      :loading="store.isLoading"
      :page-size="10"
      @row-click="viewingRecord = $event"
    >
      <template #cell-description="{ row }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-700 text-xs">{{ row.description }}</span>
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

      <template #cell-status="{ value }">
        <StatusBadge :status="value" />
      </template>

      <template #cell-actions="{ row }">
        <div v-if="auth.isAdmin && row.status === 'submitted'" class="flex gap-1.5" @click.stop>
          <button
            class="btn btn-sm bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100"
            @click="quickApprove(row.id)"
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

    <!-- ── Rejection Modal ── -->
    <Transition name="modal">
      <div v-if="rejectingId"
           class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="card w-full max-w-md shadow-2xl overflow-hidden border border-red-100">
          <!-- Header -->
          <div class="px-6 py-4 flex items-center gap-3 border-b border-red-100"
               style="background: linear-gradient(135deg, #FEF2F2 0%, #FFF5F5 100%);">
            <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center">
              <XCircle class="w-5 h-5 text-red-500" />
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-800" style="font-family: 'Poppins', sans-serif;">
                Reject Claim
              </h3>
              <p class="text-xs text-slate-400 mt-0.5">Ref #{{ rejectingId }}</p>
            </div>
          </div>

          <!-- Body -->
          <div class="p-6 flex flex-col gap-4">
            <p class="text-sm text-slate-600" style="font-family: 'Open Sans', sans-serif;">
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
                   :class="rejectionComment.length < 10 ? 'text-danger' : 'text-success'">
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
        </div>
      </div>
    </Transition>

    <!-- ── Record Detail Panel ── -->
    <Transition name="modal">
      <div v-if="viewingRecord"
           class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div class="card w-full max-w-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="px-6 py-4 flex items-center justify-between border-b border-slate-100"
               style="background: linear-gradient(135deg, #252578 0%, #2F2F7E 100%);">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-lg bg-white/15 flex items-center justify-center">
                <FileText class="w-4.5 h-4.5 w-[18px] h-[18px] text-white" />
              </div>
              <div>
                <h3 class="text-sm font-semibold text-white" style="font-family: 'Poppins', sans-serif;">
                  Reimbursement Details
                </h3>
                <p class="text-white/50 text-xs mt-0.5">Ref #{{ viewingRecord.id }}</p>
              </div>
            </div>
            <button
              class="text-white/40 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10"
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
              @click="quickApprove(viewingRecord.id); closeDetails()"
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
