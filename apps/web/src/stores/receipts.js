import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useAuthStore } from './auth'

export const useReceiptStore = defineStore('receipts', () => {
  const auth = useAuthStore()
  
  const receipts = ref([])

  // Filters State
  const filters = ref({
    dateRange: { start: '', end: '' },
    uploader: '',
    category: '',
    status: '',
    amountRange: { min: null, max: null }
  })

  // Hash Map for Duplicate Checks
  const existingHashes = computed(() => {
    const map = new Set()
    receipts.value.forEach(r => {
      if (!r.isDeleted) map.add(r.hash)
    })
    return map
  })

  // Getters
  const visibleReceipts = computed(() => {
    let filtered = receipts.value.filter(r => !r.isDeleted)

    // Role-based visibility
    if (!auth.isAdmin) {
      filtered = filtered.filter(r => r.uploader === auth.user?.username || r.uploader === 'kyle.l') // Mock logic, assume 'kyle.l' is current user
    }

    // Apply Active Filters
    if (filters.value.uploader) {
      filtered = filtered.filter(r => r.uploader === filters.value.uploader)
    }
    if (filters.value.category) {
      filtered = filtered.filter(r => r.category === filters.value.category)
    }
    if (filters.value.status) {
      filtered = filtered.filter(r => r.status === filters.value.status)
    }
    if (filters.value.amountRange.min !== null && filters.value.amountRange.min !== '') {
      filtered = filtered.filter(r => r.amount >= Number(filters.value.amountRange.min))
    }
    if (filters.value.amountRange.max !== null && filters.value.amountRange.max !== '') {
      filtered = filtered.filter(r => r.amount <= Number(filters.value.amountRange.max))
    }
    
    // Simple Date Range filtering based on '04/15/2026' string
    if (filters.value.dateRange.start && filters.value.dateRange.end) {
      const start = new Date(filters.value.dateRange.start)
      const end = new Date(filters.value.dateRange.end)
      filtered = filtered.filter(r => {
        const d = new Date(r.date)
        return d >= start && d <= end
      })
    }

    // Sorting by newest
    return filtered.sort((a, b) => new Date(b.date) - new Date(a.date))
  })

  // Actions
  async function simulateUpload(fileMeta, mockHash) {
    return new Promise((resolve, reject) => {
      // Duplicate check (Client-side)
      if (existingHashes.value.has(mockHash)) {
        reject(new Error('This file has already been uploaded (Duplicate detected).'))
        return
      }
      
      if (fileMeta.size > 10 * 1024 * 1024) {
        reject(new Error('File size exceeds 10MB.'))
        return
      }

      // Optimistic Upload creation
      const newReceipt = {
        id: `RCPT-2026-00${receipts.value.length + 1}`,
        uploader: auth.user?.username || 'kyle.l',
        fileName: fileMeta.name,
        fileType: fileMeta.type,
        fileSize: fileMeta.size,
        date: new Date().toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' }),
        amount: 0, // Requires manual entry after processing
        category: 'Uncategorized',
        status: 'Processing',
        hash: mockHash,
        thumbnail: null,
        isDeleted: false
      }

      receipts.value.unshift(newReceipt)

      // Simulate Hash verification and Server Processing (optimistic UI)
      setTimeout(() => {
        const index = receipts.value.findIndex(r => r.id === newReceipt.id)
        if (index !== -1) {
          receipts.value[index].status = 'Pending' // Finish processing
        }
        resolve(newReceipt)
      }, 2500)
    })
  }

  function softDelete(id) {
    const rx = receipts.value.find(r => r.id === id)
    if (rx) rx.isDeleted = true
  }

  function hardDelete(id) {
    receipts.value = receipts.value.filter(r => r.id !== id)
  }

  function clearFilters() {
    filters.value = {
      dateRange: { start: '', end: '' },
      uploader: '',
      category: '',
      status: '',
      amountRange: { min: null, max: null }
    }
  }

  return {
    receipts,
    filters,
    existingHashes,
    visibleReceipts,
    simulateUpload,
    softDelete,
    hardDelete,
    clearFilters
  }
})
