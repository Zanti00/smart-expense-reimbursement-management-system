import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import { tinFor, cleanName, subtotalOf, vatOf, getItems } from "@/utils/receiptUtils";

const CATEGORIES = [
  "Food & Dining",
  "Transportation",
  "Lodging",
  "Supplies",
  "Entertainment",
  "Utilities",
  "Other",
];

export function useReceiptUploads() {
  const localReceipts = ref([]);
  const receiptDrag = ref(false);
  const receiptInput = ref(null);
  
  const authStore = useAuthStore();
  const { addToast } = useToast();

  function handleReceiptDrop(e) {
    receiptDrag.value = false;
    addReceiptFiles(e.dataTransfer.files);
  }

  function handleReceiptSelect(e) {
    addReceiptFiles(e.target.files);
    if (e.target) e.target.value = "";
  }

  async function addReceiptFiles(fileList) {
    const accepted = Array.from(fileList || []).filter((file) =>
      ["image/jpeg", "image/png", "application/pdf"].includes(file.type),
    );

    for (const file of accepted) {
      if (file.size > 2 * 1024 * 1024) {
        addToast({ message: `${file.name} exceeds 2MB limit`, type: "error" });
        continue;
      }

      const tempId = `temp-${Date.now()}`;
      const receiptObj = {
        id: tempId,
        invoiceNumber: tempId,
        tin: tinFor({ id: tempId }),
        merchantName: cleanName(file.name),
        location: "Metro Manila, Philippines",
        fileName: file.name,
        fileType: file.type,
        thumbnail: file.type.startsWith("image/")
          ? URL.createObjectURL(file)
          : "",
        amount: 0,
        subtotal: 0,
        tax: 0,
        vatClassification: "vat",
        date: new Date().toISOString().slice(0, 10),
        category: "Food & Dining",
        items: getItems("Food & Dining").map((name) => ({
          name,
          qty: 1,
          price: 0,
        })),
        isUploading: true,
        sourceFile: file,
      };
      localReceipts.value.push(receiptObj);

      try {
        const formData = new FormData();
        formData.append("file", file);
        formData.append("expense_category_id", 1);

        const headers = { Accept: "application/json" };
        if (authStore.token)
          headers["Authorization"] = `Bearer ${authStore.token}`;

        const res = await fetch("/api/serms/reimbursements/receipts", {
          method: "POST",
          headers,
          body: formData,
        });
        if (!res.ok) throw new Error("Upload failed");
        const data = await res.json();

        const index = localReceipts.value.findIndex((r) => r.id === tempId);
        if (index !== -1) {
          const amount = data.data.total_amount || 0;
          const subtotal = subtotalOf(amount).toFixed(2);
          const tax = vatOf(amount).toFixed(2);

          localReceipts.value[index] = {
            ...localReceipts.value[index],
            id: data.data.id,
            invoiceNumber: data.data.id,
            amount: amount,
            subtotal: subtotal,
            tax: tax,
            vatClassification: data.data.vat_classification || "vat",
            date: data.data.transaction_date || receiptObj.date,
            isUploading: false,
          };
          // Recalculate item prices based on total amount
          const currentItems = localReceipts.value[index].items;
          if (currentItems.length > 0) {
            const splitPrice = (amount / currentItems.length).toFixed(2);
            currentItems.forEach((item) => {
              item.price = splitPrice;
            });
          }
        }
      } catch (e) {
        console.error(e);
        addToast({ message: `Failed to upload ${file.name}`, type: "error" });
        localReceipts.value = localReceipts.value.filter((r) => r.id !== tempId);
      }
    }
  }

  function removeReceipt(receipt) {
    if (receipt.thumbnail?.startsWith("blob:")) {
      URL.revokeObjectURL(receipt.thumbnail);
    }
    localReceipts.value = localReceipts.value.filter((r) => r.id !== receipt.id);
  }

  return {
    localReceipts,
    receiptDrag,
    receiptInput,
    handleReceiptDrop,
    handleReceiptSelect,
    removeReceipt,
  };
}
