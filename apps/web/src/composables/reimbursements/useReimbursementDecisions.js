import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";

export function useReimbursementDecisions(store, addToast, viewingRecord) {
  const auth = useAuthStore();
  const approvingId = ref(null);
  const rejectingId = ref(null);
  const rejectionComment = ref("");
  const confirmPassword = ref("");
  const isReviewSubmitting = ref(false);

  function isOwnSubmission() {
    const currentUserId = auth.user?.id;
    const ownerId =
      viewingRecord.value?.user?.id ??
      viewingRecord.value?.user_id ??
      viewingRecord.value?.userId ??
      viewingRecord.value?.submitted_by;

    return (
      currentUserId !== null &&
      currentUserId !== undefined &&
      ownerId !== null &&
      ownerId !== undefined &&
      String(currentUserId) === String(ownerId)
    );
  }

  function openApproveModal(id) {
    if (isOwnSubmission()) {
      addToast({
        message: "You cannot process your own request.",
        type: "error",
      });
      return;
    }

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
    if (isOwnSubmission()) {
      addToast({
        message: "You cannot process your own request.",
        type: "error",
      });
      return;
    }

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
