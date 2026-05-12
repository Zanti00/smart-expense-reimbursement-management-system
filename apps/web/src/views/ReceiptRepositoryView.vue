<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useReceiptStore } from '@/stores/receipts'
import { useNotificationStore } from '@/stores/notification'
import StatusBadge from '@/components/base/StatusBadge.vue'
import {
  Search, FileText, Image as ImageIcon, Trash2, X, Filter, ChevronDown, Download, UploadCloud
} from 'lucide-vue-next'

const auth = useAuthStore()
const receiptsStore = useReceiptStore()
const notif = useNotificationStore()

const filtersOpen = ref(true)
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

function formatCurrency(amount) {
  return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
}

function formatSize(bytes) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Modals state
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
    notif.success('Receipt has been soft-deleted.')
    deleteModalOpen.value = false
  } else {
    notif.error('Invalid confirmation code.')
  }
}

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

</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto pb-12">
    <!-- Header -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-primary tracking-tight">Receipt Repository</h1>
        <p class="text-xs text-slate-500 font-mono mt-1">SECURE EXPENSE VALIDATION MODULE</p>
      </div>
      <div v-if="auth.isAdmin" class="flex items-center gap-2">
        <button class="btn btn-secondary text-sm">
          <Download class="w-4 h-4 mr-2" />
          Export All
        </button>
      </div>
    </header>

    <!-- Upload Zone -->
    <div
       class="border-2 border-dashed border-slate-300 rounded-lg p-10 text-center transition hover:border-primary-400 hover:bg-white cursor-pointer relative bg-slate-50 flex flex-col items-center justify-center"
       @dragover.prevent="dragOver = true"
       @dragleave.prevent="dragOver = false"
       @drop.prevent="handleDrop"
       @click="$refs.fileInput.click()"
       :class="{ 'border-primary-400 bg-primary-50': dragOver }"
    >
        <input type="file" ref="fileInput" class="hidden" accept=".jpg,.jpeg,.png,.pdf" @change="handleFileSelect" />
        <div class="w-16 h-16 bg-white shadow-sm rounded-full flex items-center justify-center mb-4 text-primary">
          <UploadCloud class="w-8 h-8" />
        </div>
        <p class="text-base font-bold text-primary">Drag & drop your physical receipt scan here</p>
        <p class="text-xs text-slate-500 mt-2">Requires clean, high-contrast JPEG, PNG, or PDF formats (Max Size: 10MB)</p>
        
        <div v-if="fileError" class="mt-4 px-4 py-2 bg-red-50 text-red-600 rounded text-xs font-bold ring-1 ring-red-200">
          {{ fileError }}
        </div>
    </div>

    <!-- Filter Strip -->
    <div class="bg-white border border-slate-200 rounded shadow-sm">
       <div class="w-full flex items-center justify-between p-4 cursor-pointer hover:bg-slate-50 transition" @click="filtersOpen = !filtersOpen">
          <div class="flex items-center gap-2">
             <Filter class="w-4 h-4 text-slate-500" />
             <span class="text-xs font-bold text-slate-700 tracking-wider">FILTER PARAMETERS</span>
          </div>
          <div class="flex items-center gap-4">
             <button v-if="filtersOpen" @click.stop="receiptsStore.clearFilters()" class="text-[10px] font-bold text-slate-500 hover:text-danger transition flex items-center gap-1 uppercase tracking-wider">
               <X class="w-3 h-3" /> Clear Filters
             </button>
             <ChevronDown class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': filtersOpen }" />
          </div>
       </div>
       <Transition name="fade">
         <div v-show="filtersOpen" class="p-4 border-t border-slate-100 bg-slate-50/50">
           <div class="flex flex-wrap items-end gap-4">
             
             <!-- Date Range -->
             <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Date Range</label>
                <div class="flex items-center gap-1">
                   <input type="date" class="input !py-1.5 !px-2 text-xs w-full" v-model="receiptsStore.filters.dateRange.start" />
                   <span class="text-slate-400 font-bold">-</span>
                   <input type="date" class="input !py-1.5 !px-2 text-xs w-full" v-model="receiptsStore.filters.dateRange.end" />
                </div>
             </div>
             
             <!-- Uploader -->
             <div v-if="auth.isAdmin" class="flex-1 min-w-[150px]">
               <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Uploader</label>
               <input type="text" class="input !py-1.5 !px-2 text-xs w-full" placeholder="e.g. kyle.l" v-model="receiptsStore.filters.uploader" />
             </div>

             <!-- Category -->
             <div class="flex-1 min-w-[150px]">
               <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Category</label>
               <select class="input !py-1.5 !px-2 text-xs w-full" v-model="receiptsStore.filters.category">
                 <option value="">All Categories</option>
                 <option>Lodging</option>
                 <option>Transportation</option>
                 <option>Meals</option>
                 <option>Supplies</option>
                 <option>Uncategorized</option>
               </select>
             </div>
             
             <!-- Status -->
             <div class="flex-1 min-w-[150px]">
               <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Status</label>
               <select class="input !py-1.5 !px-2 text-xs w-full" v-model="receiptsStore.filters.status">
                 <option value="">All Statuses</option>
                 <option>Processing</option>
                 <option>Pending</option>
                 <option>Approved</option>
                 <option>Liquidated</option>
                 <option>Unliquidated</option>
               </select>
             </div>

             <!-- Amount Range -->
             <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Amount Range (PHP)</label>
                <div class="flex items-center gap-1">
                   <div class="relative w-full">
                     <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-medium">₱</span>
                     <input type="number" class="input !py-1.5 !pl-6 !pr-2 text-xs w-full" placeholder="Min" v-model="receiptsStore.filters.amountRange.min" />
                   </div>
                   <span class="text-slate-400 font-bold">-</span>
                   <div class="relative w-full">
                     <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-medium">₱</span>
                     <input type="number" class="input !py-1.5 !pl-6 !pr-2 text-xs w-full" placeholder="Max" v-model="receiptsStore.filters.amountRange.max" />
                   </div>
                </div>
             </div>
             
           </div>
         </div>
       </Transition>
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden flex-1 relative min-h-[400px]">
       <div class="overflow-x-auto">
         <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200">
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest w-12">Preview</th>
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Receipt ID / File</th>
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest" v-if="auth.isAdmin">Uploader</th>
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Metadata</th>
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Amount</th>
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                <th class="p-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
               <tr v-if="receiptsStore.visibleReceipts.length === 0">
                 <td :colspan="auth.isAdmin ? 7 : 6" class="p-12 text-center text-slate-500">
                    <Search class="w-8 h-8 mx-auto text-slate-300 mb-3" />
                    <p class="text-sm font-medium">No records match the current filters.</p>
                 </td>
               </tr>
               <tr v-for="receipt in receiptsStore.visibleReceipts" :key="receipt.id" class="hover:bg-slate-50/50 transition cursor-pointer" @click="openViewModal(receipt)">
                 <td class="p-4 font-mono">
                    <div class="w-10 h-10 bg-slate-100 border border-slate-200 rounded flex items-center justify-center overflow-hidden flex-shrink-0">
                       <img v-if="receipt.thumbnail" :src="receipt.thumbnail" class="w-full h-full object-cover opacity-50" />
                       <div v-else>
                         <FileText v-if="receipt.fileType === 'application/pdf'" class="w-5 h-5 text-slate-400" />
                         <ImageIcon v-else class="w-5 h-5 text-slate-400" />
                       </div>
                    </div>
                 </td>
                 <td class="p-4">
                    <div class="text-sm font-bold text-primary">{{ receipt.id }}</div>
                    <div class="text-xs text-slate-500 flex items-center gap-2 mt-0.5" :title="receipt.fileName">
                       <span class="truncate max-w-[150px]">{{ receipt.fileName }}</span>
                       <span class="text-[9px] px-1 bg-slate-100 rounded text-slate-500">{{ formatSize(receipt.fileSize) }}</span>
                    </div>
                 </td>
                 <td class="p-4" v-if="auth.isAdmin">
                    <div class="text-xs font-medium text-slate-700 bg-slate-100 px-2 py-1 rounded inline-flex items-center gap-1">
                      <span class="w-1.5 h-1.5 rounded-full bg-slate-400 block"></span>
                      {{ receipt.uploader }}
                    </div>
                 </td>
                 <td class="p-4">
                    <div class="text-xs font-bold text-slate-700">{{ receipt.category }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">{{ receipt.date }}</div>
                 </td>
                 <td class="p-4 text-right">
                    <div class="text-sm font-bold text-slate-700 font-mono">{{ receipt.amount > 0 ? formatCurrency(receipt.amount) : '--' }}</div>
                 </td>
                 <td class="p-4 text-center">
                    <StatusBadge :status="receipt.status.toLowerCase()" />
                 </td>
                 <td class="p-4">
                    <div class="flex items-center justify-end gap-1">
                       <button class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded transition" title="Export" @click.stop>
                         <Download class="w-4 h-4" />
                       </button>
                       <button class="p-1.5 text-danger/70 hover:text-danger hover:bg-red-50 rounded transition" title="Soft Delete" @click.stop="promptDelete(receipt.id)">
                         <Trash2 class="w-4 h-4" />
                       </button>
                    </div>
                 </td>
               </tr>
            </tbody>
         </table>
       </div>
    </div>
    
    <!-- Delete Modal (2FA / Confirmation) -->
    <div v-if="deleteModalOpen" class="fixed inset-0 z-50 bg-slate-900/40 flex items-center justify-center p-4">
       <div class="bg-white rounded-lg shadow-xl w-full max-w-sm overflow-hidden animate-fade-in border border-slate-200">
          <div class="p-5">
             <div class="flex items-center gap-3 text-danger mb-4">
               <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center flex-shrink-0">
                  <Trash2 class="w-5 h-5" />
               </div>
               <h3 class="text-lg font-bold">Delete Receipt</h3>
             </div>
             <p class="text-sm text-slate-600 mb-4">
                You are about to soft-delete this record. Please type <strong class="text-slate-900">DELETE</strong> to confirm this destructive action.
             </p>
             <input type="text" class="input w-full uppercase" v-model="confirmCode" placeholder="DELETE" />
          </div>
          <div class="bg-slate-50 p-4 border-t border-slate-100 flex justify-end gap-2">
             <button class="btn btn-secondary" @click="deleteModalOpen = false">Cancel</button>
             <button class="btn bg-danger hover:bg-danger/90 text-white" :disabled="confirmCode !== 'DELETE'" @click="confirmDelete">Confirm</button>
          </div>
       </div>
    </div>

    <!-- View Receipt Modal -->
    <div v-if="viewModalOpen && viewedReceipt" class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4 lg:p-8 backdrop-blur-sm" @click="closeViewModal">
       <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col md:flex-row animate-fade-in border border-slate-200" @click.stop>
          
          <!-- Image/Preview Section -->
          <div class="md:w-3/5 bg-slate-100 border-b md:border-b-0 md:border-r border-slate-200 flex flex-col relative">
             <div class="absolute top-4 left-4 z-10 flex gap-2">
                <StatusBadge :status="viewedReceipt.status.toLowerCase()" />
             </div>
             <div class="flex-1 flex items-center justify-center p-8 overflow-hidden min-h-[300px]">
                <img v-if="viewedReceipt.thumbnail && viewedReceipt.fileType !== 'application/pdf'" :src="viewedReceipt.thumbnail" class="max-w-full max-h-[70vh] object-contain shadow-sm border border-slate-200 bg-white" />
                <div v-else class="flex flex-col items-center justify-center text-slate-400">
                   <FileText v-if="viewedReceipt.fileType === 'application/pdf'" class="w-20 h-20 mb-4 opacity-50" />
                   <ImageIcon v-else class="w-20 h-20 mb-4 opacity-50" />
                   <p class="text-sm font-bold uppercase tracking-wider">No Preview Available</p>
                   <p class="text-xs text-slate-500">{{ viewedReceipt.fileName }}</p>
                </div>
             </div>
          </div>
          
          <!-- Details Section -->
          <div class="md:w-2/5 flex flex-col bg-white overflow-y-auto max-h-[50vh] md:max-h-full">
             <div class="p-6 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                <div>
                  <h3 class="text-lg font-bold text-primary">{{ viewedReceipt.id }}</h3>
                  <p class="text-xs text-slate-500 font-mono mt-1">Uploaded {{ viewedReceipt.date }}</p>
                </div>
                <button @click="closeViewModal" class="p-2 bg-slate-50 hover:bg-slate-100 text-slate-500 rounded-full transition">
                   <X class="w-5 h-5" />
                </button>
             </div>
             
             <div class="p-6 flex-1 space-y-6">
                <!-- Data Grid -->
                <div class="grid grid-cols-2 gap-y-4 gap-x-2">
                   <div>
                     <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Amount</span>
                     <span class="text-lg font-black text-slate-800 font-mono">{{ viewedReceipt.amount > 0 ? formatCurrency(viewedReceipt.amount) : '--' }}</span>
                   </div>
                   <div>
                     <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Category</span>
                     <span class="text-sm font-bold text-slate-700">{{ viewedReceipt.category }}</span>
                   </div>
                   <div v-if="auth.isAdmin">
                     <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Uploader</span>
                     <span class="text-sm font-medium text-slate-700 bg-slate-100 px-2 py-1 rounded inline-flex items-center gap-1.5">
                       <span class="w-1.5 h-1.5 rounded-full bg-slate-400 block"></span>
                       {{ viewedReceipt.uploader }}
                     </span>
                   </div>
                   <div>
                     <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">File Type</span>
                     <span class="text-sm font-medium text-slate-700 uppercase">{{ viewedReceipt.fileType.split('/')[1] || viewedReceipt.fileType }}</span>
                   </div>
                   <div class="col-span-2">
                     <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">File Name</span>
                     <span class="text-sm text-slate-700 break-all">{{ viewedReceipt.fileName }}</span>
                     <span class="text-[10px] ml-2 text-slate-500 font-mono">{{ formatSize(viewedReceipt.fileSize) }}</span>
                   </div>
                   <div class="col-span-2">
                     <span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">SHA-256 Hash</span>
                     <span class="text-[10px] text-slate-600 font-mono break-all bg-slate-50 p-2 border border-slate-200 rounded block">{{ viewedReceipt.hash }}</span>
                   </div>
                </div>
             </div>
             
             <!-- Actions -->
             <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-2 sticky bottom-0">
                <button class="btn btn-secondary">
                  <Download class="w-4 h-4 mr-2" /> Download Original
                </button>
             </div>
          </div>
       </div>
    </div>

  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease-out, transform 0.15s ease-out; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }

.animate-fade-in {
  animation: modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalFadeIn {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
