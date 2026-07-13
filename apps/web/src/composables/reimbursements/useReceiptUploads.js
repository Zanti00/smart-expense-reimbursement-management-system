import { ref, watch, onMounted, onBeforeUnmount } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import {
  buildPrefilledReceiptDraft,
} from "@/utils/receiptUtils";

export function useReceiptUploads() {
  const DRAFT_KEY = "serms_draft_receipts";
  const initialDrafts = sessionStorage.getItem(DRAFT_KEY);
  const localReceipts = ref(initialDrafts ? JSON.parse(initialDrafts) : []);

  watch(localReceipts, (newVal) => {
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify(newVal));
  }, { deep: true });

  function clearDraftReceipts() {
    sessionStorage.removeItem(DRAFT_KEY);
    localReceipts.value = [];
  }

  const receiptDrag = ref(false);
  const receiptInput = ref(null);
  
  const pollTimers = {};

  function startPolling(receiptId) {
    if (pollTimers[receiptId]) return;
    pollTimers[receiptId] = setInterval(async () => {
      try {
        const headers = { Accept: "application/json" };
        if (authStore.token) headers["Authorization"] = `Bearer ${authStore.token}`;
        
        const res = await fetch(`/api/serms/reimbursements/receipts/${receiptId}`, { headers });
        if (!res.ok) return;
        
        const { data } = await res.json();
        if (data.status !== 'processing') {
          clearInterval(pollTimers[receiptId]);
          delete pollTimers[receiptId];
          
          const index = localReceipts.value.findIndex((r) => String(r.id) === String(receiptId));
          if (index !== -1) {
            const oldThumbnail = localReceipts.value[index].thumbnail;
            const updatedReceipt = buildPrefilledReceiptDraft({
              id: data.id,
              receiptData: data,
              thumbnail: oldThumbnail,
            });
            updatedReceipt.isUploading = false;
            updatedReceipt.isProcessing = false;
            localReceipts.value[index] = updatedReceipt;
          }
        }
      } catch (e) {
        console.error("Polling error for receipt", receiptId, e);
      }
    }, 3000);
  }

  onBeforeUnmount(() => {
    Object.values(pollTimers).forEach(clearInterval);
  });

  onMounted(() => {
    localReceipts.value.forEach(r => {
      if (r.isProcessing) {
        startPolling(r.id);
      }
    });
  });
  
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
    const allowedMimeTypes = ["image/jpeg", "image/png", "application/pdf"];
    const files = Array.from(fileList || []);
    const accepted = files.filter((file) => allowedMimeTypes.includes(file.type));

    files
      .filter((file) => !allowedMimeTypes.includes(file.type))
      .forEach((file) => {
        addToast({
          message: `${file.name} has an invalid file type. Only JPEG, PNG, or PDF files are allowed.`,
          type: "error",
        });
      });

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
            isProcessing: data.data.status === 'processing',
          };
          if (data.data.status === 'processing') {
            startPolling(data.data.id);
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
    clearDraftReceipts,
  };
}
