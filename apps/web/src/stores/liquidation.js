import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const MOCK_SETTLEMENTS = [
  { 
    id: 'LIQ-001', 
    advanceId: 'CA-2024-001', 
    items: [
      { id: 1, category: 'Meals', description: 'Client Lunch', amount: 4500 },
      { id: 2, category: 'Travel', description: 'Grab to Site', amount: 1500 }
    ],
    receipts: [{ name: 'receipt_01.jpg' }],
    totalExpenses: 6000,
    variance: 2000, // Total Advanced 8000 - 6000 = 2000 Overpayment
    status: 'pending',
    submittedAt: '2026-04-05T10:00:00Z'
  },
  { 
    id: 'LIQ-002', 
    advanceId: 'CA-2024-002', 
    items: [
      { id: 3, category: 'Materials', description: 'Cable Spools', amount: 12000 },
      { id: 4, category: 'Travel', description: 'Truck Rental', amount: 4500 }
    ],
    receipts: [{ name: 'receipt_02.pdf' }],
    totalExpenses: 16500,
    variance: -1500, // Total Advanced 15000 - 16500 = -1500 Abono
    status: 'pending',
    submittedAt: '2026-04-06T09:12:44Z'
  }
]

export const useLiquidationStore = defineStore('liquidation', () => {
  const settlements = ref([...MOCK_SETTLEMENTS])
  const DAILY_PENALTY_PHP = 55 // approximately $1.00

  /**
   * Calculates the aging and penalty for a cash advance.
   * Logic: 7-day grace period. Day 8 starts penalty.
   */
  function calculateAging(advance) {
    const issueDate = new Date(advance.date)
    const today = new Date()
    const diffTime = Math.abs(today - issueDate)
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    
    let penalty = 0
    let isOverdue = false
    
    const isAuditing = advance.status === 'pending' || advance.status === 'under-review'
    
    if (diffDays > 7 && advance.status !== 'liquidated' && !isAuditing) {
      isOverdue = true
      penalty = (diffDays - 7) * DAILY_PENALTY_PHP
    }

    return {
      daysSinceIssue: diffDays,
      isOverdue,
      penalty,
      graceRemaining: Math.max(0, 7 - diffDays)
    }
  }

  async function submitSettlement(advanceId, { items, receipts, totalExpenses, variance }) {
    const newSettlement = {
      id: `LIQ-${Date.now()}`,
      advanceId,
      items,
      receipts,
      totalExpenses,
      variance,
      status: 'pending', // Waiting for admin review
      submittedAt: new Date().toISOString()
    }
    
    settlements.value.unshift(newSettlement)
    return newSettlement
  }

  return { settlements, calculateAging, submitSettlement }
})
