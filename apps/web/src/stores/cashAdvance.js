import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const MOCK_ADVANCES = [
  { id: 'CA-2024-001', purpose: 'Site Visit – Laguna Plant', amount: 8000, balance: 8000, status: 'unliquidated', date: '2026-04-01', requestedBy: 'John Santos', dueDate: '2026-04-08' },
  { id: 'CA-2024-002', purpose: 'Client Demo Preparation', amount: 15000, balance: 15000, status: 'unliquidated', date: '2026-03-25', requestedBy: 'Maria Cruz', dueDate: '2026-04-01' }, // Overdue
  { id: 'CA-2024-003', purpose: 'Emergency Parts Purchase', amount: 22000, balance: 0, status: 'liquidated', date: '2026-03-20', requestedBy: 'Alex Reyes', dueDate: '2026-03-27' },
  { id: 'CA-2024-004', purpose: 'Regional Sales Conference', amount: 35000, balance: 35000, status: 'unliquidated', date: '2026-04-04', requestedBy: 'John Santos', dueDate: '2026-04-11' },
  { id: 'CA-2024-005', purpose: 'Logistics Fleet Maintenance', amount: 42000, balance: 42000, status: 'pending', date: '2026-03-20', requestedBy: 'Alex Reyes', dueDate: '2026-03-27' }, // Was Overdue, now Protected
  { id: 'CA-2024-006', purpose: 'Regional Team Building', amount: 12000, balance: 12000, status: 'submitted', date: '2026-04-06', requestedBy: 'Maria Cruz', dueDate: '2026-04-13' },
]

export const useCashAdvanceStore = defineStore('cashAdvance', () => {
  const items = ref([...MOCK_ADVANCES])
  const isLoading = ref(false)

  const pendingCount = computed(() => items.value.filter(i => i.status === 'submitted').length)
  const totalOutstanding = computed(() => items.value.reduce((s, i) => s + i.balance, 0))

  async function fetchAll() {
    isLoading.value = true
    await new Promise(r => setTimeout(r, 400))
    items.value = [...MOCK_ADVANCES]
    isLoading.value = false
  }

  async function request(data) {
    const newItem = {
      id: `CA-2024-00${items.value.length + 1}`,
      ...data,
      balance: data.amount,
      status: 'submitted',
      date: new Date().toISOString().split('T')[0]
    }
    items.value.unshift(newItem)
    return newItem
  }

  async function approveRequest(id) {
    const item = items.value.find(i => i.id === id)
    if (item) item.status = 'approved'
  }

  async function rejectRequest(id) {
    const item = items.value.find(i => i.id === id)
    if (item) item.status = 'rejected'
  }

  async function approveSettlement(id) {
    const item = items.value.find(i => i.id === id)
    if (item) {
      item.status = 'liquidated'
      item.balance = 0
    }
  }

  async function rejectSettlement(id) {
    const item = items.value.find(i => i.id === id)
    if (item) {
      item.status = 'unliquidated'
    }
  }

  return { items, isLoading, pendingCount, totalOutstanding, fetchAll, request, approveRequest, rejectRequest, approveSettlement, rejectSettlement }
})
