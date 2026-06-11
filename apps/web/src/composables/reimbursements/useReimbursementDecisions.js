import { ref } from "vue";

export function useReimbursementDecisions(store, addToast, viewingRecord) {
  const approvingId = ref(null);
  const rejectingId = ref(null);
  const rejectionComment = ref("");
  const confirmPassword = ref("");
  const isReviewSubmitting = ref(false);

  function openApproveModal(id) {
    approvingId.value = id;
    confirmPassword.value = "";
  }

  function cancelApprove() {
    approvingId.value = null;
    confirmPassword.value = "";
  }

  async function confirmApprove() {
    if (!approvingId.value) return;

    isReviewSubmitting.value = true;
    try {
      const updated = await store.approve(
        approvingId.value,
        confirmPassword.value,
      );
      if (viewingRecord.value?.id === approvingId.value) {
        viewingRecord.value = updated;
      }
      addToast({ message: "Reimbursement approved!", type: "success" });
      cancelApprove();
    } catch (e) {
      addToast({
        message: e.message || "Error approving reimbursement",
        type: "error",
      });
    } finally {
      isReviewSubmitting.value = false;
    }
  }

  function openRejectModal(id) {
    rejectingId.value = id;
    rejectionComment.value = "";
    confirmPassword.value = "";
  }

  function cancelReject() {
    rejectingId.value = null;
    rejectionComment.value = "";
    confirmPassword.value = "";
  }

  async function confirmReject() {
    if (rejectionComment.value.length < 10) {
      addToast({
        message: "Rejection comment must be at least 10 characters.",
        type: "error",
      });
      return;
    }

    isReviewSubmitting.value = true;
    try {
      const updated = await store.reject(
        rejectingId.value,
        rejectionComment.value,
        confirmPassword.value,
      );
      if (viewingRecord.value?.id === rejectingId.value) {
        viewingRecord.value = updated;
      }
      addToast({ message: "Reimbursement rejected.", type: "success" });
      cancelReject();
    } catch (e) {
      addToast({
        message: e.message || "Error rejecting reimbursement",
        type: "error",
      });
    } finally {
      isReviewSubmitting.value = false;
    }
  }

  return {
    approvingId,
    rejectingId,
    rejectionComment,
    confirmPassword,
    isReviewSubmitting,
    openApproveModal,
    cancelApprove,
    confirmApprove,
    openRejectModal,
    cancelReject,
    confirmReject,
  };
}
