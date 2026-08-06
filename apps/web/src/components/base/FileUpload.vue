<script setup>
import { computed, ref, watch } from 'vue'
import {
  AlertTriangle,
  CheckCircle,
  FileText,
  PlusCircle,
  Trash2,
  UploadCloud,
  X,
  Layers,
  ChevronDown
} from 'lucide-vue-next'
import { apiFetch } from '../../utils/apiFetch'
import { cleanName, tinFor, formatDateForInput } from '../../utils/receiptUtils'


const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  accept: { type: String, default: 'image/*,.pdf' },
  maxSizeMb: { type: Number, default: 2 },
  emptyActionLabel: { type: String, default: 'Select Files' },
  addActionLabel: { type: String, default: 'Add More Receipts' },
})

const emit = defineEmits(['update:modelValue', 'ocr-result', 'upload-error'])

const isDragging = ref(false)
const files = ref([...props.modelValue])
const localError = ref(null)
const fileInput = ref(null)

const uploadMode = ref('single')
const showDropConfirmModal = ref(false)
const pendingDropFiles = ref([])
const isDropdownOpen = ref(false)

const isProcessingAnyReceipt = computed(() =>
  files.value.some((entry) => entry.ocrStatus === 'processing'),
)

watch(
  () => props.modelValue,
  (newValue) => {
    files.value = [...(newValue || [])]
  },
  { deep: true },
)

function formatTinValue(value, { padLastBlock = false } = {}) {
  let digits = String(value || '').replace(/\D/g, '').slice(0, 12)
  if (padLastBlock && digits.length === 9) {
    digits = `${digits}000`
  }

  const parts = []
  if (digits.length > 0) parts.push(digits.slice(0, 3))
  if (digits.length > 3) parts.push(digits.slice(3, 6))
  if (digits.length > 6) parts.push(digits.slice(6, 9))
  if (digits.length > 9) parts.push(digits.slice(9, 12))
  return parts.join('-')
}

