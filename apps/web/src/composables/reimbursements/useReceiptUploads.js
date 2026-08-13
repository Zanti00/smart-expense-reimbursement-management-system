import { ref, watch, onMounted, onBeforeUnmount } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "@/composables/useToast";
import { buildPrefilledReceiptDraft } from "@/utils/receiptUtils";

export function useReceiptUploads(options = {}) {
  const DRAFT_KEY = options.draftKey || "serms_draft_receipts";
  const initialDrafts = sessionStorage.getItem(DRAFT_KEY);
  const localReceipts = ref(initialDrafts ? JSON.parse(initialDrafts) : []);

  const qualityRejection = ref(null);
  const showSegmentedUpload = ref(false);

  watch(
    localReceipts,
    (newVal) => {
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify(newVal));
    },
    { deep: true },
  );

  function clearDraftReceipts() {
    sessionStorage.removeItem(DRAFT_KEY);
    localReceipts.value = [];
  }

  function clearQualityRejection() {
    qualityRejection.value = null;
  }

  const receiptDrag = ref(false);
  const receiptInput = ref(null);

  const pollTimers = {};

  function checkIsDuplicateResponse(data) {
    if (!data) return false;
    if (
      data.rejection_code === "duplicate" ||
      data.rejectionCode === "duplicate" ||
      data.is_duplicate ||
      data.isDuplicate
    ) {
      return true;
    }
    const reason = (
      data.rejection_reason ||
      data.rejectionReason ||
      data.message ||
      data.error ||
      ""
    ).toLowerCase();
    if (reason.includes("duplicate")) return true;
    if (data.errors) {
      const errStr = JSON.stringify(data.errors).toLowerCase();
      if (errStr.includes("duplicate")) return true;
    }
    return false;
  }

  function startPolling(receiptId) {
    if (pollTimers[receiptId]) return;
    pollTimers[receiptId] = setInterval(async () => {
      try {
        const headers = { Accept: "application/json" };
        if (authStore.token)
          headers["Authorization"] = `Bearer ${authStore.token}`;

        const res = await fetch(
          `/api/serms/reimbursements/receipts/${receiptId}`,
          { headers },
        );
        if (!res.ok) return;

        const { data } = await res.json();
        if (data.status !== "processing") {
          clearInterval(pollTimers[receiptId]);
          delete pollTimers[receiptId];

          const index = localReceipts.value.findIndex(
            (r) => String(r.id) === String(receiptId),
          );

          if (data.status === "rejected" || data.status === "failed") {
            const rejectedItem = index !== -1 ? localReceipts.value[index] : null;
            const fileObj = rejectedItem?.sourceFile || rejectedItem?.file || null;
            
            if (checkIsDuplicateResponse(data)) {
                if (index !== -1) {
                  localReceipts.value[index].isUploading = false;
                  localReceipts.value[index].isProcessing = false;
                  localReceipts.value[index].isRejected = true;
                  localReceipts.value[index].rejectionCode = "duplicate";
                }
                window.dispatchEvent(new CustomEvent('receipt-duplicate-detected', { 
                  detail: { 
                    similarityScore: data.duplicate_similarity || 1.0,
                    receiptId: data.id,
                    message: data.rejection_reason || data.error || "Duplicate receipt detected."
                  } 
                }));
                return;
            }

            if (index !== -1) {
              localReceipts.value[index] = {
                ...localReceipts.value[index],
                isUploading: false,
                isProcessing: false,
                isRejected: true,
                rejectionCode: data.rejection_code || "blurry",
                rejectionReason:
                  data.rejection_reason ||
                  data.error ||
                  "Receipt image quality is too low for accurate OCR data extraction.",
              };
            }
            qualityRejection.value = {
              receiptId: data.id,
              file: fileObj,
              rejectionCode: data.rejection_code || "blurry",
              rejectionReason:
                data.rejection_reason ||
                data.error ||
                "Receipt image quality is too low for accurate OCR data extraction.",
            };
            addToast({
              message: `Receipt rejected: ${data.rejection_reason || data.error || "Image quality issue"}`,
              type: "error",
            });
            return;
          }

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

  onMounted(() => {
    localReceipts.value.forEach((r) => {
      if (r.isProcessing) {
        startPolling(r.id);
      }
    });
    window.addEventListener('remove-duplicate-receipt', handleRemoveDuplicateEvent);
  });

  onBeforeUnmount(() => {
    Object.values(pollTimers).forEach(clearInterval);
    window.removeEventListener('remove-duplicate-receipt', handleRemoveDuplicateEvent);
  });

  function handleRemoveDuplicateEvent(e) {
    if (e.detail?.receiptId) {
      removeReceipt({ id: e.detail.receiptId });
    }
  }

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

  async function addReceiptFiles(fileList, forceProcess = false) {
    const allowedMimeTypes = ["image/jpeg", "image/png", "application/pdf"];
    const files = Array.from(fileList || []);
    const accepted = files.filter((file) =>
      allowedMimeTypes.includes(file.type),
    );

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
        if (forceProcess) {
          formData.append("force_process", "1");
        }

        const headers = { Accept: "application/json" };
        if (authStore.token)
          headers["Authorization"] = `Bearer ${authStore.token}`;

        const res = await fetch("/api/serms/reimbursements/receipts", {
          method: "POST",
          headers,
          body: formData,
        });

        if (res.status === 422) {
          const errorData = await res.json();
          const index = localReceipts.value.findIndex((r) => r.id === tempId);
          if (index !== -1) {
            localReceipts.value[index].isUploading = false;
            localReceipts.value[index].isProcessing = false;
            localReceipts.value[index].isRejected = true;
          }
          
          if (checkIsDuplicateResponse(errorData)) {
             if (index !== -1) {
               localReceipts.value[index].rejectionCode = "duplicate";
             }
             const msg = errorData.rejection_reason || errorData.message || (errorData.errors?.file_hash ? errorData.errors.file_hash[0] : null) || "Duplicate receipt detected.";
             window.dispatchEvent(new CustomEvent('receipt-duplicate-detected', { 
               detail: { 
                 similarityScore: errorData.duplicate_similarity || 1.0,
                 receiptId: tempId,
                 message: msg
               } 
             }));
             return;
          }
          qualityRejection.value = {
            receiptId: tempId,
            file,
            rejectionCode: errorData.rejection_code || "blurry",
            rejectionReason:
              errorData.rejection_reason ||
              "Receipt image quality is too low for OCR processing.",
          };
          addToast({
            message: `Receipt rejected: ${errorData.rejection_reason || "Image quality issue"}`,
            type: "error",
          });
          return;
        }

        if (!res.ok) throw new Error("Upload failed");
        const data = await res.json();

        const index = localReceipts.value.findIndex((r) => r.id === tempId);

        if (data.data?.status === "rejected" || data.data?.status === "failed") {
          if (checkIsDuplicateResponse(data.data)) {
             if (index !== -1) {
               localReceipts.value[index].isUploading = false;
               localReceipts.value[index].isProcessing = false;
               localReceipts.value[index].isRejected = true;
               localReceipts.value[index].rejectionCode = "duplicate";
             }
             window.dispatchEvent(new CustomEvent('receipt-duplicate-detected', { 
               detail: { 
                 similarityScore: data.data.duplicate_similarity || 1.0,
                 receiptId: data.data.id || tempId,
                 message: data.data.rejection_reason || data.data.error || "Duplicate receipt detected."
               } 
             }));
             return;
          }
          if (index !== -1) {
            localReceipts.value[index] = {
              ...buildPrefilledReceiptDraft({
                id: data.data.id,
                file,
                receiptData: data.data,
                thumbnail: localReceipts.value[index].thumbnail,
              }),
              isUploading: false,
              isProcessing: false,
              isRejected: true,
              rejectionCode: data.data.rejection_code || "blurry",
              rejectionReason:
                data.data.rejection_reason ||
                "Receipt image quality is too low for accurate OCR data extraction.",
            };
          }
          qualityRejection.value = {
            receiptId: data.data.id,
            file,
            rejectionCode: data.data.rejection_code || "blurry",
            rejectionReason:
              data.data.rejection_reason ||
              "Receipt image quality is too low for accurate OCR data extraction.",
          };
          addToast({
            message: `Receipt rejected: ${data.data.rejection_reason || "Image quality issue"}`,
            type: "error",
          });
          return;
        }

        if (index !== -1) {
          localReceipts.value[index] = {
            ...buildPrefilledReceiptDraft({
              id: data.data.id,
              file,
              receiptData: data.data,
              thumbnail: localReceipts.value[index].thumbnail,
            }),
            isUploading: false,
            isProcessing: data.data.status === "processing",
          };
          if (data.data.status === "processing") {
            startPolling(data.data.id);
          }
        }
      } catch (e) {
        console.error(e);
        addToast({ message: `Failed to upload ${file.name}`, type: "error" });
        localReceipts.value = localReceipts.value.filter(
          (r) => r.id !== tempId,
        );
      }
    }
  }

  function continueAnyway() {
    clearQualityRejection();
    addToast({
      message: "Quality override applied. You can now fill in the receipt details manually.",
      type: "info",
    });
  }

  const submitWithForce = continueAnyway;

  async function submitSegments(files) {
    if (!files || files.length < 2) return;

    const tempId = `temp-seg-${Date.now()}`;
    const firstFile = files[0];
    const receiptObj = buildPrefilledReceiptDraft({
      id: tempId,
      file: firstFile,
      thumbnail: URL.createObjectURL(firstFile),
    });
    receiptObj.isUploading = true;
    localReceipts.value.push(receiptObj);

    try {
      const formData = new FormData();
      files.forEach((file) => {
        formData.append("files[]", file);
      });

      const headers = { Accept: "application/json" };
      if (authStore.token)
        headers["Authorization"] = `Bearer ${authStore.token}`;

      const res = await fetch("/api/serms/reimbursements/receipts/segmented", {
        method: "POST",
        headers,
        body: formData,
      });

      if (res.status === 422) {
        const errorData = await res.json();
        const index = localReceipts.value.findIndex((r) => r.id === tempId);
        if (index !== -1) {
          localReceipts.value[index].isUploading = false;
          localReceipts.value[index].isProcessing = false;
          localReceipts.value[index].isRejected = true;
        }
        
        if (checkIsDuplicateResponse(errorData)) {
           if (index !== -1) {
             localReceipts.value[index].rejectionCode = "duplicate";
           }
           const msg = errorData.rejection_reason || errorData.message || (errorData.errors?.file_hash ? errorData.errors.file_hash[0] : null) || "Duplicate receipt detected.";
           window.dispatchEvent(new CustomEvent('receipt-duplicate-detected', { 
             detail: { 
               similarityScore: errorData.duplicate_similarity || 1.0,
               receiptId: tempId,
               message: msg
             } 
           }));
           return;
        }
        qualityRejection.value = {
          receiptId: tempId,
          file: firstFile,
          rejectionCode: errorData.rejection_code || "blurry",
          rejectionReason:
            errorData.rejection_reason ||
            "One or more segments are too low quality.",
        };
        addToast({
          message: `Segmented receipt rejected: ${errorData.rejection_reason || "Quality issue"}`,
          type: "error",
        });
        return;
      }

      if (!res.ok) throw new Error("Segmented upload failed");
      const data = await res.json();

      const index = localReceipts.value.findIndex((r) => r.id === tempId);

      if (data.data?.status === "rejected" || data.data?.status === "failed") {
        if (checkIsDuplicateResponse(data.data)) {
           if (index !== -1) {
             localReceipts.value[index].isUploading = false;
             localReceipts.value[index].isProcessing = false;
             localReceipts.value[index].isRejected = true;
             localReceipts.value[index].rejectionCode = "duplicate";
           }
           window.dispatchEvent(new CustomEvent('receipt-duplicate-detected', { 
             detail: { 
               similarityScore: data.data.duplicate_similarity || 1.0,
               receiptId: data.data.id || tempId,
               message: data.data.rejection_reason || data.data.error || "Duplicate receipt detected."
             } 
           }));
           return;
        }
        if (index !== -1) {
          localReceipts.value[index] = {
            ...buildPrefilledReceiptDraft({
              id: data.data.id,
              file: firstFile,
              receiptData: data.data,
              thumbnail: localReceipts.value[index].thumbnail,
            }),
            isUploading: false,
            isProcessing: false,
            isRejected: true,
            rejectionCode: data.data.rejection_code || "blurry",
            rejectionReason:
              data.data.rejection_reason ||
              "One or more segments are too low quality for accurate OCR data extraction.",
          };
        }
        qualityRejection.value = {
          receiptId: data.data.id,
          file: firstFile,
          rejectionCode: data.data.rejection_code || "blurry",
          rejectionReason:
            data.data.rejection_reason ||
            "One or more segments are too low quality for accurate OCR data extraction.",
        };
        addToast({
          message: `Segmented receipt rejected: ${data.data.rejection_reason || "Quality issue"}`,
          type: "error",
        });
        return;
      }

      if (index !== -1) {
        localReceipts.value[index] = {
          ...buildPrefilledReceiptDraft({
            id: data.data.id,
            file: firstFile,
            receiptData: data.data,
            thumbnail: localReceipts.value[index].thumbnail,
          }),
          isUploading: false,
          isProcessing: data.data.status === "processing",
        };
        if (data.data.status === "processing") {
          startPolling(data.data.id);
        }
      }
    } catch (e) {
      console.error(e);
      addToast({
        message: "Failed to upload segmented receipt",
        type: "error",
      });
      localReceipts.value = localReceipts.value.filter((r) => r.id !== tempId);
    }
  }

  function removeReceipt(receipt) {
    if (receipt.thumbnail?.startsWith("blob:")) {
      URL.revokeObjectURL(receipt.thumbnail);
    }
    localReceipts.value = localReceipts.value.filter(
      (r) => r.id !== receipt.id,
    );
  }

  return {
    localReceipts,
    receiptDrag,
    receiptInput,
    handleReceiptDrop,
    handleReceiptSelect,
    addReceiptFiles,
    removeReceipt,
    clearDraftReceipts,
    qualityRejection,
    clearQualityRejection,
    showSegmentedUpload,
    continueAnyway,
    submitWithForce,
    submitSegments,
  };
}
