import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiFetch } from '../utils/apiFetch'

export const useLiquidationStore = defineStore('liquidation', () => {
  const settlements = ref([])
  const isLoading = ref(false)
  const DAILY_PENALTY_PHP = 55 // approximately $1.00

  /**
   * Fetch all liquidations from the backend.
   */
  async function fetchSettlements() {
    isLoading.value = true
    try {
      const response = await apiFetch('/api/serms/liquidations', {
        credentials: 'include',
      })
      if (response.ok) {
        settlements.value = await response.json()
      }
    } catch (err) {
      console.error('Failed to fetch liquidations', err)
    } finally {
      isLoading.value = false
    }
  }

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
    
    const isAuditing = advance.status === 'pending' || advance.status === 'under-review' || advance.status === 'approved'
    
    if (diffDays > 7 && advance.status !== 'liquidated' && advance.status !== 'settled' && !isAuditing) {
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

  /**
   * Submit settlement to backend
   */
  async function submitSettlement(advanceId, payload) {
    try {
      const formData = new FormData();
      formData.append('cash_advance_id', advanceId);
      formData.append('total_expense_amount', payload.totalExpenses);
      if (payload.shortfall_explanation) {
        formData.append('shortfall_explanation', payload.shortfall_explanation);
      }
      
      if (payload.reportAttachment) {
        formData.append('report_attachment', payload.reportAttachment);
      }

      const formattedReceipts = payload.receipts.map(r => ({
        id: r.ocrData?.id || r.id, // backend DB ID
        vendor_name: r.ocrData?.vendor || r.merchantName,
        transaction_date: r.ocrData?.date || r.transactionDate,
        total_amount: r.ocrData?.amount || r.amount,
        vat_amount: r.ocrData?.vat || r.vat,
        tin: r.ocrData?.tin || r.tinNumber,
        invoice_number: r.ocrData?.invoiceNumber || r.invoiceNumber,
      }));
      formData.append('receipts', JSON.stringify(formattedReceipts));

      const response = await apiFetch('/api/serms/liquidations', {
        method: 'POST',
        credentials: 'include',
        body: formData,
      });

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to submit liquidation')
      }

      await fetchSettlements()
      return await response.json()
    } catch (err) {
      console.error('Failed to submit liquidation', err)
      throw err;
    }
  }

  /**
   * Audit settlement (approve or reject)
   */
  async function auditSettlement(id, payload) {
    try {
      const response = await apiFetch(`/api/serms/liquidations/${id}/audit`, {
        method: 'POST',
        credentials: 'include',
        body: JSON.stringify(payload), // contains status, password, admin_note
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to audit liquidation')
      }

      await fetchSettlements()
      return await response.json()
    } catch (err) {
      console.error('Failed to audit liquidation', err)
      throw err;
    }
  }

  return { settlements, isLoading, calculateAging, fetchSettlements, submitSettlement, auditSettlement }
})
