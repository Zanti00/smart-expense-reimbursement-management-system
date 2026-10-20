// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";

const { onBeforeRouteLeaveMock } = vi.hoisted(() => ({
  onBeforeRouteLeaveMock: vi.fn(),
}));

vi.mock("vue-router", () => ({
  onBeforeRouteLeave: onBeforeRouteLeaveMock,
}));

import { useUnsavedChanges } from "@/composables/useUnsavedChanges";

describe("useUnsavedChanges", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("prompts again after an in-page dismissal was confirmed", () => {
    const isDirty = ref(true);
    const { showConfirmModal, handleConfirmLeave, dismissWithConfirm } =
      useUnsavedChanges(isDirty);

    const first = vi.fn();
    dismissWithConfirm(first);
    expect(showConfirmModal.value).toBe(true);
    expect(first).not.toHaveBeenCalled();

    handleConfirmLeave();
    expect(showConfirmModal.value).toBe(false);
    expect(first).toHaveBeenCalledTimes(1);

    // A later in-page dismissal must prompt again; the first confirmation
    // must not leave a sticky "already confirmed" flag behind.
    const second = vi.fn();
    dismissWithConfirm(second);
    expect(showConfirmModal.value).toBe(true);
    expect(second).not.toHaveBeenCalled();

    handleConfirmLeave();
    expect(showConfirmModal.value).toBe(false);
    expect(second).toHaveBeenCalledTimes(1);
  });

  it("drops the cancelled callback so only a later action can run", () => {
    const isDirty = ref(true);
    const { showConfirmModal, handleConfirmLeave, handleCancelLeave, dismissWithConfirm } =
      useUnsavedChanges(isDirty);

    const cancelled = vi.fn();
    dismissWithConfirm(cancelled);
    expect(showConfirmModal.value).toBe(true);

    handleCancelLeave();
    expect(showConfirmModal.value).toBe(false);

    const later = vi.fn();
    dismissWithConfirm(later);
    expect(showConfirmModal.value).toBe(true);
    expect(later).not.toHaveBeenCalled();
    expect(cancelled).not.toHaveBeenCalled();

    handleConfirmLeave();
    expect(later).toHaveBeenCalledTimes(1);
    expect(cancelled).not.toHaveBeenCalled();
  });

  it("registers the route-leave guard and still supports in-page confirmation", () => {
    const isDirty = ref(true);
    const { showConfirmModal, handleConfirmLeave, dismissWithConfirm } =
      useUnsavedChanges(isDirty);

    expect(onBeforeRouteLeaveMock).toHaveBeenCalledTimes(1);
    expect(typeof onBeforeRouteLeaveMock.mock.calls[0][0]).toBe("function");

    const action = vi.fn();
    dismissWithConfirm(action);
    expect(showConfirmModal.value).toBe(true);

    handleConfirmLeave();
    expect(action).toHaveBeenCalledTimes(1);
    expect(showConfirmModal.value).toBe(false);
  });
});