function buildPrefilledOcrData(file) {
  return {
    id: null,
    amount: '',
    vat: '',
    tin: '',
    vendor: '',
    invoiceNumber: '',
    date: '',
    confidence: 0,
  }
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function processFiles(fileList, mode) {
  localError.value = null

  const validFiles = []
  for (const file of fileList) {
    if (file.size > props.maxSizeMb * 1024 * 1024) {
      emit('upload-error', {
        type: 'size-exceeded',
        message: `${file.name} exceeds the ${props.maxSizeMb}MB size limit.`,
        fileName: file.name,
        maxSizeMb: props.maxSizeMb,
      })
      continue
    }
    validFiles.push(file)
  }

  if (validFiles.length === 0) return

  if (mode === 'multi') {
    const entry = {
      pages: validFiles,
      name: `Multi-page Receipt (${validFiles.length} pages)`,
      size: validFiles.reduce((acc, f) => acc + f.size, 0),
      previews: validFiles.map(f => f.type.startsWith('image/') ? URL.createObjectURL(f) : null),
      ocrStatus: 'idle',
      ocrData: buildPrefilledOcrData(validFiles[0]),
    }
    files.value.push(entry)
    simulateOCR(files.value[files.value.length - 1])
  } else {
    for (const file of validFiles) {
      const entry = {
        pages: [file],
        name: file.name,
        size: file.size,
        previews: [file.type.startsWith('image/') ? URL.createObjectURL(file) : null],
        ocrStatus: 'idle',
        ocrData: buildPrefilledOcrData(file),
      }
      files.value.push(entry)
      simulateOCR(files.value[files.value.length - 1])
    }
  }

  emit('update:modelValue', files.value)
}

function handleConfirmDrop(mode) {
  showDropConfirmModal.value = false
  if (pendingDropFiles.value.length > 0) {
    processFiles(pendingDropFiles.value, mode)
  }
  pendingDropFiles.value = []
}

function triggerFileInput(mode) {
  uploadMode.value = mode
  fileInput.value?.click()
}

async function simulateOCR(entry) {
  entry.ocrStatus = 'processing'
  try {
    const formData = new FormData()
    entry.pages.forEach(file => {
      formData.append('files[]', file)
    })

    const response = await apiFetch('/api/serms/liquidations/scan', {
      method: 'POST',
      body: formData,
    })

    if (!response.ok) {
      throw new Error('OCR Scan failed')
    }

    const result = await response.json()
    const ocrData = result.data

    entry.ocrStatus = 'done'
    entry.ocrData = {
      id: ocrData.id,
      amount: ocrData.total_amount || entry.ocrData.amount || '',
      vat: ocrData.vat_amount || entry.ocrData.vat || '',
      tin: ocrData.tin || entry.ocrData.tin || '',
      vendor: ocrData.vendor_name || entry.ocrData.vendor || '',
      invoiceNumber: ocrData.invoice_number || entry.ocrData.invoiceNumber || '',
      date: formatDateForInput(ocrData.transaction_date || entry.ocrData.date),
      confidence: Math.round(ocrData.ocr_confidence_score || 85),
      file_path: ocrData.file_path,
      file_hash: ocrData.file_hash,
      file_type: ocrData.file_type,
      file_size_bytes: ocrData.file_size_bytes,
    }
    emit('ocr-result', entry.ocrData)
    emit('update:modelValue', files.value)
  } catch (error) {
    console.error('OCR processing failed:', error)
    entry.ocrStatus = 'failed'
    entry.ocrData = buildPrefilledOcrData(entry.pages[0])
    emit('update:modelValue', files.value)
  }
}

function removeFile(index) {
  const entry = files.value[index]
  if (entry?.previews) {
    entry.previews.forEach(preview => {
      if (preview) URL.revokeObjectURL(preview)
    })
  }
  files.value.splice(index, 1)
  emit('update:modelValue', files.value)
}

function onDrop(event) {
  isDragging.value = false
  const droppedFiles = Array.from(event.dataTransfer.files)
  if (droppedFiles.length > 1) {
    pendingDropFiles.value = droppedFiles
    showDropConfirmModal.value = true
  } else if (droppedFiles.length === 1) {
    processFiles(droppedFiles, 'single')
  }
}

function onFileInput(event) {
  processFiles(Array.from(event.target.files), uploadMode.value)
  event.target.value = ''
}
</script>

<template>
  <div class="flex flex-col gap-4 font-sans">
    <input
      ref="fileInput"
      type="file"
      :accept="accept"
      multiple
      class="hidden"
      @change="onFileInput"
    />

    <section v-if="files.length === 0" class="rounded-xl border border-slate-200 bg-white p-4">
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="font-heading text-base font-bold text-primary">Upload Receipts</h3>
        <div class="flex items-center gap-2 text-xs font-bold text-accent">
          <CheckCircle class="h-4 w-4" />
          <span>Upload your receipt - system reads the file automatically</span>
        </div>
      </div>

      <div
        :class="[
          'flex min-h-[260px] w-full flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors',
          isDragging
            ? 'border-accent bg-accent/5'
            : 'border-slate-200 bg-slate-50/60',
        ]"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
      >
        <span class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-accent/10 text-accent">
          <UploadCloud class="h-8 w-8" />
        </span>
        <h4 class="font-heading text-base font-bold text-slate-800">
          Drag and drop receipt images here, or choose an option
        </h4>
        <p class="mt-1 text-sm text-slate-400">
          Supports: JPG, PNG, PDF (Max {{ maxSizeMb }}MB per file)
        </p>
        <p class="mt-4 flex items-center gap-2 text-sm font-bold text-danger">
          <AlertTriangle class="h-4 w-4" />
          At least 1 receipt is required to proceed
        </p>
        <div class="mt-6">
          <div class="relative inline-block text-left" @click.stop>
            <div>
              <button
                @click="isDropdownOpen = !isDropdownOpen"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-accent px-4 text-xs font-bold text-white transition-colors hover:bg-accent-600 focus:outline-none"
              >
                <UploadCloud class="h-3.5 w-3.5" />
                Select Files
                <ChevronDown class="w-3.5 h-3.5 ml-1 -mr-1" aria-hidden="true" />
              </button>
            </div>

            <transition
              enter-active-class="transition duration-100 ease-out"
              enter-from-class="transform scale-95 opacity-0"
              enter-to-class="transform scale-100 opacity-100"
              leave-active-class="transition duration-75 ease-in"
              leave-from-class="transform scale-100 opacity-100"
              leave-to-class="transform scale-95 opacity-0"
            >
              <div
                v-if="isDropdownOpen"
                class="absolute z-10 w-48 mt-2 origin-top right-1/2 translate-x-1/2 bg-white divide-y divide-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
              >
                <div class="px-1 py-1">
                  <button
                    class="group flex w-full items-center rounded-md px-2 py-2 text-xs font-bold text-gray-900 hover:bg-accent hover:text-white"
                    @click="triggerFileInput('single'); isDropdownOpen = false"
                  >
                    <UploadCloud class="mr-2 h-4 w-4 text-accent group-hover:text-white" aria-hidden="true" />
                    Single Upload
                  </button>
                  <button
                    class="group flex w-full items-center rounded-md px-2 py-2 text-xs font-bold text-gray-900 hover:bg-accent hover:text-white"
                    @click="triggerFileInput('multi'); isDropdownOpen = false"
                  >
                    <Layers class="mr-2 h-4 w-4 text-accent group-hover:text-white" aria-hidden="true" />
                    Multiple Upload
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </section>

    <section v-else class="rounded-xl border border-slate-200 bg-white p-4">
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 class="font-heading text-base font-bold text-primary">Scanned Receipts</h3>
          <p class="mt-0.5 text-xs font-semibold text-accent">
            <template v-if="isProcessingAnyReceipt">
              Reading receipt data. Please wait...
            </template>
            <template v-else>
              {{ files.length }} uploaded - ready for audit
            </template>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative inline-block text-left" @click.stop>
            <div>
              <button
                @click="isDropdownOpen = !isDropdownOpen"
                class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-white border border-slate-300 px-3.5 text-xs font-bold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none"
              >
                <PlusCircle class="h-3.5 w-3.5" />
                Add More Receipts
                <ChevronDown class="w-3.5 h-3.5 ml-1 -mr-1" aria-hidden="true" />
              </button>
            </div>
            <transition
              enter-active-class="transition duration-100 ease-out"
              enter-from-class="transform scale-95 opacity-0"
              enter-to-class="transform scale-100 opacity-100"
              leave-active-class="transition duration-75 ease-in"
              leave-from-class="transform scale-100 opacity-100"
              leave-to-class="transform scale-95 opacity-0"
            >
              <div
                v-if="isDropdownOpen"
                class="absolute z-10 w-48 mt-2 origin-top-right right-0 bg-white divide-y divide-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
              >
                <div class="px-1 py-1">
                  <button
                    class="group flex w-full items-center rounded-md px-2 py-2 text-xs font-bold text-gray-900 hover:bg-accent hover:text-white"
                    @click="triggerFileInput('single'); isDropdownOpen = false"
                  >
                    <UploadCloud class="mr-2 h-4 w-4 text-accent group-hover:text-white" aria-hidden="true" />
                    Single Upload
                  </button>
                  <button
                    class="group flex w-full items-center rounded-md px-2 py-2 text-xs font-bold text-gray-900 hover:bg-accent hover:text-white"
                    @click="triggerFileInput('multi'); isDropdownOpen = false"
                  >
                    <Layers class="mr-2 h-4 w-4 text-accent group-hover:text-white" aria-hidden="true" />
                    Multiple Upload
                  </button>
                </div>
              </div>
            </transition>
          </div>
        </div>
      </div>

      <div
        :class="[
          'mb-4 flex min-h-[120px] flex-col items-center justify-center rounded-xl border border-dashed p-5 text-center transition-colors',
          isDragging ? 'border-accent bg-accent/5' : 'border-slate-200 bg-slate-50/60',
        ]"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
      >
        <UploadCloud :class="['h-5 w-5', isDragging ? 'text-accent' : 'text-slate-400']" />
        <p class="mt-2 text-xs font-bold text-slate-500">
          Drop more receipts here
        </p>
      </div>
    </section>

    <Transition name="fade">
      <div
        v-if="localError"
        class="flex items-center justify-between rounded-lg border border-danger/30 bg-danger/10 p-2 text-[10px] font-bold uppercase tracking-widest text-danger"
      >
        <span>{{ localError }}</span>
        <button class="hover:text-danger-700" type="button" @click="localError = null">
          <X class="h-3.5 w-3.5" />
        </button>
      </div>
    </Transition>

    <TransitionGroup v-if="files.length > 0" name="file-list" tag="div" class="flex flex-col gap-3">
      <div
        v-for="(entry, index) in files"
        :key="entry.name + index"
        class="flex items-center gap-3 rounded-xl border border-accent/15 bg-accent-50/20 p-3"
      >
        <div class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white">
          <template v-if="entry.previews?.length > 0">
            <div
              v-for="(preview, idx) in entry.previews.slice(0, 3)"
              :key="idx"
              class="absolute h-full w-full rounded-lg overflow-hidden border border-slate-200 bg-white shadow-sm transition-transform"
              :style="{ transform: `translate(${idx * 4}px, ${idx * 4}px)`, zIndex: 10 - idx }"
            >
              <img v-if="preview" :src="preview" class="h-full w-full object-cover" alt="" />
              <FileText v-else class="h-5 w-5 text-slate-300 m-auto mt-4" />
            </div>
            <div v-if="entry.previews.length > 3" class="absolute -bottom-1 -right-1 z-20 rounded bg-slate-800 px-1 text-[8px] font-bold text-white">
              +{{ entry.previews.length - 3 }}
            </div>
          </template>
          <FileText v-else class="h-5 w-5 text-slate-300" />

          <div v-if="entry.ocrStatus === 'processing'" class="absolute inset-0 z-30 pointer-events-none rounded-lg overflow-hidden">
            <div class="h-0.5 w-full bg-accent/80 animate-scan"></div>
            <div class="absolute inset-0 bg-accent/5"></div>
          </div>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center justify-between gap-3">
            <p class="truncate text-xs font-bold text-slate-700">{{ entry.name }}</p>
            <span class="shrink-0 text-[9px] font-mono uppercase text-slate-400">{{ formatSize(entry.size) }}</span>
          </div>

          <div
            v-if="entry.ocrStatus === 'processing'"
            class="mt-1 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-accent animate-pulse"
          >
            <span
              class="h-2.5 w-2.5 rounded-full border-2 border-current border-t-transparent animate-spin"
              aria-hidden="true"
            />
            Reading receipt...
          </div>
          <div v-else-if="entry.ocrStatus === 'done' && entry.ocrData" class="mt-1 flex flex-wrap gap-2">
            <span class="flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-accent">
              <CheckCircle class="h-3 w-3" />
              PHP {{ Number(entry.ocrData.amount).toLocaleString() }}
            </span>
            <span
              :class="[
                'border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-widest',
                entry.ocrData.confidence < 80
                  ? 'bg-warning/10 border-warning/20 text-amber-700'
                  : 'bg-slate-50 border-slate-200 text-slate-500',
              ]"
            >
              {{ entry.ocrData.confidence }}% ACCURACY
              <span v-if="entry.ocrData.confidence < 80" class="ml-1">CHECK DETAILS</span>
            </span>
          </div>
        </div>

        <button
          class="inline-flex h-8 w-fit shrink-0 items-center justify-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 text-xs font-bold text-danger transition-colors hover:bg-red-100"
          type="button"
          @click="removeFile(index)"
        >
          <Trash2 class="h-3.5 w-3.5" />
          Delete
        </button>
      </div>
    </TransitionGroup>

    <ConfirmModal
      :is-open="showDropConfirmModal"
      title="Multiple Files Dropped"
      message="You dropped multiple files. Are these separate receipts or pages of a single receipt?"
      confirm-text="Single Multi-Page Receipt"
      cancel-text="Separate Receipts"
      @confirm="handleConfirmDrop('multi')"
      @close="handleConfirmDrop('single')"
    />
  </div>
</template>

<style scoped>
.file-list-enter-active,
.file-list-leave-active {
  transition: opacity 0.15s linear;
}

.file-list-enter-from,
.file-list-leave-to {
  opacity: 0;
}

@keyframes scan {
  0% {
    transform: translateY(0);
    opacity: 0;
  }
  10% {
    opacity: 1;
  }
  90% {
    opacity: 1;
  }
  100% {
    transform: translateY(56px);
    opacity: 0;
  }
}

.animate-scan {
  animation: scan 1.5s linear infinite;
}
</style>
