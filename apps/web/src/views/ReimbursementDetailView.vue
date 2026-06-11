<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useReimbursementStore } from '@/stores/reimbursement'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { formatPeso } from '@/utils/formatters'
import { ArrowLeft, CheckCircle, XCircle, ShieldCheck, FileText, Download, CalendarDays, MapPin, Sparkles, X, Activity } from 'lucide-vue-next'
import BaseModal from '@/components/base/BaseModal.vue'
import BaseButton from '@/components/base/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const store = useReimbursementStore()
const auth = useAuthStore()
const { addToast } = useToast()

const record = ref(null)
const isLoading = ref(true)

// Modals
const rejectingId = ref(null)
const rejectionComment = ref('')
const approvingId = ref(null)

const isReviewSubmitting = ref(false)

onMounted(async () => {
  try {
    record.value = await store.fetchOne(route.params.id)
  } catch (error) {
    addToast({ message: 'Failed to load reimbursement details', type: 'error' })
    router.push('/reimbursements')
  } finally {
    isLoading.value = false
  }
})

function statusClass(status) {
  const s = String(status || '').toLowerCase()
  if (s === 'approved') return 'bg-success text-white border border-success'
  if (s === 'pending' || s === 'submitted') return 'bg-yellow-100 text-yellow-800 border border-yellow-200'
  if (s === 'rejected') return 'bg-[#FEF2F2] text-[#B91C1C] border border-red-200'
  if (s === 'granted' || s === 'paid') return 'bg-[#F0FDFA] text-[#0D9488] border border-teal-100'
  return 'bg-slate-100 text-slate-600 border border-slate-200'
}

function statusLabel(status) {
  const s = String(status || '').toLowerCase()
  if (s === 'approved') return 'Approved'
  if (s === 'rejected') return 'Rejected'
  if (s === 'granted' || s === 'paid') return 'Granted'
  if (s === 'pending' || s === 'submitted') return 'Pending'
  return 'Unknown'
}

// Global approval
function openApproveModal(id) { approvingId.value = id }
function cancelApprove() { approvingId.value = null }
async function confirmApprove() {
  if (!approvingId.value) return
  isReviewSubmitting.value = true
  try {
    const updated = await store.approve(approvingId.value)
    record.value = updated
    addToast({ message: 'Reimbursement approved!', type: 'success' })
    cancelApprove()
  } catch(e) {
    addToast({ message: 'Error approving reimbursement', type: 'error' })
  } finally {
    isReviewSubmitting.value = false
  }
}

// Global rejection
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
  isReviewSubmitting.value = true
  try {
    const updated = await store.reject(rejectingId.value, rejectionComment.value)
    record.value = updated
    addToast({ message: 'Reimbursement rejected.', type: 'success' })
    cancelReject()
  } catch(e) {
    addToast({ message: 'Error rejecting reimbursement', type: 'error' })
  } finally {
    isReviewSubmitting.value = false
  }
}

async function approveReceipt(receiptId) {
  try {
    const res = await fetch(`/api/serms/reimbursements/receipts/${receiptId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ status: 'approved' })
    })
    if(!res.ok) throw new Error()
    const json = await res.json()
    const rIndex = record.value.receipts.findIndex(r => r.id === receiptId)
    if(rIndex > -1) {
      record.value.receipts[rIndex] = json.data
    }
    addToast({ message: 'Receipt approved', type: 'success' })
  } catch(e) {
    addToast({ message: 'Failed to approve receipt', type: 'error' })
  }
}

async function rejectReceipt(receiptId) {
  const reason = prompt('Reason for rejection:')
  if(!reason) return
  try {
    const res = await fetch(`/api/serms/reimbursements/receipts/${receiptId}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
        'Accept': 'application/json'
      },
      body: JSON.stringify({ status: 'rejected', admin_notes: reason })
    })
    if(!res.ok) throw new Error()
    const json = await res.json()
    const rIndex = record.value.receipts.findIndex(r => r.id === receiptId)
    if(rIndex > -1) {
      record.value.receipts[rIndex] = json.data
    }
    // Also the whole reimbursement might be updated if status cascaded
    // Better refetch
    record.value = await store.fetchOne(record.value.id)
    addToast({ message: 'Receipt rejected', type: 'success' })
  } catch(e) {
    addToast({ message: 'Failed to reject receipt', type: 'error' })
  }
}

