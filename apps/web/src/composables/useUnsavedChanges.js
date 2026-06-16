import { ref, unref } from "vue";
import { onBeforeRouteLeave } from "vue-router";

export function useUnsavedChanges(isDirtyRef, isSubmittedRef = ref(false)) {
  const showConfirmModal = ref(false);
  const isConfirmedLeave = ref(false);
  let pendingRouteResolve = null;
  let customFallbackAction = null;

  onBeforeRouteLeave((to, from) => {
    const dirty = unref(isDirtyRef);
    const submitted = unref(isSubmittedRef);

    if (dirty && !submitted && !isConfirmedLeave.value && !showConfirmModal.value) {
      showConfirmModal.value = true;
      return new Promise((resolve) => {
        pendingRouteResolve = resolve;
      });
    }
    
    return true;
  });

  function handleConfirmLeave() {
    isConfirmedLeave.value = true;
    showConfirmModal.value = false;

    if (pendingRouteResolve) {
      pendingRouteResolve(true);
      pendingRouteResolve = null;
    } else if (customFallbackAction) {
      customFallbackAction();
      customFallbackAction = null;
    }
  }

  function handleCancelLeave() {
    showConfirmModal.value = false;
    
    if (pendingRouteResolve) {
      pendingRouteResolve(false);
      pendingRouteResolve = null;
    }
    customFallbackAction = null;
  }

  function dismissWithConfirm(fallbackAction) {
    const dirty = unref(isDirtyRef);
    const submitted = unref(isSubmittedRef);

    if (dirty && !submitted && !isConfirmedLeave.value) {
      customFallbackAction = fallbackAction;
      showConfirmModal.value = true;
      return;
    }
    
    fallbackAction();
  }

  return {
    showConfirmModal,
    isConfirmedLeave,
    handleConfirmLeave,
    handleCancelLeave,
    dismissWithConfirm
  };
}
