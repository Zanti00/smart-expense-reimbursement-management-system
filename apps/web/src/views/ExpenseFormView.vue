<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useExpenseStore } from '@/stores/expense'
import FileUpload from '@/components/base/FileUpload.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import ReceiptViewfinder from '@/components/base/ReceiptViewfinder.vue'
import OCRExtractedFields from '@/components/base/OCRExtractedFields.vue'
import { UploadCloud, Send, Activity, PackageCheck, Receipt, ChevronLeft, X } from 'lucide-vue-next'

const store = useExpenseStore()
const router = useRouter()

const submitting = ref(false)
const submitted = ref(false)
const errorMsg = ref('')

const form = reactive({
  amount: '',
  vat: '',
  tin: '',
  receipts: []
})

const activeReceipt = computed(() => form.receipts[0])

const defaultOcr = { amount: '', vendor: '', date: '', vat: '', tin: '', confidence: 100 }
const ocrResult = ref({ ...defaultOcr })

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
  return amt > 0 && !invalidTin.value && activeReceipt.value?.ocrStatus === 'done'
})

// If navigated from ExpenseManagementView with a pending file, auto-load it
onMounted(() => {
  if (store.pendingFile) {
    const pf = store.pendingFile
    const entry = {
      file: pf.file,
      name: pf.file.name,
      size: pf.file.size,
      preview: pf.file.type.startsWith('image/') ? URL.createObjectURL(pf.file) : null,
      ocrStatus: 'idle',
      ocrData: null
    }
    form.receipts = [entry]
    simulateOCR(form.receipts[0])
    store.clearPendingFile()
  }
})

function simulateOCR(entry) {
  entry.ocrStatus = 'processing'
  setTimeout(() => {
    const confidence = 85 + Math.random() * 15 // High confidence
    entry.ocrStatus = 'done'
    const data = {
      amount: '1250.00',
      vat: '150.00',
      tin: '000-123-456-000',
      vendor: 'Supermarket Corp.',
      date: new Date().toISOString().split('T')[0],
      confidence: Math.round(confidence)
    }
    entry.ocrData = data
    handleOcr(data)
  }, 200)
}

function handleOcr(data) {
  ocrResult.value = data
  if (!form.amount && data.amount) form.amount = data.amount
  if (!form.vat && data.vat) form.vat = data.vat
  if (!form.tin && data.tin) form.tin = data.tin
}

function handleFileOcr(data) {
  handleOcr(data)
  if (form.receipts[0]) {
    form.receipts[0].ocrStatus = 'done'
  }
}

function resetForm() {
  submitted.value = false
  Object.assign(form, { amount: '', vat: '', tin: '', receipts: [] })
  ocrResult.value = { ...defaultOcr }
}

