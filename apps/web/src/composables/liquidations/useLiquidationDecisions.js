import { ref } from "vue";
import { useAuthStore } from "@/stores/auth";

export function useLiquidationDecisions(liqStore, addToast, reviewingCase, refreshAllCallback, closeReviewCallback) {
  const auth = useAuthStore();
  const approvingId = ref(null);
  const rejectingId = ref(null);
  const rejectionComment = ref("");
  const confirmPassword = ref("");
  const isReviewSubmitting = ref(false);

  function isReviewingOwnLiquidation() {
    const currentUserId = auth.user?.id;
    const ownerId =
      reviewingCase.value?.requestorId ??
      reviewingCase.value?.userId ??
      reviewingCase.value?.user_id;

    return (
      currentUserId !== null &&
      currentUserId !== undefined &&
      ownerId !== null &&
      ownerId !== undefined &&
      String(currentUserId) === String(ownerId)
    );
  }

  function openApproveModal() {
    if (isReviewingOwnLiquidation()) {
      addToast({
        title: "Action Not Allowed",
        message: "You cannot process your own liquidation settlement.",
        type: "danger",
      });
      return;
    }

    confirmPassword.value = "";
    rejectionComment.value = "";
    approvingId.value = reviewingCase.value?.databaseId || null;
    rejectingId.value = null;
  }

  function openRejectModal() {
    if (isReviewingOwnLiquidation()) {
      addToast({
        title: "Action Not Allowed",
        message: "You cannot process your own liquidation settlement.",
        type: "danger",
      });
      return;
    }

    confirmPassword.value = "";
    rejectionComment.value = "";
    rejectingId.value = reviewingCase.value?.databaseId || null;
    approvingId.value = null;
  }

  function cancelApprove() {
    approvingId.value = null;
    confirmPassword.value = "";
  }

  function cancelReject() {
    rejectingId.value = null;
    confirmPassword.value = "";
    rejectionComment.value = "";
  }

  async function confirmApprove() {
    if (!approvingId.value) return;
    if (!confirmPassword.value?.trim()) {
      addToast({
        title: "Validation Error",
        message: "Password is required to approve this settlement.",
        type: "danger",
      });
      return;
    }
    if (
      rejectionComment.value.trim() &&
      rejectionComment.value.trim().length < 10
    ) {
      addToast({
        title: "Validation Error",
        message: "Admin note must be at least 10 characters.",
        type: "danger",
      });
      return;
    }
    isReviewSubmitting.value = true;
    try {
      await liqStore.auditSettlement(approvingId.value, {
        status: "approved",
        password: confirmPassword.value,
        admin_note: rejectionComment.value.trim() || null,
      });
      addToast({
        title: "Settlement Approved",
        message: "The liquidation settlement was successfully approved.",
        type: "success",
      });

      if (refreshAllCallback) await refreshAllCallback();
      if (closeReviewCallback) closeReviewCallback();
      
      cancelApprove();
    } catch (err) {
      addToast({
        title: "Audit Failed",
        message: err.message || "Failed to approve liquidation settlement.",
        type: "danger",
      });
    } finally {
      isReviewSubmitting.value = false;
    }
  }

  async function confirmReject() {
    if (!rejectingId.value) return;
    if (!confirmPassword.value?.trim()) {
      addToast({
        title: "Validation Error",
        message: "Password is required to reject this settlement.",
        type: "danger",
      });
      return;
    }
    if (rejectionComment.value.length < 5) {
      addToast({
        title: "Validation Error",
        message: "Rejection comment must be at least 5 characters.",
        type: "danger",
      });
      return;
    }
    isReviewSubmitting.value = true;
    try {
      await liqStore.auditSettlement(rejectingId.value, {
        status: "rejected",
        password: confirmPassword.value,
        admin_note: rejectionComment.value,
      });
      addToast({
        title: "Settlement Rejected",
        message: "The liquidation settlement was successfully rejected.",
        type: "success",
      });

      if (refreshAllCallback) await refreshAllCallback();
      if (closeReviewCallback) closeReviewCallback();
      
      cancelReject();
    } catch (err) {
      addToast({
        title: "Audit Failed",
        message: err.message || "Failed to reject liquidation settlement.",
        type: "danger",
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
    openRejectModal,
    cancelApprove,
    cancelReject,
    confirmApprove,
    confirmReject,
  };
}
