import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useAuthStore } from "./auth";
import { apiFetch } from "../utils/apiFetch";
export const useCashAdvanceStore = defineStore("cashAdvance", () => {
  const items = ref([]);
  const isLoading = ref(false);

  const pendingCount = computed(
    () => items.value.filter((i) => i.status === "pending").length,
  );
  const totalOutstanding = computed(() =>
    items.value.reduce((s, i) => s + (i.amount || 0), 0),
  ); // simplistic for now

  function normalizeAdvance(item = {}) {
    const document = item.document || null;

    return {
      ...item,
      document,
      // Map backend fields to frontend expected fields
      userId: item.user_id ?? item.userId,
      requestedBy: item.requester?.name || item.requestedBy || "Unknown",
      dueDate: item.expected_liquidation_date ?? item.dueDate,
      date: item.submitted_at || item.date || item.created_at,
      // outstanding_balance is DB-authoritative. Fall back to full amount for
      // advances not yet disbursed where balance is still null.
      balance: item.outstanding_balance !== null && item.outstanding_balance !== undefined
        ? Number(item.outstanding_balance)
        : Number(item.balance ?? item.amount ?? 0),
      adminNotes:
        item.adminNotes ??
        (item.approval_actions && item.approval_actions.length
          ? item.approval_actions[item.approval_actions.length - 1].comment
          : null),
      acknowledgedAt: item.acknowledged_at ?? item.acknowledgedAt,
      signatureImage: item.signature ?? item.signatureImage,
      documentUrl: document?.file_url || item.documentUrl || null,
      documentFileName: document?.file_name || item.documentFileName || null,
    };
  }

  function upsertAdvance(item) {
    const existingIndex = items.value.findIndex((i) => i.id == item.id);
    const merged = normalizeAdvance(
      existingIndex === -1 ? item : { ...items.value[existingIndex], ...item },
    );

    if (existingIndex === -1) {
      items.value.unshift(merged);
    } else {
      items.value[existingIndex] = merged;
    }

    return merged;
  }

  async function fetchAll() {
    isLoading.value = true;
    try {
      const response = await apiFetch("/api/serms/cash-advances", {
        credentials: "include",
      });
      if (response.ok) {
        const data = await response.json();
        items.value = data.map(normalizeAdvance);
      }
    } catch (err) {
      console.error("Failed to fetch cash advances", err);
    } finally {
      isLoading.value = false;
    }
  }

  async function request(formData) {
    try {
      const response = await apiFetch("/api/serms/cash-advances", {
        method: "POST",
        credentials: "include",
        body: formData, // Send as FormData to handle files
      });
      if (response.ok) {
        const result = await response.json();
        return upsertAdvance(result.data);
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to create cash advance");
      }
    } catch (err) {
      console.error("Failed to create cash advance", err);
      throw err;
    }
  }

  async function approveRequest(id, comment) {
    try {
      const response = await apiFetch(`/api/serms/cash-advances/${id}/approve`, {
        method: "POST",
        credentials: "include",
        body: JSON.stringify({ comment }),
      });
      if (response.ok) {
        const result = await response.json();
        return upsertAdvance(result.data);
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to approve cash advance");
      }
    } catch (err) {
      console.error("Failed to approve cash advance", err);
      throw err;
    }
  }

  async function rejectRequest(id, comment) {
    try {
      const response = await apiFetch(`/api/serms/cash-advances/${id}/reject`, {
        method: "POST",
        credentials: "include",
        body: JSON.stringify({ comment }),
      });
      if (response.ok) {
        const result = await response.json();
        return upsertAdvance(result.data);
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to reject cash advance");
      }
    } catch (err) {
      console.error("Failed to reject cash advance", err);
      throw err;
    }
  }

  async function disburseRequest(id, payload) {
    try {
      const response = await apiFetch(`/api/serms/cash-advances/${id}/disburse`, {
        method: "POST",
        credentials: "include",
        body: JSON.stringify(payload),
      });
      if (response.ok) {
        const result = await response.json();
        return upsertAdvance(result.data);
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to disburse cash advance");
      }
    } catch (err) {
      console.error("Failed to disburse cash advance", err);
      throw err;
    }
  }

  async function acknowledgeRequest(id, signature) {
    try {
      const response = await apiFetch(
        `/api/serms/cash-advances/${id}/acknowledge`,
        {
          method: "POST",
          credentials: "include",
          body: JSON.stringify({ signature }),
        },
      );
      if (response.ok) {
        const result = await response.json();
        return upsertAdvance(result.data);
      } else {
        const errorData = await response.json();
        throw new Error(
          errorData.message || "Failed to acknowledge cash advance",
        );
      }
    } catch (err) {
      console.error("Failed to acknowledge cash advance", err);
      throw err;
    }
  }

  async function approveSettlement(id) {
    // Placeholder for liquidation approval
  }

  async function rejectSettlement(id) {
    // Placeholder for liquidation rejection
  }

  async function fetchRequest(id) {
    try {
      const response = await apiFetch(`/api/serms/cash-advances/${id}`, {
        credentials: "include",
      });
      if (response.ok) {
        return normalizeAdvance(await response.json());
      }
      return null;
    } catch (err) {
      console.error("Failed to fetch cash advance details", err);
      return null;
    }
  }

  async function fetchDocument(id) {
    try {
      const response = await apiFetch(`/api/serms/cash-advances/${id}/document`, {
        credentials: "include",
      });
      if (response.ok) {
        return await response.json();
      }
      return null;
    } catch (err) {
      console.error("Failed to fetch cash advance document", err);
      return null;
    }
  }

  /**
   * Update a pending/rejected cash advance request (employee self-edit).
   */
  async function updateRequest(id, formData) {
    try {
      if (formData instanceof FormData) {
        formData.append("_method", "PUT");
      }
      const response = await apiFetch(`/api/serms/cash-advances/${id}`, {
        method: "POST",
        credentials: "include",
        body: formData,
      });
      if (response.ok) {
        const result = await response.json();
        return upsertAdvance(result.data);
      } else {
        const errorData = await response.json();
        throw new Error(errorData.message || "Failed to update cash advance");
      }
    } catch (err) {
      console.error("Failed to update cash advance", err);
      throw err;
    }
  }

  /**
   * Delete a pending cash advance request.
   */
  async function deleteRequest(id, password) {
    try {
      const response = await apiFetch(`/api/serms/cash-advances/${id}`, {
        method: "DELETE",
        credentials: "include",
        body: JSON.stringify({ password }),
      });
      if (response.ok) {
        items.value = items.value.filter((i) => i.id != id);
        return true;
      } else {
        const errorData = await response.json();
        const errMsg = errorData.errors?.password?.[0] || errorData.message || "Failed to delete";
        throw new Error(errMsg);
      }
    } catch (err) {
      console.error("Failed to delete cash advance", err);
      throw err;
    }
  }

  return {
    items,
    isLoading,
    pendingCount,
    totalOutstanding,
    fetchAll,
    request,
    fetchRequest,
    fetchDocument,
    approveRequest,
    rejectRequest,
    disburseRequest,
    acknowledgeRequest,
    approveSettlement,
    rejectSettlement,
    updateRequest,
    deleteRequest,
  };
});