async function handleSubmit() {
  submitting.value = true
  errorMsg.value = ''
  try {
    const finalAmount = form.amount || ocrResult.value.amount
    const finalVat = form.vat || ocrResult.value.vat
    const finalTin = form.tin || ocrResult.value.tin

    await store.submit({
      amount: finalAmount,
      vat: finalVat,
      tin: finalTin,
      vendor: ocrResult.value.vendor,
      date: ocrResult.value.date,
      ocrConfidence: ocrResult.value.confidence,
      fileName: activeReceipt.value?.name || '',
      fileType: activeReceipt.value?.file?.type || '',
      fileSize: activeReceipt.value?.size || 0,
      thumbnail: activeReceipt.value?.preview || null,
      file: activeReceipt.value?.file,
    })
    submitted.value = true
  } catch (error) {
    console.error('Save failed:', error)
    errorMsg.value = error.message || 'An unexpected error occurred while saving the receipt.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="max-w-5xl mx-auto flex flex-col gap-6 font-sans">
    <!-- Page Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="min-w-0">
        <div class="mb-2 flex items-center gap-2">
          <Receipt class="h-3.5 w-3.5 text-accent" />
          <span class="section-label">New Expense</span>
        </div>
        <h1 class="font-heading text-2xl font-bold leading-tight text-slate-800">
          Store Receipt
        </h1>
        <p class="mt-1 text-sm text-slate-400">
          Upload and validate a new expense receipt
        </p>
      </div>
      <button
        class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-slate-400 transition hover:text-primary"
        @click="router.push('/expense-management')"
      >
        <ChevronLeft class="w-3.5 h-3.5" /> Back to Expense Management
      </button>
    </div>

    <!-- Processing Success -->
    <div v-if="submitted" class="card p-12 flex flex-col items-center gap-6 text-center border-t-2 border-t-emerald-600">
      <div class="w-12 h-12 border border-emerald-600/30 bg-emerald-600/5 flex items-center justify-center">
        <PackageCheck class="w-6 h-6 text-emerald-600" />
      </div>
      <div>
        <h2 class="text-xs font-bold text-primary uppercase tracking-[0.2em] mb-2">Expense Record Saved</h2>
        <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">Your receipt has been stored successfully.</p>
      </div>
      <div class="flex gap-2">
        <BaseButton variant="primary" @click="router.push('/expense-management')">VIEW EXPENSES</BaseButton>
        <BaseButton variant="secondary" @click="resetForm">
          ADD ANOTHER
        </BaseButton>
      </div>
    </div>

    <template v-else>
      <!-- Form State: INITIAL UPLOAD -->
      <div v-if="!activeReceipt" class="card p-12 flex flex-col items-center justify-center min-h-[400px]">
        <h3 class="text-[11px] font-bold text-primary uppercase tracking-widest mb-6 flex items-center gap-2">
          <UploadCloud class="w-4 h-4" /> [01] INGEST RECEIPT
        </h3>
        <FileUpload v-model="form.receipts" @ocr-result="handleFileOcr" class="w-full max-w-lg" />
      </div>

      <!-- Form State: SPLIT CONSOLE (Scan & Verify) -->
      <div v-else class="flex flex-col lg:flex-row gap-6 items-start">
        
        <!-- LEFT COLUMN: The Viewfinder (40%) -->
        <ReceiptViewfinder
          :receipt="activeReceipt"
          :confidence="ocrResult.confidence"
          @remove="resetForm"
        />

        <!-- RIGHT COLUMN: Data Readout (60%) -->
        <div class="lg:w-[60%] flex flex-col gap-6 w-full">

          <!-- Error Alert Banner -->
          <div v-if="errorMsg" class="card border border-rose-500/20 bg-rose-50 px-4 py-3 flex items-start justify-between gap-3 text-rose-800">
            <div class="flex flex-col gap-1">
              <span class="text-[10px] font-bold uppercase tracking-widest text-rose-500">Submission Error</span>
              <p class="text-[11px] font-medium leading-relaxed">{{ errorMsg }}</p>
            </div>
            <button class="text-rose-400 hover:text-rose-600 transition p-1" @click="errorMsg = ''">
              <X class="w-3.5 h-3.5" />
            </button>
          </div>
          
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
            
            <OCRExtractedFields
              v-model:amount="form.amount"
              v-model:vat="form.vat"
              v-model:vendor="ocrResult.vendor"
              v-model:tin="form.tin"
              v-model:date="ocrResult.date"
              :confidence="ocrResult.confidence"
              :ocr-status="activeReceipt.ocrStatus"
              :over-limit="null"
              :invalid-tin="invalidTin"
            />
          </div>



          <!-- Station Controls -->
          <div class="flex items-center justify-end mt-2">
            <BaseButton
              id="submit-expense-btn"
              variant="primary"
              :disabled="!canProceed || submitting"
              :require-hold="true"
              :hold-duration="1500"
              class="w-full sm:w-auto"
              @click="handleSubmit"
            >
              <Activity v-if="submitting" class="animate-spin w-3 h-3" />
              <Send v-else class="w-3 h-3" />
              <span class="tracking-widest">{{ submitting ? 'SAVING...' : 'SAVE EXPENSE RECORD' }}</span>
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
</style>
