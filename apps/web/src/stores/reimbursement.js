import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const MOCK_REIMBURSEMENTS = [
  { id: 'RM-2024-001', description: 'Q1 Lab Supplies', amount: 12500, category: 'Lab Supplies', status: 'approved', date: '2024-03-15', submittedBy: 'John Santos', receipts: 3 },
  { id: 'RM-2024-002', description: 'Client Entertainment – Makati', amount: 4800, category: 'Entertainment', status: 'submitted', date: '2024-04-01', submittedBy: 'Maria Cruz', receipts: 2 },
  { id: 'RM-2024-003', description: 'Office Supplies Purchase', amount: 1250, category: 'Office Supplies', status: 'rejected', date: '2024-03-28', submittedBy: 'Alex Reyes', receipts: 1 },
  { id: 'RM-2024-004', description: 'Field Service Transportation', amount: 3200, category: 'Transportation', status: 'paid', date: '2024-02-20', submittedBy: 'John Santos', receipts: 4 },
  { id: 'RM-2024-005', description: 'Calibration Equipment', amount: 28750, category: 'Equipment', status: 'review', date: '2024-04-03', submittedBy: 'Maria Cruz', receipts: 1 },
  { id: 'RM-2024-006', description: 'Training Materials', amount: 5500, category: 'Training', status: 'draft', date: '2024-04-04', submittedBy: 'John Santos', receipts: 0 },
  { id: 'RM-2024-007', description: 'BGC Client Meeting Meals', amount: 8650, category: 'Meals', status: 'submitted', date: '2024-04-08', submittedBy: 'Lia Cruz', receipts: 2 },
  { id: 'RM-2024-008', description: 'Warehouse Safety Supplies', amount: 14200, category: 'Lab Supplies', status: 'approved', date: '2024-04-10', submittedBy: 'Noel Garcia', receipts: 5 },
  { id: 'RM-2024-009', description: 'Provincial Site Visit Fuel', amount: 6100, category: 'Transportation', status: 'review', date: '2024-04-12', submittedBy: 'Marco Santos', receipts: 3 },
  { id: 'RM-2024-010', description: 'Replacement Printer Toner', amount: 3900, category: 'Office Supplies', status: 'paid', date: '2024-04-15', submittedBy: 'Ana Reyes', receipts: 1 },
  { id: 'RM-2024-011', description: 'Equipment Repair Parts', amount: 17450, category: 'Equipment', status: 'submitted', date: '2024-04-18', submittedBy: 'John Santos', receipts: 4 },
  { id: 'RM-2024-012', description: 'Team Certification Fees', amount: 9800, category: 'Training', status: 'approved', date: '2024-04-20', submittedBy: 'Maria Cruz', receipts: 2 },
  { id: 'RM-2024-013', description: 'QC Lab Consumables', amount: 22300, category: 'Lab Supplies', status: 'rejected', date: '2024-04-22', submittedBy: 'Alex Reyes', receipts: 6 },
  { id: 'RM-2024-014', description: 'Courier and Document Delivery', amount: 1850, category: 'Transportation', status: 'submitted', date: '2024-04-24', submittedBy: 'Lia Cruz', receipts: 2 },
  { id: 'RM-2024-015', description: 'Monthly Pantry Restock', amount: 7200, category: 'Office Supplies', status: 'draft', date: '2024-04-26', submittedBy: 'Noel Garcia', receipts: 0 },
  { id: 'RM-2024-016', description: 'Conference Booth Materials', amount: 31500, category: 'Marketing', status: 'review', date: '2024-04-28', submittedBy: 'Marco Santos', receipts: 4 },
  { id: 'RM-2024-017', description: 'Regulatory Filing Fees', amount: 11200, category: 'Professional Fees', status: 'approved', date: '2024-05-02', submittedBy: 'Ana Reyes', receipts: 1 },
  { id: 'RM-2024-018', description: 'Emergency Generator Fuel', amount: 4600, category: 'Utilities', status: 'paid', date: '2024-05-05', submittedBy: 'John Santos', receipts: 2 },
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
      id: `RM-2024-${String(items.value.length + 1).padStart(3, '0')}`,
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
