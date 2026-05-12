import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useAuthStore } from './auth'

export const useReceiptStore = defineStore('receipts', () => {
  const auth = useAuthStore()
  
  // Mock Data
  const receipts = ref([
    {
      id: 'RCPT-2026-001',
      uploader: 'kyle.l',
      fileName: 'hotel_invoice_marriott.pdf',
      fileType: 'pdf',
      fileSize: 2.4 * 1024 * 1024,
      date: '04/15/2026',
      amount: 15400.00,
      category: 'Lodging',
      status: 'Approved',
      hash: 'a1b2c3d4e5f6g7h8i9j0',
      thumbnail: null,
      isDeleted: false
    },
    {
      id: 'RCPT-2026-002',
      uploader: 'anna.sm',
      fileName: 'flight_ticket.png',
      fileType: 'png',
      fileSize: 1.1 * 1024 * 1024,
      date: '04/16/2026',
      amount: 8500.00,
      category: 'Transportation',
      status: 'Pending',
      hash: 'b1c2d3e4f5g6h7i8j9k0',
      thumbnail: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', // transparent pixel
      isDeleted: false
    },
    {
      id: 'RCPT-2026-003',
      uploader: 'kyle.l',
      fileName: 'client_dinner_receipt.jpg',
      fileType: 'jpg',
      fileSize: 3.5 * 1024 * 1024,
      date: '03/28/2026',
      amount: 4200.50,
      category: 'Meals',
      status: 'Liquidated',
      hash: 'c1d2e3f4g5h6i7j8k9l0',
      thumbnail: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
      isDeleted: false
    },
    {
      id: 'RCPT-2026-004',
      uploader: 'kyle.l',
      fileName: 'supplies_staples.png',
      fileType: 'png',
      fileSize: 10.1 * 1024 * 1024, // Notice this is somewhat large, mock data
      date: '04/18/2026',
      amount: 1250.00,
      category: 'Supplies',
      status: 'Unliquidated',
      hash: 'd1e2f3g4h5i6j7k8l9m0',
      thumbnail: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
      isDeleted: false
    }
  ])

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
