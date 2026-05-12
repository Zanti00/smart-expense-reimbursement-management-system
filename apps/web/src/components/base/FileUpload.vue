<script setup>
import { ref } from 'vue'
import { UploadCloud, FileText, X, CheckCircle } from 'lucide-vue-next'

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  accept: { type: String, default: 'image/*,.pdf' },
  maxSizeMb: { type: Number, default: 10 }
})
const emit = defineEmits(['update:modelValue', 'ocr-result'])

const isDragging = ref(false)
const files = ref([...props.modelValue])
const localError = ref(null)

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function getExt(name) {
  return name.split('.').pop().toUpperCase()
}

function addFiles(fileList) {
  localError.value = null
  for (const file of fileList) {
    if (file.size > props.maxSizeMb * 1024 * 1024) {
      localError.value = `File exceeds limits (${props.maxSizeMb}MB)`
      continue
    }
    
    // Hash check based on file name + size for current session duplicates
    const signature = `${file.name}-${file.size}`
    const isDuplicate = files.value.some(f => `${f.file.name}-${f.size}` === signature)
    
    if (isDuplicate) {
      localError.value = `DUPLICATE INTERCEPTED: '${file.name}' ALREADY PROCESSED.`
      continue
    }

    const entry = {
      file,
      name: file.name,
      size: file.size,
      preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
      ocrStatus: 'idle', // idle | processing | done
      ocrData: null
    }
    files.value.push(entry)
    simulateOCR(entry)
  }
  emit('update:modelValue', files.value)
}

function simulateOCR(entry) {
  if (!entry.file.type.startsWith('image/') && !entry.file.type === 'application/pdf') return
  entry.ocrStatus = 'processing'
  setTimeout(() => {
    const confidence = 70 + Math.random() * 30
    entry.ocrStatus = 'done'
    entry.ocrData = {
      amount: (Math.random() * 5000 + 500).toFixed(2),
      vat: (Math.random() * 500).toFixed(2),
      tin: `000-${Math.floor(100+Math.random()*899)}-${Math.floor(100+Math.random()*899)}-000`,
      vendor: 'Sample Vendor Inc.',
      date: new Date().toISOString().split('T')[0],
      confidence: Math.round(confidence)
    }
    emit('ocr-result', entry.ocrData)
  }, 1500 + Math.random() * 1000)
}

function removeFile(index) {
  files.value.splice(index, 1)
  emit('update:modelValue', files.value)
}

function onDrop(e) {
  isDragging.value = false
  addFiles(e.dataTransfer.files)
}

function onFileInput(e) {
  addFiles(e.target.files)
  e.target.value = ''
}
</script>

<template>
  <div class="flex flex-col gap-3 font-sans">
    <!-- Drop Zone (Input Interface) -->
    <label
      :class="[
        'flex flex-col items-center justify-center gap-2 rounded-sm border border-dashed p-8 cursor-pointer transition-none',
        isDragging
          ? 'border-primary bg-primary/5'
          : 'border-slate-200 bg-clinical hover:border-primary/50'
      ]"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="onDrop"
    >
      <UploadCloud :class="['w-6 h-6', isDragging ? 'text-primary' : 'text-slate-400']" />
      <div class="text-center">
        <p class="text-xs font-bold uppercase tracking-widest text-slate-500">
          Drop receipts and files or <span class="text-primary underline">browse</span>
        </p>
        <p class="text-[10px] font-medium text-slate-400 mt-1 uppercase">PDF, PNG, JPG (MAX {{ maxSizeMb }}MB)</p>
      </div>
      <input type="file" :accept="accept" multiple class="hidden" @change="onFileInput" />
    </label>

    <Transition name="fade">
      <div v-if="localError" class="bg-danger/10 text-danger border border-danger/30 text-[10px] font-bold uppercase tracking-widest p-2 flex items-center justify-between">
        <span>{{ localError }}</span>
        <button class="hover:text-danger-700" @click="localError = null"><X class="w-3.5 h-3.5" /></button>
      </div>
    </Transition>

    <!-- File Stream -->
    <TransitionGroup name="file-list" tag="div" class="flex flex-col gap-2">
      <div
        v-for="(entry, i) in files"
        :key="entry.name + i"
        class="card p-2 flex items-center gap-3 border shadow-none"
      >
        <!-- Preview Module (with scanning line overlay) -->
        <div class="relative w-10 h-10 border border-slate-100 bg-clinical flex items-center justify-center overflow-hidden flex-shrink-0">
          <img v-if="entry.preview" :src="entry.preview" class="w-full h-full object-cover" alt="" />
          <FileText v-else class="w-4 h-4 text-slate-300" />
          
          <!-- Scanning Overlay -->
          <div v-if="entry.ocrStatus === 'processing'" class="absolute inset-0 z-10 pointer-events-none">
            <div class="h-0.5 w-full bg-primary/80 animate-scan"></div>
            <div class="absolute inset-0 bg-primary/5"></div>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between">
            <p class="text-[11px] font-bold text-slate-700 uppercase truncate tracking-tight">{{ entry.name }}</p>
            <span class="text-[9px] font-mono text-slate-400 uppercase">{{ formatSize(entry.size) }}</span>
          </div>

          <!-- Scanning Status -->
          <div v-if="entry.ocrStatus === 'processing'" class="mt-1 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-primary animate-pulse">
            <Activity class="w-2.5 h-2.5" /> Scanning receipt...
          </div>
          <div v-else-if="entry.ocrStatus === 'done' && entry.ocrData" class="mt-1 flex flex-wrap gap-2">
            <span class="text-[10px] font-bold text-success uppercase tracking-widest flex items-center gap-1">
              <CheckCircle class="w-3 h-3" />
              ₱{{ Number(entry.ocrData.amount).toLocaleString() }}
            </span>
            <span
              :class="[
                'text-[10px] font-bold uppercase tracking-widest px-1.5 py-0.5 border',
                entry.ocrData.confidence < 80
                  ? 'bg-warning/10 border-warning/20 text-amber-700'
                  : 'bg-slate-50 border-slate-200 text-slate-500'
              ]"
            >
              {{ entry.ocrData.confidence }}% ACCURACY
              <span v-if="entry.ocrData.confidence < 80" class="ml-1">⚠ CHECK DETAILS</span>
            </span>
          </div>
        </div>

        <button class="text-slate-300 hover:text-danger transition-none p-1.5" @click="removeFile(i)">
          <X class="w-3.5 h-3.5" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.file-list-enter-active, .file-list-leave-active { transition: opacity 0.15s linear; }
.file-list-enter-from, .file-list-leave-to { opacity: 0; }

@keyframes scan {
  0% { transform: translateY(0); opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { transform: translateY(40px); opacity: 0; }
}

.animate-scan {
  animation: scan 1.5s linear infinite;
}
</style>
