import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useAuthStore } from "./auth";
import { useNotificationStore } from "./notification";
import { apiFetch } from "../utils/apiFetch";

export const useReceiptStore = defineStore("receipts", () => {
  const auth = useAuthStore();

  const receipts = ref([]);
  const categories = ref([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    pageSize: 10,
    total: 0,
    from: 0,
    to: 0,
  });

  // Filters State
  const filters = ref({
    dateRange: { start: "", end: "" },
    uploader: "",
    category: "",
    status: "",
    amountRange: { min: null, max: null },
  });

  // Hash Map for Duplicate Checks
  const existingHashes = computed(() => {
    const map = new Set();
    receipts.value.forEach((r) => {
      if (!r.isDeleted) map.add(r.hash);
    });
    return map;
  });

  // Getters
  const visibleReceipts = computed(() => {
    let filtered = receipts.value.filter((r) => !r.isDeleted);

    // Note: Role-based visibility is handled securely by the backend API.

    // Apply Active Filters
    if (filters.value.uploader) {
      filtered = filtered.filter((r) => r.uploader === filters.value.uploader);
    }
    if (filters.value.category) {
      filtered = filtered.filter((r) => r.category === filters.value.category);
    }
    if (filters.value.status) {
      filtered = filtered.filter((r) => r.status === filters.value.status);
    }
    if (
      filters.value.amountRange.min !== null &&
      filters.value.amountRange.min !== ""
    ) {
      filtered = filtered.filter(
        (r) => r.amount >= Number(filters.value.amountRange.min),
      );
    }
    if (
      filters.value.amountRange.max !== null &&
      filters.value.amountRange.max !== ""
    ) {
      filtered = filtered.filter(
        (r) => r.amount <= Number(filters.value.amountRange.max),
      );
    }

    // Simple Date Range filtering based on '04/15/2026' string
    if (filters.value.dateRange.start && filters.value.dateRange.end) {
      const start = new Date(filters.value.dateRange.start);
      const end = new Date(filters.value.dateRange.end);
      filtered = filtered.filter((r) => {
        const d = new Date(r.date);
        return d >= start && d <= end;
      });
    }

    // Sorting by newest
    return filtered.sort((a, b) => new Date(b.date) - new Date(a.date));
  });

  /**
   * Map a raw receipt API object to the frontend shape.
   */
  function getComplianceStatus(r) {
    const normalizedStatus = String(r.status || "").toLowerCase();
    const score = Number(r.ocr_confidence_score);
    const missingRequiredFields =
      !r.vendor_name ||
      !r.transaction_date ||
      !r.total_amount ||
      !r.invoice_number;

    if (
      [
        "processed",
        "pending",
        "approved",
        "rejected",
        "pending-admin-re-review",
        "automatic-rejected",
        "processing",
        "final-rejected",
      ].includes(normalizedStatus)
    ) {
      return normalizedStatus;
    }
    if (r.ocr_flagged || missingRequiredFields || (score > 0 && score < 0.75)) {
      return "automatic-rejected";
    }

    return r.status || "Processed";
  }

  function mapReceipt(r) {
    const complianceStatus = getComplianceStatus(r);
    const reimbursementCount = Number(
      r.reimbursements_count ?? r.reimbursements?.length ?? 0,
    );

    return {
      id: `RCPT-2026-${String(r.id).padStart(3, "0")}`,
      dbId: r.id,
      uploader: r.uploader?.name || auth.user?.name || "Unknown",
      fileName: (r.file_path || "").split("/").pop() || "N/A",
      fileType: r.file_type,
      fileSize: r.file_size_bytes,
      date: r.transaction_date || r.created_at,
      amount: Number(r.total_amount) || 0,
      category: r.category?.name || "Uncategorized",
      categoryId: r.expense_category_id || null,
      status: complianceStatus,
      complianceStatus,
      complianceReason:
        complianceStatus === "automatic-rejected"
          ? "System validation failed. Required data may be missing or OCR confidence was too low."
          : r.compliance_reason || "",
      systemRejectedAt:
        complianceStatus === "automatic-rejected"
          ? r.updated_at || r.created_at || new Date().toISOString()
          : r.system_rejected_at || null,
      modifiedAfterSystemRejection: !!r.modified_after_system_rejection,
      adminNotes: r.admin_notes || "",
      hash: r.file_hash,
      thumbnail: r.file_url || (r.file_path ? `https://vbabvrcfqcmvvjwmzuwx.supabase.co/storage/v1/object/public/cash_advances/${r.file_path}` : null),
      isDeleted: !!r.deleted_at,
      reimbursementCount,
      isReimbursed: reimbursementCount > 0,
      vendorName: r.vendor_name,
      vatAmount: Number(r.vat_amount) || 0,
      tin: r.tin,
      invoiceNumber: r.invoice_number,
      vatClassification: r.vat_classification,
      ocrConfidenceScore: r.ocr_confidence_score,
      ocrFlagged: r.ocr_flagged,
      createdAt: r.created_at,
      items: r.items || [],
    };
  }

  /**
   * Fetch expense categories from the API.
   */
  async function fetchCategories() {
    try {
      const response = await apiFetch("/api/serms/reimbursements/categories", {
        credentials: "include",
      });
      if (response.ok) {
        const data = await response.json();
        const parsed = data.data || data;
        categories.value = Array.isArray(parsed) ? parsed : [];
        if (categories.value.length === 0) {
          useNotificationStore().error("API returned empty categories array.");
        }
      } else {
        useNotificationStore().error(
          `Categories API returned ${response.status}: ${response.statusText}`,
        );
      }
    } catch (e) {
      console.error("Failed to fetch expense categories:", e);
      // Force an alert so we can see the hidden error!
      const notif = useNotificationStore();
      if (notif) notif.error(`Categories fetch failed: ${e.message}`);
    }
  }

  /**
   * Fetch all receipts from the API.
   */
  async function fetchAll(params = {}) {
    isLoading.value = true;
    try {
      const query = new URLSearchParams();
      query.set("page", String(params.page || 1));
      query.set("per_page", String(params.perPage || pagination.value.pageSize || 10));
      if (params.search) query.set("search", params.search);
      if (params.status && params.status !== "All") {
        query.set("status", String(params.status).toLowerCase());
      }
      if (params.category && params.category !== "All") {
        query.set("category", params.category);
      }

      const response = await apiFetch(`/api/serms/reimbursements/receipts?${query.toString()}`, {
        credentials: "include",
      });
      if (response.ok) {
        const data = await response.json();
        const fetchedItems = (data.data || []).map(mapReceipt);
        receipts.value = fetchedItems;
        const meta = data.meta || {};
        pagination.value = {
          currentPage: Number(meta.current_page) || 1,
          lastPage: Number(meta.last_page) || 1,
          pageSize: Number(meta.per_page) || 10,
          total: Number(meta.total) || fetchedItems.length,
          from: Number(meta.from) || (fetchedItems.length ? 1 : 0),
          to: Number(meta.to) || fetchedItems.length,
        };
      }
    } catch (e) {
      console.error("Failed to fetch receipts from database:", e);
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Upload and store a receipt to the backend.
   * Sends the file as multipart/form-data alongside receipt metadata.
   *
   * @param {File} file - The file blob to upload
   * @param {object} metadata - Receipt metadata fields
   * @returns {Promise<object>} The created receipt
   */
  async function uploadReceipt(file, metadata) {
    isSaving.value = true;

    const formData = new FormData();
    if (file) {
      formData.append("file", file);
    }
    formData.append("expense_category_id", metadata.expense_category_id);
    if (metadata.vendor_name)
      formData.append("vendor_name", metadata.vendor_name);
    if (metadata.transaction_date)
      formData.append("transaction_date", metadata.transaction_date);
    if (metadata.total_amount != null)
      formData.append("total_amount", metadata.total_amount);
    if (metadata.vat_amount != null)
      formData.append("vat_amount", metadata.vat_amount);
    if (metadata.tin) formData.append("tin", metadata.tin);
    if (metadata.invoice_number)
      formData.append("invoice_number", metadata.invoice_number);
    if (metadata.vat_classification)
      formData.append("vat_classification", metadata.vat_classification);
    if (metadata.items && Array.isArray(metadata.items)) {
      formData.append("items", JSON.stringify(metadata.items));
    }

    try {
      const response = await apiFetch("/api/serms/reimbursements/receipts", {
        method: "POST",
        credentials: "include",
        body: formData,
      });

      if (!response.ok) {
        const errData = await response.json().catch(() => ({}));
        throw new Error(
          errData.message ||
            `Failed to save receipt (Status ${response.status})`,
        );
      }

      const resData = await response.json();
      const savedReceipt = mapReceipt(resData.data);
      receipts.value.unshift(savedReceipt);
      return savedReceipt;
    } catch (error) {
      console.error("Receipt upload failed:", error);
      throw error;
    } finally {
      isSaving.value = false;
    }
  }

  async function resubmitReceipt(id, metadata, file = null) {
    isSaving.value = true;
    const rx = receipts.value.find((r) => r.id === id);
    if (!rx) {
      isSaving.value = false;
      throw new Error("Receipt not found.");
    }

    if (String(rx.status || "").toLowerCase() !== "processed") {
      isSaving.value = false;
      throw new Error("Only receipts with processed status can be edited.");
    }

    const previousStatus = rx.status;
    const payload = {
      ...metadata,
      status: "processed",
      complianceStatus: "processed",
      modifiedAfterSystemRejection: false,
      previousStatus,
      resubmittedAt: new Date().toISOString(),
    };

    try {


      const formData = new FormData();
      if (file) formData.append("file", file);
      Object.entries(metadata).forEach(([key, value]) => {
        if (value == null) return;
        formData.append(key, Array.isArray(value) ? JSON.stringify(value) : value);
      });
      formData.append("status", "processed");

      if (rx.dbId) {
        const response = await apiFetch(`/api/serms/reimbursements/receipts/${rx.dbId}/resubmit`, {
          method: "POST",
          credentials: "include",
          body: formData,
        });

        if (!response.ok) {
          const errData = await response.json().catch(() => ({}));
          throw new Error(
            errData.message ||
              `Failed to resubmit receipt (Status ${response.status})`,
          );
        }

        const resData = await response.json();
        Object.assign(rx, mapReceipt(resData.data));
        return rx;
      }

      Object.assign(rx, {
        vendorName: metadata.vendor_name || rx.vendorName,
        date: metadata.transaction_date || rx.date,
        amount: Number(metadata.total_amount) || rx.amount,
        vatAmount: Number(metadata.vat_amount) || 0,
        tin: metadata.tin || rx.tin,
        invoiceNumber: metadata.invoice_number || rx.invoiceNumber,
        vatClassification: metadata.vat_classification || rx.vatClassification,
        categoryId: metadata.expense_category_id || rx.categoryId,
        items: metadata.items || rx.items,
        ...payload,
      });

      return rx;
    } finally {
      isSaving.value = false;
    }
  }

  async function finalizeReReview(id, decision, adminNotes) {
    const rx = receipts.value.find((r) => r.id === id);
    if (!rx) throw new Error("Receipt not found.");
    if (!adminNotes || adminNotes.trim().length < 10) {
      throw new Error("Admin notes must be at least 10 characters.");
    }

    const finalStatus = decision === "approve" ? "Processed" : "final-rejected";
    Object.assign(rx, {
      status: finalStatus,
      complianceStatus: finalStatus,
      adminNotes,
      finalDecision: decision,
      finalDecisionAt: new Date().toISOString(),
    });
    return rx;
  }

  // Legacy: keep simulateUpload for backward compatibility
  async function simulateUpload(fileMeta, mockHash) {
    return new Promise((resolve, reject) => {
      // Duplicate check (Client-side)
      if (existingHashes.value.has(mockHash)) {
        reject(
          new Error(
            "This file has already been uploaded (Duplicate detected).",
          ),
        );
        return;
      }

      if (fileMeta.size > 2 * 1024 * 1024) {
        reject(new Error("File size exceeds 2MB."));
        return;
      }

      // Optimistic Upload creation
      const newReceipt = {
        id: `RCPT-2026-00${receipts.value.length + 1}`,
        uploader: auth.user?.name || "Unknown",
        fileName: fileMeta.name,
        fileType: fileMeta.type,
        fileSize: fileMeta.size,
        date: new Date().toLocaleDateString("en-US", {
          month: "2-digit",
          day: "2-digit",
          year: "numeric",
        }),
        amount: 0, // Requires manual entry after processing
        category: "Uncategorized",
        status: "Processing",
        hash: mockHash,
        thumbnail: null,
        isDeleted: false,
      };

      receipts.value.unshift(newReceipt);

      // Simulate Hash verification and Server Processing (optimistic UI)
      setTimeout(() => {
        const index = receipts.value.findIndex((r) => r.id === newReceipt.id);
        if (index !== -1) {
          receipts.value[index].status = "Pending"; // Finish processing
        }
        resolve(newReceipt);
      }, 2500);
    });
  }

  async function softDelete(id) {
    const rx = receipts.value.find((r) => r.id === id);
    if (!rx) return;

    try {
      const response = await apiFetch(
        `/api/serms/reimbursements/receipts/${rx.dbId}`,
        {
          method: "DELETE",
          credentials: "include",
        },
      );

      if (!response.ok) {
        const errData = await response.json().catch(() => ({}));
        throw new Error(
          errData.message ||
            `Failed to delete receipt (Status ${response.status})`,
        );
      }

      rx.isDeleted = true;
      const notif = useNotificationStore();
      if (notif) notif.success("Receipt deleted successfully.");
    } catch (error) {
      console.error("Failed to delete receipt:", error);
      const notif = useNotificationStore();
      if (notif) notif.error(error.message);
      throw error;
    }
  }

  function hardDelete(id) {
    receipts.value = receipts.value.filter((r) => r.id !== id);
  }

  function clearFilters() {
    filters.value = {
      dateRange: { start: "", end: "" },
      uploader: "",
      category: "",
      status: "",
      amountRange: { min: null, max: null },
    };
  }

  return {
    receipts,
    categories,
    isLoading,
    isSaving,
    filters,
    existingHashes,
    pagination,
    visibleReceipts,
    fetchAll,
    fetchCategories,
    uploadReceipt,
    resubmitReceipt,
    finalizeReReview,
    simulateUpload,
    softDelete,
    hardDelete,
    clearFilters,
  };
});
