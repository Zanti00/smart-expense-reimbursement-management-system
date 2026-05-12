<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useReimbursementStore } from '@/stores/reimbursement'
import { usePolicyStore } from '@/stores/policy'
import { useAuthStore } from '@/stores/auth'
import FileUpload from '@/components/base/FileUpload.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import OCRField from '@/components/base/OCRField.vue'
import { ChevronRight, UploadCloud, Tag, Send, AlertTriangle, Activity, PackageCheck, Receipt, FileText } from 'lucide-vue-next'
import { onMounted } from 'vue'

const store = useReimbursementStore()
const policyStore = usePolicyStore()
const authStore = useAuthStore()
const router = useRouter()

onMounted(() => {
  policyStore.fetchAll()
})

const submitting = ref(false)
const submitted = ref(false)

const form = reactive({
  description: '',
  category: '',
  amount: '', // Set by OCR, but mutable
  vat: '',
  tin: '',
  notes: '',
  receipts: []
})

const activeReceipt = computed(() => form.receipts[0])

// Provide defaults to avoid undefined errors during processing
const defaultOcr = { amount: '', vendor: '', date: '', vat: '', tin: '', confidence: 100 }
const ocrResult = ref({ ...defaultOcr })

const CATEGORIES = [
  'LAB-SUPPLIES', 'TRANSPORT', 'CLIENT-FAC',
  'EQUIP-MAINT', 'OFFICE-SYS', 'STAFF-DEV', 'UTIL-OPER', 'OTHER-MISC'
]

const overLimit = computed(() => {
  if (!form.category) return null
  const user = authStore.user
  const limitRule = policyStore.getApplicableLimit(
    form.category, 
    user?.grade || 'ALL', 
    user?.department || 'ALL', 
    new Date().toISOString().split('T')[0]
  )
  
  if (!limitRule) return null

  const val = Number(form.amount) || Number(ocrResult.value.amount)
  if (val > limitRule.limit) {
    const excess = val - limitRule.limit
    return `SYSTEM ALERT: LIMIT EXCEEDED BY ₱${excess.toLocaleString()} (MAX ₱${limitRule.limit.toLocaleString()})`
  }
  return null
})

const invalidTin = computed(() => {
  const tinValue = form.tin || ocrResult.value.tin
  if (!tinValue) return null
  const numericOnly = tinValue.replace(/\D/g, '')
  if (numericOnly.length !== 9 && numericOnly.length !== 12) {
    return 'INVALID: MUST BE 9 OR 12 DIGITS'
  }
  return null
})

const canProceed = computed(() => {
  const amt = Number(form.amount) || Number(ocrResult.value.amount)
  return form.description && form.category && amt > 0 && !overLimit.value && !invalidTin.value && activeReceipt.value?.ocrStatus === 'done'
})

function handleOcr(data) {
  ocrResult.value = data
  if (!form.amount && data.amount) form.amount = data.amount
  if (!form.vat && data.vat) form.vat = data.vat
  if (!form.tin && data.tin) form.tin = data.tin
}

function resetForm() {
  submitted.value = false
  Object.assign(form, { description: '', category: '', amount: '', vat: '', tin: '', notes: '', receipts: [] })
  ocrResult.value = { ...defaultOcr }
}

