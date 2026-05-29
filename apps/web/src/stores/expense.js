import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useAuthStore } from './auth'

export const useExpenseStore = defineStore('expense', () => {
  const auth = useAuthStore()

  const items = ref([
    {
      id: "RCPT-2026-088",
      dbId: 88,
      uploadedBy: "kyle.l",
      fileName: "bir_official_receipt_meals.png",
      fileType: "image/png",
      fileSize: 1048576,
      vendorName: "Jollibee Food Corp",
      transactionDate: "2026-03-15",
      totalAmount: 450.00,
      vatAmount: 48.21,
      tin: "000-111-222-000",
      ocrConfidenceScore: 92,
      ocrFlagged: false,
      thumbnail: null,
      isDeleted: false,
      createdAt: new Date(Date.now() - 65 * 24 * 60 * 60 * 1000).toISOString(), // 65 days ago
      deletionWarningSent: true,
      hash: "7f83b123456789abcdef7f83b123456789abcdef7f83b123456789abcdef1234",
      category: "Meals"
    },
    {
      id: "RCPT-2026-089",
      dbId: 89,
      uploadedBy: "kyle.l",
      fileName: "taxi_fare_manila.pdf",
      fileType: "application/pdf",
      fileSize: 2097152,
      vendorName: "Grab Car PH",
      transactionDate: "2026-03-05",
      totalAmount: 650.00,
      vatAmount: 0.00,
      tin: "123-456-789-000",
      ocrConfidenceScore: 88,
      ocrFlagged: false,
      thumbnail: null,
      isDeleted: false,
      createdAt: new Date(Date.now() - 75 * 24 * 60 * 60 * 1000).toISOString(), // 75 days ago
      deletionWarningSent: true,
      hash: "8f83b123456789abcdef7f83b123456789abcdef7f83b123456789abcdef5678",
      category: "Transportation"
    },
    {
      id: "RCPT-2026-090",
      dbId: 90,
      uploadedBy: "kyle.l",
      fileName: "office_supplies_notebooks.jpg",
      fileType: "image/jpeg",
      fileSize: 524288,
      vendorName: "National Book Store",
      transactionDate: "2026-05-15",
      totalAmount: 1250.00,
      vatAmount: 133.93,
      tin: "987-654-321-000",
      ocrConfidenceScore: 95,
      ocrFlagged: false,
      thumbnail: null,
      isDeleted: false,
      createdAt: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000).toISOString(), // 4 days ago
      deletionWarningSent: false,
      hash: "9f83b123456789abcdef7f83b123456789abcdef7f83b123456789abcdef9999",
      category: "Supplies"
    }
  ])
  const isLoading = ref(false)

  // Pending file to pass between ExpenseManagement upload → ExpenseForm
  const pendingFile = ref(null)

  const visibleExpenses = computed(() => {
    let filtered = [...items.value].filter(r => !r.isDeleted)

    // Role-based visibility
    if (!auth.isAdmin) {
      filtered = filtered.filter(r => r.uploadedBy === auth.user?.username || r.uploadedBy === 'kyle.l')
    }

    // Sort newest first
    return filtered.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
  })

  async function fetchAll() {
    isLoading.value = true
    try {
      const response = await fetch('/api/serms/expenses', {
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${auth.token}`
        }
      })
      if (response.ok) {
        const data = await response.json()
        const fetchedItems = data.map(r => ({
          id: `RCPT-2026-${String(r.id).padStart(3, '0')}`,
          dbId: r.id,
          uploadedBy: r.uploader?.name || 'kyle.l',
          fileName: r.file_path.split('/').pop() || 'N/A',
          fileType: r.file_type,
          fileSize: r.file_size_bytes,
          vendorName: r.vendor_name,
          transactionDate: r.transaction_date,
          totalAmount: Number(r.total_amount) || 0,
          vatAmount: Number(r.vat_amount) || 0,
          tin: r.tin,
          ocrConfidenceScore: r.ocr_confidence_score,
          ocrFlagged: r.ocr_flagged,
          thumbnail: null,
          isDeleted: !!r.deleted_at,
          createdAt: r.created_at,
          deletionWarningSent: !!r.deletion_warning_sent,
          hash: r.file_hash,
          category: r.category || 'Uncategorized'
        }))

        // Retain mock records for consistent visual demo state if they don't overlap with DB
        const mockItems = items.value.filter(item => 
          !fetchedItems.some(f => f.id === item.id || f.hash === item.hash || f.fileName === item.fileName)
        )

        items.value = [...fetchedItems, ...mockItems]
      }
    } catch (e) {
      console.error('Failed to fetch expenses from database:', e)
    } finally {
      isLoading.value = false
    }
  }

  async function computeSHA256(file) {
    const buffer = await file.arrayBuffer()
    const hashBuffer = await crypto.subtle.digest("SHA-256", buffer)
    const hashArray = Array.from(new Uint8Array(hashBuffer))
    return hashArray.map((b) => b.toString(16).padStart(2, "0")).join("")
  }

  /**
   * Save a receipt from the expense form.
   * Maps to POST /api/serms/expenses in production.
   */
  async function submit(data) {
    isLoading.value = true

    let fileHash = ''
    if (data.file) {
      try {
        fileHash = await computeSHA256(data.file)
      } catch (e) {
        console.error('Failed to compute file hash:', e)
      }
    }
    if (!fileHash) {
      fileHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855' // empty hash fallback
    }

    let fileType = 'jpeg'
    const typeStr = (data.fileType || '').toLowerCase()
    if (typeStr.includes('pdf')) {
      fileType = 'pdf'
    } else if (typeStr.includes('png')) {
      fileType = 'png'
    }

    const payload = {
      file_path: `receipts/${data.fileName || 'receipt.jpg'}`,
      file_hash: fileHash,
      file_type: fileType,
      file_size_bytes: Number(data.fileSize) || 1024,
      vendor_name: data.vendor || null,
      transaction_date: data.date || new Date().toISOString().split('T')[0],
      total_amount: Number(data.amount) || 0,
      vat_amount: Number(data.vat) || 0,
      tin: data.tin || null,
      invoice_number: null,
      vat_classification: 'vat',
      ocr_confidence_score: Number(data.ocrConfidence) || null,
      category: 'Uncategorized'
    }

    try {
      const response = await fetch('/api/serms/expenses', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${auth.token}`
        },
        body: JSON.stringify(payload)
      })

      if (!response.ok) {
        const errData = await response.json().catch(() => ({}))
        throw new Error(errData.message || `Failed to save receipt to database (Status ${response.status})`)
      }

      const resData = await response.json()
      const savedReceipt = resData.data

      const newReceipt = {
        id: `RCPT-2026-${String(savedReceipt.id).padStart(3, '0')}`,
        dbId: savedReceipt.id,
        uploadedBy: auth.user?.name || auth.user?.username || 'kyle.l',
        fileName: savedReceipt.file_path.split('/').pop() || 'N/A',
        fileType: savedReceipt.file_type,
        fileSize: savedReceipt.file_size_bytes,
        vendorName: savedReceipt.vendor_name,
        transactionDate: savedReceipt.transaction_date,
        totalAmount: Number(savedReceipt.total_amount) || 0,
        vatAmount: Number(savedReceipt.vat_amount) || 0,
        tin: savedReceipt.tin,
        ocrConfidenceScore: savedReceipt.ocr_confidence_score,
        ocrFlagged: savedReceipt.ocr_flagged,
        thumbnail: data.thumbnail || null,
        isDeleted: false,
        createdAt: savedReceipt.created_at,
        deletionWarningSent: !!savedReceipt.deletion_warning_sent,
        hash: savedReceipt.file_hash,
        category: savedReceipt.category || 'Uncategorized'
      }

      items.value.unshift(newReceipt)
      return newReceipt
    } catch (error) {
      console.error(error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  function setPendingFile(fileData) {
    pendingFile.value = fileData
  }

  function clearPendingFile() {
    pendingFile.value = null
  }

  async function softDelete(id) {
    const receipt = items.value.find(r => r.id === id)
    if (!receipt) return

    receipt.isDeleted = true // optimistic update

    if (receipt.dbId) {
      try {
        await fetch(`/api/serms/expenses/${receipt.dbId}`, {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'Authorization': `Bearer ${auth.token}`
          }
        })
      } catch (e) {
        console.error('Failed to soft delete receipt in database:', e)
      }
    }
  }

  return {
    items,
    isLoading,
    visibleExpenses,
    pendingFile,
    fetchAll,
    submit,
    setPendingFile,
    clearPendingFile,
    softDelete,
  }
})
