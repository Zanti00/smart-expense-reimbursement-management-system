<script setup>
import { ref, computed, onMounted } from 'vue'
import { useCashAdvanceStore } from '@/stores/cashAdvance'
import { useLiquidationStore } from '@/stores/liquidation'
import StatusBadge from '@/components/base/StatusBadge.vue'
import FileUpload from '@/components/base/FileUpload.vue'
import BaseButton from '@/components/base/BaseButton.vue'
import { 
  FilePieChart, Upload, AlertTriangle, CheckCircle, Activity, 
  ArchiveRestore, ClipboardList, Plus, Trash2, Calculator
} from 'lucide-vue-next'

const store = useCashAdvanceStore()
const liqStore = useLiquidationStore()

onMounted(() => store.fetchAll())

const selectedAdvance = ref(null)
const receipts = ref([])
const submitting = ref(false)
const submitted = ref(false)

// Itemized Expenses State
const expenseItems = ref([
  { id: Date.now(), category: 'Meals', description: '', amount: 0 }
])

function addItem() {
  expenseItems.value.push({ id: Date.now(), category: 'Travel', description: '', amount: 0 })
}

function removeItem(id) {
  if (expenseItems.value.length > 1) {
    expenseItems.value = expenseItems.value.filter(item => item.id !== id)
  }
}

const totalExpenseAmount = computed(() => {
  return expenseItems.value.reduce((sum, item) => sum + (Number(item.amount) || 0), 0)
})

const agingInfo = computed(() => {
  if (!selectedAdvance.value) return null
  return liqStore.calculateAging(selectedAdvance.value)
})

const variance = computed(() => {
  if (!selectedAdvance.value) return 0
  // Variance = CA Amount - Total Expenses
  return selectedAdvance.value.amount - totalExpenseAmount.value
})

async function submitLiquidation() {
  submitting.value = true
  
  const payload = {
    items: expenseItems.value,
    receipts: receipts.value,
    totalExpenses: totalExpenseAmount.value,
    variance: variance.value
  }

  await liqStore.submitSettlement(selectedAdvance.value.id, payload)
  
  // Update mock status in local store
  const item = store.items.find(i => i.id === selectedAdvance.value.id)
  if (item) {
    item.status = 'pending' // Technically waiting for admin
    item.balance = 0
  }

  submitting.value = false
  submitted.value = true
}

function selectAdvance(adv) {
  selectedAdvance.value = adv
  submitted.value = false
  receipts.value = []
  expenseItems.value = [{ id: Date.now(), category: 'Meals', description: '', amount: 0 }]
}
</script>

