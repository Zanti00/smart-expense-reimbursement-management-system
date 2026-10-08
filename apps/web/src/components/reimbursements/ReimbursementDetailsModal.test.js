// @vitest-environment happy-dom
import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import ReimbursementDetailsModal from "./ReimbursementDetailsModal.vue";

vi.mock("@/stores/auth", () => ({
  useAuthStore: () => ({
    isAdmin: true,
    user: { id: "admin-1", name: "Admin User" },
  }),
}));

describe("ReimbursementDetailsModal — Custom User Comment Display", () => {
  it("displays the custom user comment on admin side when present", () => {
    const wrapper = mount(ReimbursementDetailsModal, {
      props: {
        viewingRecord: {
          id: 1,
          status: "pending",
          description: "Client Lunch",
          user_comment: "Lunch meeting with clients from Acme Corp.",
          user: { name: "John Doe" },
          cutoff_period: "2026-10",
          amount: 1500,
          receipts: [],
        },
        receiptDetailsOpen: false,
        modalLoading: false,
      },
      global: {
        stubs: {
          StatusBadge: true,
          BaseReceiptImage: true,
        },
      },
    });

    expect(wrapper.text()).toContain("User Comment");
    expect(wrapper.text()).toContain("Lunch meeting with clients from Acme Corp.");
  });

  it("displays fallback message when user_comment is empty or null", () => {
    const wrapper = mount(ReimbursementDetailsModal, {
      props: {
        viewingRecord: {
          id: 2,
          status: "pending",
          description: "Office Supplies",
          user_comment: null,
          user: { name: "Jane Smith" },
          cutoff_period: "2026-10",
          amount: 500,
          receipts: [],
        },
        receiptDetailsOpen: false,
        modalLoading: false,
      },
      global: {
        stubs: {
          StatusBadge: true,
          BaseReceiptImage: true,
        },
      },
    });

    expect(wrapper.text()).toContain("User Comment");
    expect(wrapper.text()).toContain("No comment provided");
  });
});
