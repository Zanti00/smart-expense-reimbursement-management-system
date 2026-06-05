import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const MOCK_REIMBURSEMENTS = [
  { id: 'RM-2024-001', description: 'Q1 Lab Supplies', amount: 12500, category: 'Lab Supplies', status: 'approved', date: '2024-03-15', submittedBy: 'John Santos', receipts: 3 },
  { id: 'RM-2024-002', description: 'Client Entertainment – Makati', amount: 4800, category: 'Entertainment', status: 'submitted', date: '2024-04-01', submittedBy: 'Maria Cruz', receipts: 2 },
  { id: 'RM-2024-003', description: 'Office Supplies Purchase', amount: 1250, category: 'Office Supplies', status: 'rejected', date: '2024-03-28', submittedBy: 'Alex Reyes', receipts: 1 },
  { id: 'RM-2024-004', description: 'Field Service Transportation', amount: 3200, category: 'Transportation', status: 'paid', date: '2024-02-20', submittedBy: 'John Santos', receipts: 4 },
  { id: 'RM-2024-005', description: 'Calibration Equipment', amount: 28750, category: 'Equipment', status: 'review', date: '2024-04-03', submittedBy: 'Maria Cruz', receipts: 1 },
  { id: 'RM-2024-006', description: 'Training Materials', amount: 5500, category: 'Training', status: 'draft', date: '2024-04-04', submittedBy: 'John Santos', receipts: 0 },
]

export const useReimbursementStore = defineStore('reimbursement', () => {
  const items = ref([...MOCK_REIMBURSEMENTS])
  const isLoading = ref(false)

  const total = computed(() => items.value.reduce((s, i) => s + i.amount, 0))
  const pending = computed(() => items.value.filter(i => i.status === 'submitted'))
  const approved = computed(() => items.value.filter(i => i.status === 'approved'))

  async function fetchAll() {
    isLoading.value = true
    await new Promise(r => setTimeout(r, 400))
    items.value = [...MOCK_REIMBURSEMENTS]
    isLoading.value = false
  }

  async function submit(data) {
    isLoading.value = true
    await new Promise(r => setTimeout(r, 600))
    const newItem = {
      id: `RM-2024-00${items.value.length + 1}`,
      ...data,
      status: 'submitted',
      date: new Date().toISOString().split('T')[0],
      receipts: data.receipts?.length || 0
    }
    items.value.unshift(newItem)
    isLoading.value = false
    return newItem
  }

  async function approve(id, remarks = '') {
    const item = items.value.find(i => i.id === id)
    if (item) {
      item.status = 'approved'
      item.reviewerNotes = remarks
    }
  }

  async function reject(id, remarks = '') {
    const item = items.value.find(i => i.id === id)
    if (item) {
      item.status = 'rejected'
      item.reviewerNotes = remarks
    }
  }

  async function setReceiptDecision(id, receiptId, review) {
    const item = items.value.find(i => i.id === id)
    if (!item) return

    item.receiptReviews = {
      ...(item.receiptReviews || {}),
      [receiptId]: review,
    }
  }

  return { items, isLoading, total, pending, approved, fetchAll, submit, approve, reject, setReceiptDecision }
})
