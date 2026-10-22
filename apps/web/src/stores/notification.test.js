import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { useNotificationStore } from "./notification";

const mockApiFetch = vi.fn();
const mockAddToast = vi.fn();

vi.mock("@/utils/apiFetch", () => ({
  apiFetch: (...args) => mockApiFetch(...args),
}));

vi.mock("@/composables/useToast", () => ({
  useToast: () => ({ addToast: mockAddToast }),
}));

describe("useNotificationStore", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("fetches notifications and computes unreadCount", async () => {
    mockApiFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        unread_count: 1,
        data: [
          {
            id: 10,
            user_id: 1,
            type: "reimbursement_submitted",
            module: "Reimbursement",
            title: "Reimbursement Submitted",
            message: "Your reimbursement of ₱4,500.10 was submitted.",
            record_link: "/reimbursements?view=5",
            is_read: false,
            created_at: "2026-10-22T10:00:00Z",
          },
        ],
      }),
    });

    const store = useNotificationStore();
    await store.fetchNotifications();

    expect(mockApiFetch).toHaveBeenCalledWith("/api/serms/notifications");
    expect(store.alerts).toHaveLength(1);
    expect(store.unreadCount).toBe(1);
    expect(store.alerts[0].recordLink).toBe("/reimbursements?view=5");
  });

  it("shows error toast on 403 forbidden when marking another user's notification read", async () => {
    mockApiFetch.mockResolvedValueOnce({
      ok: false,
      status: 403,
      json: async () => ({ message: "Forbidden." }),
    });

    const store = useNotificationStore();
    await expect(store.markRead(999)).rejects.toThrow();
    expect(mockAddToast).toHaveBeenCalledWith(
      expect.objectContaining({
        type: "error",
        message: expect.stringMatching(/Access denied|Forbidden/i),
      }),
    );
  });

  it("polls every 30 seconds when startPolling is active", async () => {
    mockApiFetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ unread_count: 0, data: [] }),
    });

    const store = useNotificationStore();
    store.startPolling(30000);

    await vi.advanceTimersByTimeAsync(30000);
    expect(mockApiFetch).toHaveBeenCalledTimes(1);

    await vi.advanceTimersByTimeAsync(30000);
    expect(mockApiFetch).toHaveBeenCalledTimes(2);

    store.stopPolling();
  });
});
