<script setup>
import { computed } from 'vue'
import OCRField from '@/components/base/OCRField.vue'

const props = defineProps({
  amount: { type: [Number, String], default: '' },
  vat: { type: [Number, String], default: '' },
  vendor: { type: String, default: '' },
  tin: { type: String, default: '' },
  date: { type: String, default: '' },
  confidence: { type: Number, default: 100 },
  ocrStatus: { type: String, default: '' },
  overLimit: { type: String, default: null },
  invalidTin: { type: String, default: null }
})

const emit = defineEmits([
  'update:amount',
  'update:vat',
  'update:vendor',
  'update:tin',
  'update:date'
])

const localAmount = computed({
  get: () => props.amount,
  set: (val) => emit('update:amount', val)
})

const localVat = computed({
  get: () => props.vat,
  set: (val) => emit('update:vat', val)
})

const localVendor = computed({
  get: () => props.vendor,
  set: (val) => emit('update:vendor', val)
})

function formatTin(value) {
  if (!value) return ''
  // Strip all non-digits, cap at 12 digits
  const digits = value.replace(/\D/g, '').slice(0, 12)
  const chunks = digits.match(/.{1,3}/g) || []
  return chunks.join('-')
}

const localTin = computed({
  get: () => formatTin(props.tin),
  set: (val) => {
    const formatted = formatTin(val)
    emit('update:tin', formatted)
  }
})

const localDate = computed({
  get: () => {
    if (!props.date) return ''
    // Extract YYYY-MM-DD pattern directly if present (e.g. from ISO or SQL timestamp)
    const match = props.date.match(/^(\d{4}-\d{2}-\d{2})/)
    if (match) return match[1]

    // Fallback: Parse via JS Date object
    try {
      const parsedDate = new Date(props.date)
      if (!isNaN(parsedDate.getTime())) {
        const year = parsedDate.getFullYear()
        const month = String(parsedDate.getMonth() + 1).padStart(2, '0')
        const day = String(parsedDate.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
      }
    } catch (e) {
      // Ignore parsing errors and return raw fallback or empty
    }
    return ''
  },
  set: (val) => emit('update:date', val)
})
</script>

<template>
  <div :class="ocrStatus === 'processing' ? 'opacity-50 pointer-events-none grayscale' : ''">
    <OCRField
      v-model="localAmount"
      label="Amount (PHP)"
      type="number"
      :confidence="confidence"
      :error="!!overLimit"
      :error-message="overLimit"
    />
    <OCRField
      v-model="localVat"
      label="VAT Amount"
      type="number"
      :confidence="confidence"
    />
    <OCRField
      v-model="localVendor"
      label="Store / Vendor"
      :confidence="confidence"
    />
    <OCRField
      v-model="localTin"
      label="TIN Number"
      :confidence="confidence"
      :error="!!invalidTin"
      :error-message="invalidTin"
      maxlength="15"
    />
    <OCRField
      v-model="localDate"
      label="Date"
      type="date"
      :confidence="confidence"
    />
  </div>
</template>
