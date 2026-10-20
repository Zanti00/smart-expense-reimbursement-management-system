import { ref, unref } from "vue";
import { onBeforeRouteLeave } from "vue-router";

export function useUnsavedChanges(isDirtyRef, isSubmittedRef = ref(false)) {
  const showConfirmModal = ref(false);
  const isConfirmedLeave = ref(false);
  let pendingRouteResolve = null;
  let customFallbackAction = null;
  let customFallbackNavigates = false;

  onBeforeRouteLeave((to, from) => {
    if (isConfirmedLeave.value) {
      // Consume the one-shot confirmation issued by a navigating in-page
      // dismissal so the guard does not prompt a second time.
      isConfirmedLeave.value = false;
      return true;
    }

    const dirty = unref(isDirtyRef);
    const submitted = unref(isSubmittedRef);

    if (dirty && !submitted && !showConfirmModal.value) {
      showConfirmModal.value = true;
      return new Promise((resolve) => {
        pendingRouteResolve = resolve;
      });
    }
    
    return true;
  });

  function handleConfirmLeave() {
    showConfirmModal.value = false;

    if (pendingRouteResolve) {
      // Actual route leave: keep the confirmed flag so the navigation
      // guard does not prompt again while the component unmounts.
      isConfirmedLeave.value = true;
      pendingRouteResolve(true);
      pendingRouteResolve = null;
      return;
    }

    if (customFallbackAction) {
      const fallbackAction = customFallbackAction;
      const navigates = customFallbackNavigates;
      customFallbackAction = null;
      customFallbackNavigates = false;
      isConfirmedLeave.value = true;

      if (navigates) {
        // Leave the flag set: the route guard will consume it asynchronously.
        fallbackAction();
        return;
      }

      // Suppress nested prompts while the confirmed action runs, but do not
      // let the flag stick around for the next in-page dismissal.
      try {
        fallbackAction();
      } finally {
        isConfirmedLeave.value = false;
      }
    }
  }

  function handleCancelLeave() {
    showConfirmModal.value = false;
    isConfirmedLeave.value = false;
    customFallbackAction = null;
    customFallbackNavigates = false;
    
    if (pendingRouteResolve) {
      pendingRouteResolve(false);
      pendingRouteResolve = null;
    }
  }

  function dismissWithConfirm(fallbackAction, options = {}) {
    const dirty = unref(isDirtyRef);
    const submitted = unref(isSubmittedRef);

    if (dirty && !submitted && !isConfirmedLeave.value) {
      customFallbackAction = fallbackAction;
      customFallbackNavigates = Boolean(options.navigates);
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
