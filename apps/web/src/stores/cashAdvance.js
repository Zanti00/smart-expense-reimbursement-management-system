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

  async function fetchAll() {
    isLoading.value = true;
    try {
      const response = await apiFetch("/api/serms/cash-advances", {
        credentials: "include",
      });
      if (response.ok) {
        const data = await response.json();
        items.value = data.map((item) => ({
          ...item,
          // Map backend fields to frontend expected fields
          userId: item.user_id,
          requestedBy: item.requester ? item.requester.name : "Unknown",
          dueDate: item.expected_liquidation_date,
          date: item.submitted_at || item.created_at,
          // outstanding_balance is now persisted in the DB and returned by the API.
          // Fall back to full amount for advances not yet disbursed (null balance).
          balance: item.outstanding_balance !== null && item.outstanding_balance !== undefined
            ? Number(item.outstanding_balance)
            : Number(item.amount ?? 0),
          adminNotes:
            item.approval_actions && item.approval_actions.length
              ? item.approval_actions[item.approval_actions.length - 1].comment
              : null,
          acknowledgedAt: item.acknowledged_at,
          signatureImage: item.signature,
          documentUrl: item.document ? item.document.file_url : null,
        }));
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
        await fetchAll();
        return result.data;
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
        await fetchAll();
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
        await fetchAll();
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
        await fetchAll();
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
        await fetchAll();
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
        return await response.json();
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
  };
});