<template>
  <div class="flex flex-col gap-6 font-sans">
    <!-- Component Header -->
    <div class="flex items-end justify-between border-b border-slate-200 pb-5 text-primary">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <ArchiveRestore class="w-3.5 h-3.5" />
          <span class="text-[10px] font-bold uppercase tracking-widest opacity-60">Section: Settlement Operations</span>
        </div>
        <h1 class="text-xl font-bold uppercase tracking-widest">Liquidation Console</h1>
        <p class="text-[10px] font-bold opacity-40 uppercase tracking-wider mt-1">Reconcile outstanding advances and settle balances</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- Active Allocation Stream -->
      <div class="lg:col-span-2 flex flex-col gap-4">
        <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2">
          <ClipboardList class="w-3.5 h-3.5" /> OUTSTANDING_ADVANCES
        </h3>
        
        <div 
          v-for="adv in store.items.filter(i => i.status !== 'liquidated')" 
          :key="adv.id"
          :class="[
            'card p-4 cursor-pointer transition-none group border-2',
            selectedAdvance?.id === adv.id ? 'border-primary bg-primary/[0.02]' : 'border-slate-100'
          ]"
          @click="selectAdvance(adv)"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
              <p class="text-[9px] font-bold text-slate-700 uppercase tracking-tighter mb-0.5">REF: {{ adv.id }}</p>
              <p class="font-bold text-slate-900 text-xs uppercase tracking-tight truncate">{{ adv.purpose }}</p>
            </div>
            <StatusBadge :status="liqStore.calculateAging(adv).isOverdue ? 'overdue' : adv.status" />
          </div>
          
          <div class="mt-4 flex items-end justify-between">
            <div>
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">TOTAL_ISSUED</p>
              <p class="text-lg font-bold text-primary font-mono tracking-tighter">₱{{ adv.amount.toLocaleString() }}</p>
            </div>
            <div class="text-right">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">AGE_STATUS</p>
              <div class="flex flex-col items-end">
                 <span :class="['text-[10px] font-bold uppercase', liqStore.calculateAging(adv).isOverdue ? 'text-danger' : 'text-slate-500']">
                  Day {{ liqStore.calculateAging(adv).daysSinceIssue }} of 7
                </span>
                <span v-if="liqStore.calculateAging(adv).isOverdue" class="text-[9px] font-bold text-danger font-mono">
                  PENALTY: ₱{{ liqStore.calculateAging(adv).penalty }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Liquidation Sync Form -->
      <div class="lg:col-span-3">
        <div v-if="!selectedAdvance" class="card p-16 flex flex-col items-center gap-4 text-center h-full justify-center bg-clinical/20 border-dashed border-2">
          <FilePieChart class="w-10 h-10 text-slate-200" />
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Select an active advance to clear debt</p>
        </div>

        <div v-else-if="submitted" class="card p-12 flex flex-col items-center gap-6 text-center border-t-2 border-t-emerald-600">
          <CheckCircle class="w-12 h-12 text-emerald-600" />
          <h3 class="text-xs font-bold text-primary uppercase tracking-[0.2em]">Submission Received</h3>
          <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Technician report for {{ selectedAdvance.id }} sent to audit.</p>
          <BaseButton variant="secondary" @click="selectedAdvance = null; submitted = false">
            RELOAD CONSOLE
          </BaseButton>
        </div>

        <div v-else class="card p-6 flex flex-col gap-6 shadow-sm border-t-2 border-t-primary">
          <!-- Form Header -->
          <div class="flex items-start justify-between border-b border-slate-100 pb-4">
            <div>
              <p class="text-[9px] font-bold text-slate-700 uppercase tracking-tighter mb-1">RECONCILING REF: {{ selectedAdvance.id }}</p>
              <h3 class="text-xs font-bold text-primary uppercase tracking-widest">{{ selectedAdvance.purpose }}</h3>
            </div>
            <div class="text-right">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">CASH_ADVANCE</p>
              <p class="text-2xl font-bold text-primary font-mono tracking-tighter">₱{{ selectedAdvance.amount.toLocaleString() }}</p>
            </div>
          </div>

          <!-- Itemized Breakdown -->
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
              <label class="input-label">ITEMIZED EXPENSE BREAKDOWN</label>
              <button @click="addItem" class="text-[10px] font-bold text-primary uppercase border border-primary/20 px-2 py-0.5 hover:bg-primary/5 flex items-center gap-1">
                <Plus class="w-3 h-3" /> Add Line
              </button>
            </div>
            
            <div class="space-y-2">
              <div v-for="(item, idx) in expenseItems" :key="item.id" class="flex items-center gap-2 animate-fade-in">
                <select v-model="item.category" class="input !py-1 text-[11px] w-28">
                  <option>Meals</option>
                  <option>Travel</option>
                  <option>Materials</option>
                  <option>Others</option>
                </select>
                <input v-model="item.description" placeholder="Description" class="input !py-1 text-[11px] flex-1" />
                <div class="relative w-32">
                  <span class="absolute left-2 top-1/2 -translate-y-1/2 text-[9px] font-bold text-slate-400">₱</span>
                  <input v-model.number="item.amount" type="number" class="input !py-1 !pl-5 text-[11px] font-mono text-right" />
                </div>
                <button 
                  class="p-1.5 text-slate-300 hover:text-danger hover:bg-danger/5"
                  @click="removeItem(item.id)"
                  :disabled="expenseItems.length === 1"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>

          <!-- Proof Module -->
          <div class="input-wrapper border-t border-slate-100 pt-4">
            <label class="input-label mb-3">DIGITAL RECEIPT ATTACHMENTS *</label>
            <FileUpload v-model="receipts" />
          </div>

          <!-- Reconciliation Module -->
          <div class="border border-slate-200 bg-clinical/20 p-5 mt-2">
            <div class="flex items-center gap-2 mb-4">
              <Calculator class="w-4 h-4 text-primary opacity-50" />
              <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">The Reconciliation (Settlement)</h4>
            </div>
            
            <div class="grid grid-cols-2 gap-8">
              <div class="space-y-4">
                <div class="flex justify-between items-center text-[11px]">
                  <span class="text-slate-400 font-bold uppercase tracking-tight">Total Advanced:</span>
                  <span class="font-mono font-bold text-primary">₱{{ selectedAdvance.amount.toLocaleString() }}</span>
                </div>
                <div class="flex justify-between items-center text-[11px]">
                  <span class="text-slate-400 font-bold uppercase tracking-tight text-danger">Total Expenses:</span>
                  <span class="font-mono font-bold text-danger">-₱{{ totalExpenseAmount.toLocaleString() }}</span>
                </div>
                <div class="pt-2 border-t border-slate-200 flex justify-between items-center">
                  <span class="text-[10px] font-black text-slate-600 uppercase tracking-widest">Net Variance:</span>
                  <span :class="['text-lg font-black font-mono tracking-tighter', variance >= 0 ? 'text-primary' : 'text-danger']">
                    ₱{{ Math.abs(variance).toLocaleString() }}
                  </span>
                </div>
              </div>

              <!-- Logic Outcome Message -->
              <div 
                :class="[
                  'p-4 border flex flex-col justify-center gap-2 text-center',
                  variance > 0 ? 'bg-amber-50 border-amber-200' : 
                  variance < 0 ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200'
                ]"
              >
                <template v-if="variance > 0">
                  <p class="text-[10px] font-black text-amber-700 uppercase tracking-[0.1em]">Status: Overpayment</p>
                  <p class="text-[9px] font-bold text-amber-600 uppercase leading-relaxed">
                    RETURN <span class="underline decoration-2">₱{{ variance.toLocaleString() }}</span> TO THE CASHIER
                  </p>
                </template>
                <template v-else-if="variance < 0">
                  <p class="text-[10px] font-black text-red-700 uppercase tracking-[0.1em]">Status: Reimbursement</p>
                  <p class="text-[9px] font-bold text-red-600 uppercase leading-relaxed">
                    COMPANY OWES YOU <span class="underline decoration-2">₱{{ Math.abs(variance).toLocaleString() }}</span> (ABONO)
                  </p>
                </template>
                <template v-else>
                  <p class="text-[10px] font-black text-emerald-700 uppercase tracking-[0.1em]">Status: Balanced</p>
                  <p class="text-[9px] font-bold text-emerald-600 uppercase leading-relaxed">
                    ACCOUNT CLEARED. NO FURTHER ACTION.
                  </p>
                </template>
              </div>
            </div>
          </div>

          <BaseButton
            id="submit-liquidation-btn"
            variant="primary"
            class="mt-4"
            :disabled="receipts.length === 0 || totalExpenseAmount === 0 || submitting"
            @click="submitLiquidation"
          >
            <div v-if="submitting" class="flex items-center gap-2">
              <Activity class="w-4 h-4 animate-spin" /> <span>ENCODING SETTLEMENT...</span>
            </div>
            <div v-else class="flex items-center gap-2">
              <Upload class="w-4 h-4" /> <span>SUBMIT FOR AUDIT</span>
            </div>
          </BaseButton>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.1s linear; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
