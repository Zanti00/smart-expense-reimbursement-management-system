import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import {
  buildPrefilledReceiptDraft,
} from "@/utils/receiptUtils";

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
      const receiptObj = buildPrefilledReceiptDraft({
        id: tempId,
        file,
        thumbnail: file.type.startsWith("image/")
          ? URL.createObjectURL(file)
          : "",
      });
      receiptObj.isUploading = true;
      localReceipts.value.push(receiptObj);

      try {
        const formData = new FormData();
        formData.append("file", file);

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
          localReceipts.value[index] = {
            ...buildPrefilledReceiptDraft({
              id: data.data.id,
              file,
              receiptData: data.data,
              thumbnail: localReceipts.value[index].thumbnail,
            }),
            isUploading: false,
          };
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