</script>

<template>
  <div class="flex flex-col gap-6 font-sans animate-fade-up" v-if="!isLoading && record">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="flex items-start gap-3">
        <button
          @click="router.back()"
          class="w-9 h-9 rounded-full bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 shadow-sm text-slate-500 transition-all"
        >
          <ArrowLeft class="w-4 h-4" />
        </button>
        <div class="min-w-0">
          <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
            Reimbursement #{{ record.id }}
          </h1>
          <p class="mt-1 text-sm text-slate-400">
            {{ record.description }}
          </p>
        </div>
      </div>
      <div>
        <span
          :class="['inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide', statusClass(record.status)]"
        >
          {{ statusLabel(record.status) }}
        </span>
      </div>
    </div>

    <!-- Info Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-1">
        <span class="text-slate-400 text-xs font-semibold">Cutoff Period</span>
        <span class="font-bold text-slate-700">{{ record.cutoff_period || 'N/A' }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-1">
        <span class="text-slate-400 text-xs font-semibold">Total Amount</span>
        <span class="font-bold text-primary font-mono text-lg">{{ formatPeso(record.amount || 0) }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-1">
        <span class="text-slate-400 text-xs font-semibold">Date Submitted</span>
        <span class="font-bold text-slate-700">{{ new Date(record.created_at).toLocaleDateString() }}</span>
      </div>
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-1">
        <span class="text-slate-400 text-xs font-semibold">Submitted By</span>
        <span class="font-bold text-slate-700">{{ record.user?.name || 'Employee' }}</span>
      </div>
    </div>

    <div v-if="record.report_url || record.report_file_path" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex justify-between items-center">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
          <FileText class="w-5 h-5" />
        </div>
        <div>
          <p class="font-bold text-sm text-slate-800">Activity Report</p>
          <a :href="record.report_url" target="_blank" class="text-xs text-primary underline">Download Report</a>
        </div>
      </div>
    </div>

    <div class="space-y-4 mb-24">
      <h3 class="font-heading text-lg font-bold text-slate-800">Receipts ({{ record.receipts?.length || 0 }})</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="receipt in record.receipts" :key="receipt.id" class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col">
          <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h4 class="font-bold text-sm text-slate-800">{{ receipt.vendor_name || 'Receipt' }}</h4>
            <span :class="['inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide', statusClass(receipt.status)]">
              {{ statusLabel(receipt.status) }}
            </span>
          </div>
          <div class="p-4 grid grid-cols-2 gap-4 flex-1">
             <div class="col-span-2 aspect-video bg-slate-100 rounded-lg overflow-hidden border border-slate-200 flex items-center justify-center relative group">
               <a v-if="receipt.file_url" :href="receipt.file_url" target="_blank" class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-black/50 text-white font-bold transition-all z-10">View Full Size</a>
               <img v-if="receipt.file_url" :src="receipt.file_url" class="w-full h-full object-cover" />
               <FileText v-else class="w-8 h-8 text-slate-300" />
             </div>
             <div class="flex flex-col gap-1">
               <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Date</span>
               <span class="text-xs font-semibold text-slate-700">{{ receipt.transaction_date }}</span>
             </div>
             <div class="flex flex-col gap-1">
               <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Amount</span>
               <span class="text-xs font-bold font-mono text-primary">{{ formatPeso(receipt.total_amount || 0) }}</span>
             </div>
             <div class="flex flex-col gap-1 col-span-2">
               <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">TIN / Invoice</span>
               <span class="text-xs font-semibold text-slate-700">{{ receipt.tin || '--' }} / {{ receipt.invoice_number || '--' }}</span>
             </div>
             <div class="flex flex-col gap-1 col-span-2" v-if="receipt.admin_notes">
               <span class="text-[10px] uppercase font-bold text-red-500 tracking-wider">Rejection Reason</span>
               <span class="text-xs font-semibold text-red-700 bg-red-50 p-2 rounded">{{ receipt.admin_notes }}</span>
             </div>
          </div>
          <div v-if="auth.isAdmin && receipt.status === 'processing'" class="p-3 border-t border-slate-100 bg-slate-50 flex gap-2">
             <button class="flex-1 btn btn-sm bg-red-50 text-red-600 border border-red-200 hover:bg-red-100" @click="rejectReceipt(receipt.id)"><XCircle class="w-3.5 h-3.5"/> Reject</button>
             <button class="flex-1 btn btn-sm bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100" @click="approveReceipt(receipt.id)"><CheckCircle class="w-3.5 h-3.5"/> Approve</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Admin Final Actions -->
    <div v-if="auth.isAdmin && record.status === 'submitted'" class="fixed bottom-0 left-0 right-0 z-40 border-t border-slate-200 bg-white/90 p-4 shadow-[0_-8px_30px_rgb(0,0,0,0.04)] backdrop-blur-md">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-6">
        <div class="flex items-center gap-4">
          <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-accent-50 text-accent">
            <ShieldCheck class="h-6 w-6" />
          </div>
          <div>
            <h4 class="font-heading text-sm font-bold text-slate-900">Final Decision Required</h4>
            <p class="text-xs font-medium text-slate-500">Review all receipts before approving or rejecting the entire claim.</p>
          </div>
        </div>
        <div class="flex gap-3">
          <button class="btn btn-sm bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 px-5 py-2" @click="openRejectModal(record.id)">
            <XCircle class="w-4 h-4" /> Reject Claim
          </button>
          <button class="btn btn-cta px-5" @click="openApproveModal(record.id)">
            <CheckCircle class="w-4 h-4" /> Approve & Authorize
          </button>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <BaseModal :isOpen="!!approvingId" @close="cancelApprove" contentClass="text-center p-8">
      <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-accent-50 text-accent mb-6">
        <CheckCircle class="h-10 w-10" />
      </div>
      <h3 class="font-heading text-xl font-bold text-slate-900">Approve Claim Request?</h3>
      <p class="mt-2 text-sm leading-relaxed text-slate-500">Confirming this action will finalize the approval for Claim Ref #{{ approvingId }}.</p>
      <div class="mt-8 flex gap-3">
        <BaseButton variant="secondary" class="flex-1" @click="cancelApprove">Go Back</BaseButton>
        <BaseButton variant="primary" class="flex-1" :disabled="isReviewSubmitting" @click="confirmApprove">
          <Activity v-if="isReviewSubmitting" class="w-4 h-4 animate-spin" />
          Confirm Approval
        </BaseButton>
      </div>
    </BaseModal>

    <BaseModal :isOpen="!!rejectingId" @close="cancelReject" contentClass="!p-0">
      <div class="px-6 py-4 flex items-center gap-3 border-b border-slate-200 bg-slate-50/80">
        <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center"><XCircle class="w-5 h-5 text-red-500" /></div>
        <div><h3 class="font-heading text-sm font-semibold text-slate-800">Reject Claim</h3><p class="text-xs text-slate-400 mt-0.5">Ref #{{ rejectingId }}</p></div>
      </div>
      <div class="p-6 flex flex-col gap-4">
        <div class="input-wrapper">
          <label class="input-label">Rejection Reason</label>
          <textarea v-model="rejectionComment" rows="3" class="input resize-none" placeholder="Describe the reason..."></textarea>
        </div>
        <div class="flex items-center justify-end gap-2.5 mt-1">
          <BaseButton variant="secondary" @click="cancelReject">Cancel</BaseButton>
          <BaseButton variant="danger" :disabled="rejectionComment.length < 10 || isReviewSubmitting" @click="confirmReject">
            <Activity v-if="isReviewSubmitting" class="w-4 h-4 animate-spin" />
            <XCircle v-else class="w-4 h-4" /> Confirm Rejection
          </BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
  <div v-else-if="isLoading" class="flex items-center justify-center h-64">
    <Activity class="w-8 h-8 animate-spin text-accent" />
  </div>
</template>
