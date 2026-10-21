// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { ref } from "vue";
import { mount } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import CashAdvanceDetailsModal from "./CashAdvanceDetailsModal.vue";

const mockExportReport = vi.fn(() => Promise.resolve(true));
vi.mock("@/composables/useReportExport", () => ({
  useReportExport: () => ({
    isExporting: ref(false),
    exportReport: mockExportReport,
  }),
}));

const mockAuth = {
  isAdmin: true,
  user: { id: 99, name: "Admin User" },
};
vi.mock("@/stores/auth", () => ({
  useAuthStore: () => mockAuth,
}));

describe("CashAdvanceDetailsModal — Export Report (G2-056, G2-059)", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
  });

  it("renders Export Report button beside Disburse in Admin view without triggering Disburse (G2-059)", async () => {
    mockAuth.isAdmin = true;
    const wrapper = mount(CashAdvanceDetailsModal, {
      props: {
        isOpen: true,
        record: {
          id: 15,
          userId: 10,
          requestedBy: "Juan Dela Cruz",
          purpose: "Field Calibration",
          amount: 5000,
          balance: 5000,
          date: "2026-10-01",
          dueDate: "2026-10-10",
          status: "approved",
        },
      },
      global: {
        stubs: {
          UnifiedRoadmapStepper: true,
          StatusBadge: true,
          DecisionConfirmationModal: true,
          BaseModal: true,
        },
      },
    });

    const exportBtn = wrapper.find('[data-testid="export-cash-advance-report-btn"]');
    expect(exportBtn.exists()).toBe(true);
    expect(exportBtn.text()).toContain("Export Report");

    await exportBtn.trigger("click");
    expect(mockExportReport).toHaveBeenCalledWith("cash-advance", 15);
  });

  it("renders Export Report button in Employee Cash Advance view modal for any status (G2-056)", async () => {
    mockAuth.isAdmin = false;
    const wrapper = mount(CashAdvanceDetailsModal, {
      props: {
        isOpen: true,
        record: {
          id: 21,
          userId: 10,
          requestedBy: "Juan Dela Cruz",
          purpose: "Reagent Delivery",
          amount: 3200,
          balance: 3200,
          date: "2026-10-05",
          dueDate: "2026-10-12",
          status: "pending",
        },
      },
      global: {
        stubs: {
          UnifiedRoadmapStepper: true,
          StatusBadge: true,
          DecisionConfirmationModal: true,
          BaseModal: true,
        },
      },
    });

    const exportBtn = wrapper.find('[data-testid="export-cash-advance-report-btn"]');
    expect(exportBtn.exists()).toBe(true);
    await exportBtn.trigger("click");
    expect(mockExportReport).toHaveBeenCalledWith("cash-advance", 21);
  });
});
