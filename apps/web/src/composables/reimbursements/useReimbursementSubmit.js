import { ref, computed } from "vue";
import { useToast } from "@/composables/useToast";
import { useReimbursementStore } from "@/stores/reimbursement";
import { cleanName } from "@/utils/receiptUtils";

export function useReimbursementSubmit(emit, router) {
  const store = useReimbursementStore();
  const { addToast } = useToast();
  
  const submitting = ref(false);

  async function submitReimbursement({ receipts, cutoffPeriod, reportFile, totalAmount }) {
    if (!receipts.length || !cutoffPeriod || !reportFile || receipts.some(r => r.isUploading)) {
      return false;
    }
    if (receipts.some((r) => !r.categoryId)) {
      addToast({
        message: "Category is required for every receipt.",
        type: "error",
      });
      return false;
    }

    for (let idx = 0; idx < receipts.length; idx++) {
      const r = receipts[idx];
      if (r.items && Array.isArray(r.items)) {
        for (const item of r.items) {
          if (!item.name || !item.name.trim()) {
            addToast({
              message: `Item name is required in receipt ${idx + 1}`,
              type: "error",
            });
            return false;
          }
          if (Number(item.qty) <= 0) {
            addToast({
              message: `Quantity for "${item.name}" must be greater than 0`,
              type: "error",
            });
            return false;
          }
          if (Number(item.price) <= 0) {
            addToast({
              message: `Price for "${item.name}" must be greater than 0`,
              type: "error",
            });
            return false;
          }
        }
      }
    }

    submitting.value = true;
    try {
      const formData = new FormData();
      formData.append(
        "description",
        receipts
          .map((r) => r.merchantName || cleanName(r.fileName))
          .join(", "),
      );
      formData.append("expense_category_id", receipts[0].categoryId);
      formData.append("amount", totalAmount);
      formData.append(
        "date",
        receipts[0]?.date || new Date().toISOString().slice(0, 10),
      );
      formData.append("cutoff_period", cutoffPeriod);

      if (reportFile) {
        formData.append("report_file", reportFile);
      }

      receipts.forEach((r, index) => {
        formData.append(`receipt_ids[${index}]`, r.id);
        formData.append(`receipts[${index}][id]`, r.id);
        if (r.categoryId) {
          formData.append(`receipts[${index}][expense_category_id]`, r.categoryId);
        }
        formData.append(`receipts[${index}][vendor_name]`, r.merchantName || "");
        formData.append(`receipts[${index}][transaction_date]`, r.date || "");
        formData.append(`receipts[${index}][total_amount]`, r.amount || 0);
        formData.append(`receipts[${index}][vat_amount]`, r.tax || 0);
        formData.append(
          `receipts[${index}][vat_classification]`,
          r.vatClassification || "vat",
        );
        if (r.currency) {
          formData.append(`receipts[${index}][currency]`, r.currency);
        }
        formData.append(`receipts[${index}][tin]`, r.tin || "");
        formData.append(
          `receipts[${index}][invoice_number]`,
          r.invoiceNumber || "",
        );

        if (r.items && Array.isArray(r.items)) {
          r.items.forEach((item, itemIdx) => {
            formData.append(
              `receipts[${index}][items][${itemIdx}][name]`,
              item.name || "",
            );
            formData.append(
              `receipts[${index}][items][${itemIdx}][quantity]`,
              item.qty || 1,
            );
            formData.append(
              `receipts[${index}][items][${itemIdx}][price]`,
              item.price || 0,
            );
          });
        }
      });

      await store.submit(formData);

      addToast({
        message: "Reimbursement submitted successfully!",
        type: "success",
      });
      emit("submitted");
      
      emit("close");
      router.push({ name: "Reimbursements" });
      
      return true;
    } finally {
      submitting.value = false;
    }
  }

  async function updateReimbursement(id, { receipts, cutoffPeriod, reportFile, totalAmount }) {
    if (!receipts.length || !cutoffPeriod || receipts.some(r => r.isUploading)) {
      return false;
    }

    for (let idx = 0; idx < receipts.length; idx++) {
      const r = receipts[idx];
      if (r.items && Array.isArray(r.items)) {
        for (const item of r.items) {
          if (!item.name || !item.name.trim()) {
            addToast({
              message: `Item name is required in receipt ${idx + 1}`,
              type: "error",
            });
            return false;
          }
          if (Number(item.qty) <= 0) {
            addToast({
              message: `Quantity for "${item.name}" must be greater than 0`,
              type: "error",
            });
            return false;
          }
          if (Number(item.price) <= 0) {
            addToast({
              message: `Price for "${item.name}" must be greater than 0`,
              type: "error",
            });
            return false;
          }
        }
      }
    }

    submitting.value = true;
    try {
      const formData = new FormData();
      formData.append(
        "description",
        receipts
          .map((r) => r.merchantName || cleanName(r.fileName))
          .join(", "),
      );
      if (receipts[0].categoryId) {
        formData.append("expense_category_id", receipts[0].categoryId);
      }
      formData.append("amount", totalAmount);
      formData.append(
        "date",
        receipts[0]?.date || new Date().toISOString().slice(0, 10),
      );
      formData.append("cutoff_period", cutoffPeriod);

      if (reportFile && reportFile instanceof File) {
        formData.append("report_file", reportFile);
      }

      receipts.forEach((r, index) => {
        formData.append(`receipt_ids[${index}]`, r.id);
        formData.append(`receipts[${index}][id]`, r.id);
        if (r.categoryId) {
          formData.append(`receipts[${index}][expense_category_id]`, r.categoryId);
        }
        formData.append(`receipts[${index}][vendor_name]`, r.merchantName || "");
        formData.append(`receipts[${index}][transaction_date]`, r.date || "");
        formData.append(`receipts[${index}][total_amount]`, r.amount || 0);
        formData.append(`receipts[${index}][vat_amount]`, r.tax || 0);
        formData.append(
          `receipts[${index}][vat_classification]`,
          r.vatClassification || "vat",
        );
        if (r.currency) {
          formData.append(`receipts[${index}][currency]`, r.currency);
        }
        formData.append(`receipts[${index}][tin]`, r.tin || "");
        formData.append(
          `receipts[${index}][invoice_number]`,
          r.invoiceNumber || "",
        );

        if (r.items && Array.isArray(r.items)) {
          r.items.forEach((item, itemIdx) => {
            formData.append(
              `receipts[${index}][items][${itemIdx}][name]`,
              item.name || "",
            );
            formData.append(
              `receipts[${index}][items][${itemIdx}][quantity]`,
              item.qty || 1,
            );
            formData.append(
              `receipts[${index}][items][${itemIdx}][price]`,
              item.price || 0,
            );
          });
        }
      });

      await store.updateRequest(id, formData);

      addToast({
        message: "Reimbursement updated successfully!",
        type: "success",
      });
      emit("submitted");
      
      emit("close");
      router.push({ name: "Reimbursements" });
      
      return true;
    } finally {
      submitting.value = false;
    }
  }

  return {
    submitting,
    submitReimbursement,
    updateReimbursement,
  };
}