async function handleSubmit() {
  submitting.value = true
  try {
    // Merge OCR Fields into form if they weren't explicitly bound to form
    const finalAmount = form.amount || ocrResult.value.amount
    const finalVat = form.vat || ocrResult.value.vat
    const finalTin = form.tin || ocrResult.value.tin

    await store.submit({ 
      ...form, 
      amount: finalAmount,
      vat: finalVat,
      tin: finalTin,
      vendor: ocrResult.value.vendor,
      date: ocrResult.value.date,
      status: 'pending', 
      submittedBy: authStore.user?.name || 'System Operator'  
    })
    submitted.value = true
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="max-w-5xl mx-auto flex flex-col gap-6 font-sans">
    <!-- Component Header -->
    <div class="flex items-end justify-between border-b border-slate-200 pb-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <Receipt class="w-3.5 h-3.5 text-primary" />
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Process: New Claim</span>
        </div>
        <h1 class="text-xl font-bold text-primary uppercase tracking-widest">Submit for Settlement</h1>
      </div>
    </div>

    <!-- Processing Success -->
    <div v-if="submitted" class="card p-12 flex flex-col items-center gap-6 text-center border-t-2 border-t-emerald-600">
      <div class="w-12 h-12 border border-emerald-600/30 bg-emerald-600/5 flex items-center justify-center">
        <PackageCheck class="w-6 h-6 text-emerald-600" />
      </div>
      <div>
        <h2 class="text-xs font-bold text-primary uppercase tracking-[0.2em] mb-2">Settlement Request Submitted</h2>
        <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">Your data has been successfully ingested to the ledger.</p>
      </div>
      <div class="flex gap-2">
        <BaseButton variant="primary" @click="router.push('/reimbursements')">VIEW QUEUE</BaseButton>
        <BaseButton variant="secondary" @click="resetForm">
          PROCESS NEXT
        </BaseButton>
      </div>
    </div>

    <template v-else>
      <!-- Form State: INITIAL UPLOAD -->
      <div v-if="!activeReceipt" class="card p-12 flex flex-col items-center justify-center min-h-[400px]">
        <h3 class="text-[11px] font-bold text-primary uppercase tracking-widest mb-6 flex items-center gap-2">
          <UploadCloud class="w-4 h-4" /> [01] INGEST RECEIPT
        </h3>
        <FileUpload v-model="form.receipts" @ocr-result="handleOcr" class="w-full max-w-lg" />
      </div>

      <!-- Form State: SPLIT CONSOLE (Scan & Verify) -->
      <div v-else class="flex flex-col lg:flex-row gap-6 items-start">
        
        <!-- LEFT COLUMN: The Viewfinder (40%) -->
        <div class="lg:w-[40%] flex flex-col gap-2 w-full">
          <div class="card p-2 bg-slate-100 flex flex-col h-[500px] border-slate-200">
            <div class="flex items-center justify-between px-2 pt-1 pb-2">
              <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Source Document</span>
              
              <span v-if="activeReceipt.ocrStatus === 'processing'" class="badge">
                <span class="w-1.5 h-1.5 rounded-full block bg-primary animate-pulse"></span> SCANNING
              </span>
              <span v-else-if="activeReceipt.ocrStatus === 'done' && ocrResult.confidence < 80" class="badge">
                <span class="w-1.5 h-1.5 rounded-full block bg-amber-600"></span> NEEDS REVIEW
              </span>
              <span v-else-if="activeReceipt.ocrStatus === 'done'" class="badge">
                <span class="w-1.5 h-1.5 rounded-full block bg-emerald-600"></span> CAPTURED
              </span>
            </div>

            <div class="relative flex-1 bg-white border border-slate-200 flex items-center justify-center overflow-hidden">
              <img v-if="activeReceipt.preview" :src="activeReceipt.preview" class="w-full h-full object-contain" />
              <FileText v-else class="w-8 h-8 text-slate-300" />
              
              <!-- Scanning Overlay Line -->
              <div v-if="activeReceipt.ocrStatus === 'processing'" class="absolute inset-x-0 w-full z-10 pointer-events-none animate-scan-slow">
                <div class="h-1 bg-[#252578]/40 shadow-[0_0_8px_#252578]"></div>
                <div class="h-24 bg-gradient-to-b from-[#252578]/20 to-transparent"></div>
              </div>
            </div>
          </div>
          <button class="text-[10px] font-bold text-slate-400 hover:text-danger uppercase text-center py-2" @click="resetForm">
            [ REMOVE FILE ]
          </button>
        </div>

        <!-- RIGHT COLUMN: Data Readout (60%) -->
        <div class="lg:w-[60%] flex flex-col gap-6 w-full">
          
          <!-- Extracted Fields -->
          <div class="card p-0 overflow-hidden relative">
            <div class="bg-slate-50 border-b border-slate-200 px-4 py-2 flex items-center justify-between">
              <h4 class="text-[10px] font-bold uppercase tracking-widest text-primary flex items-center gap-2">
                <Activity class="w-3.5 h-3.5" /> DATA EXTRACTION READOUT
              </h4>
               <span v-if="activeReceipt.ocrStatus === 'done'" class="text-[9px] font-bold font-mono text-slate-400">
                Confidence: {{ ocrResult.confidence }}%
              </span>
            </div>
            
            <div :class="activeReceipt.ocrStatus === 'processing' ? 'opacity-50 pointer-events-none grayscale' : ''">
              <OCRField
                v-model="form.amount"
                label="Amount (PHP)"
                type="number"
                :confidence="ocrResult.confidence"
                :error="!!overLimit"
                :error-message="overLimit"
              />
              <OCRField
                v-model="form.vat"
                label="VAT Amount"
                type="number"
                :confidence="ocrResult.confidence"
              />
              <OCRField
                v-model="ocrResult.vendor"
                label="Store / Vendor"
                :confidence="ocrResult.confidence"
              />
              <OCRField
                v-model="form.tin"
                label="TIN Number"
                :confidence="ocrResult.confidence"
                :error="!!invalidTin"
                :error-message="invalidTin"
              />
              <OCRField
                v-model="ocrResult.date"
                label="Date"
                :confidence="ocrResult.confidence"
              />
            </div>
          </div>

          <!-- Manual Enrichment -->
          <div class="card p-0 overflow-hidden">
            <div class="bg-slate-50 border-b border-slate-200 px-4 py-2">
              <h4 class="text-[10px] font-bold uppercase tracking-widest text-primary flex items-center gap-2">
                <Tag class="w-3.5 h-3.5" /> MANUAL ENRICHMENT
              </h4>
            </div>
            <div class="p-4 flex flex-col gap-4 bg-white">
              <div class="input-wrapper">
                <label class="input-label">DESCRIPTION *</label>
                <input v-model="form.description" type="text" class="input uppercase" placeholder="PURPOSE OF EXPENSE" />
              </div>
              <div class="input-wrapper">
                <label class="input-label">EXPENSE CLASSIFICATION *</label>
                <select v-model="form.category" class="input uppercase font-bold text-xs tracking-widest">
                  <option disabled value="">SELECT CLASSIFICATION</option>
                  <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ cat }}</option>
                </select>
              </div>
              <div class="input-wrapper">
                <label class="input-label">REMARKS / NOTES</label>
                <textarea v-model="form.notes" rows="2" class="input !font-sans resize-none" placeholder="OPTIONAL REMARKS" />
              </div>
            </div>
          </div>

          <!-- Station Controls -->
          <div class="flex items-center justify-end mt-2">
            <BaseButton
              id="submit-claim-btn"
              variant="primary"
              :disabled="!canProceed || submitting"
              :require-hold="true"
              :hold-duration="1500"
              class="w-full sm:w-auto"
              @click="handleSubmit"
            >
              <Activity v-if="submitting" class="animate-spin w-3 h-3" />
              <Send v-else class="w-3 h-3" />
              <span class="tracking-widest">{{ submitting ? 'TRANSMITTING...' : 'SUBMIT FOR SETTLEMENT' }}</span>
            </BaseButton>
          </div>

        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.1s linear; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@keyframes scan-slow {
  0% { transform: translateY(-100%); opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { transform: translateY(500px); opacity: 0; }
}

.animate-scan-slow {
  animation: scan-slow 1.5s linear infinite;
}
</style>
