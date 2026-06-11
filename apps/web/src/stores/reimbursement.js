import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiFetch } from '../utils/apiFetch'

export const useReimbursementStore = defineStore('reimbursement', () => {
  const items = ref([])
  const currentItem = ref(null)
  const isLoading = ref(false)

  const totalAmount = computed(() => items.value.reduce((s, i) => s + parseFloat(i.amount), 0))
  const totalPending = computed(() => items.value.filter(i => i.status === 'pending').length)
  const totalSubmitted = computed(() => items.value.filter(i => i.status === 'submitted').length)
  const totalApproved = computed(() => items.value.filter(i => i.status === 'approved').length)
  const totalRejected = computed(() => items.value.filter(i => i.status === 'rejected').length)
  const totalGranted = computed(() => items.value.filter(i => i.status === 'granted').length)

  async function fetchAll() {
    isLoading.value = true
    try {
      const response = await apiFetch('/api/serms/reimbursements')
      if (!response.ok) throw new Error('Failed to fetch reimbursements')
      const data = await response.json()
      items.value = data
    } catch (e) {
      console.error(e)
    } finally {
      isLoading.value = false
    }
  }

  async function fetchOne(id) {
    isLoading.value = true
    try {
      const response = await apiFetch(`/api/serms/reimbursements/${id}`)
      if (!response.ok) throw new Error('Failed to fetch reimbursement')
      const data = await response.json()
      currentItem.value = data
      return data
    } catch (e) {
      console.error(e)
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function submit(formData) {
    isLoading.value = true
    try {
      const response = await apiFetch('/api/serms/reimbursements', {
        method: 'POST',
        body: formData
      })
      if (!response.ok) throw new Error('Failed to submit reimbursement')
      const json = await response.json()
      items.value.unshift(json.data)
      return json.data
    } catch (e) {
      console.error(e)
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function approve(id) {
    isLoading.value = true
    try {
      const response = await apiFetch(`/api/serms/reimbursements/${id}/approve`, {
        method: 'POST'
      })
      if (!response.ok) throw new Error('Failed to approve')
      const json = await response.json()
      const index = items.value.findIndex(i => i.id == id)
      if (index !== -1) items.value[index] = json.data
      if (currentItem.value?.id == id) currentItem.value = json.data
      return json.data
    } catch (e) {
      console.error(e)
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function reject(id, comment) {
    isLoading.value = true
    try {
      const response = await apiFetch(`/api/serms/reimbursements/${id}/reject`, {
        method: 'POST',
        body: JSON.stringify({ comment })
      })
      if (!response.ok) throw new Error('Failed to reject')
      const json = await response.json()
      const index = items.value.findIndex(i => i.id == id)
      if (index !== -1) items.value[index] = json.data
      if (currentItem.value?.id == id) currentItem.value = json.data
      return json.data
    } catch (e) {
      console.error(e)
      throw e
    } finally {
      isLoading.value = false
    }
  }

  async function updateNotes(id, notes) {
    isLoading.value = true
    try {
      const response = await apiFetch(`/api/serms/reimbursements/${id}`, {
        method: 'PATCH',
        body: JSON.stringify({ admin_notes: notes })
      })
      if (!response.ok) throw new Error('Failed to update notes')
      const json = await response.json()
      const index = items.value.findIndex(i => i.id == id)
      if (index !== -1) items.value[index] = json.data
      if (currentItem.value?.id == id) currentItem.value = json.data
      return json.data
    } catch (e) {
      console.error(e)
      throw e
    } finally {
      isLoading.value = false
    }
  }

  return {
    items, currentItem, isLoading,
    totalAmount, totalPending, totalSubmitted, totalApproved, totalRejected, totalGranted,
    fetchAll, fetchOne, submit, approve, reject, updateNotes
  }
})
