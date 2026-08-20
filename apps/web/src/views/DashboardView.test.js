// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import DashboardView from "@/views/DashboardView.vue";
import { useAuthStore } from "@/stores/auth";
import { useReimbursementStore } from "@/stores/reimbursement";
import { useCashAdvanceStore } from "@/stores/cashAdvance";
import { useLiquidationStore } from "@/stores/liquidation";

vi.mock("vue-chartjs", async () => {
  const { defineComponent, h } = await import("vue");
  return {
    Bar: defineComponent({
      name: "Bar",
      props: ["data", "options"],
      render() {
        return h("div", { class: "mock-bar-chart", "data-testid": "bar-chart" });
      },
    }),
    Pie: defineComponent({
      name: "Pie",
      props: ["data", "options"],
      render() {
        return h("div", { class: "mock-pie-chart", "data-testid": "pie-chart" });
      },
    }),
    Line: defineComponent({
      name: "Line",
      props: ["data", "options"],
      render() {
        return h("div", { class: "mock-line-chart", "data-testid": "line-chart" });
      },
    }),
    Doughnut: defineComponent({
      name: "Doughnut",
      props: ["data", "options"],
      render() {
        return h("div", { class: "mock-doughnut-chart", "data-testid": "doughnut-chart" });
      },
    }),
  };
});

describe("DashboardView", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it("renders employee dashboard with empty charts without blank cards or errors", async () => {
    const auth = useAuthStore();
    auth.user = { id: 1, name: "General Employee", email: "employee@example.com", is_admin: false };

    const rStore = useReimbursementStore();
    rStore.items = [];
    rStore.fetchAll = vi.fn().mockResolvedValue([]);

    const caStore = useCashAdvanceStore();
    caStore.items = [];
    caStore.fetchAll = vi.fn().mockResolvedValue([]);

    const liqStore = useLiquidationStore();
    liqStore.settlements = [];
    liqStore.fetchSettlements = vi.fn().mockResolvedValue([]);

    const wrapper = mount(DashboardView, {
      global: {
        stubs: {
          BaseKpiGrid: true,
          SkeletonLoader: true,
          Bar: { template: "<div class='mock-bar-chart'></div>" },
          Line: { template: "<div class='mock-line-chart'></div>" },
          Pie: { template: "<div class='mock-pie-chart'></div>" },
        },
      },
    });

    expect(wrapper.text()).toContain("Welcome back, General");
    expect(wrapper.text()).toContain("Monthly Spending Trend for Reimbursement and My Expense");
    expect(wrapper.text()).toContain("Spending by Category");
    expect(wrapper.text()).toContain("Liquidation Trend");

    // Mock chart components should be rendered
    expect(wrapper.findAll(".mock-bar-chart").length).toBe(1);
    expect(wrapper.findAll(".mock-pie-chart").length).toBe(1);
    expect(wrapper.findAll(".mock-line-chart").length).toBe(1);
  });

  it("renders admin dashboard with all 4 charts when admin logs in", async () => {
    const auth = useAuthStore();
    auth.user = { id: 2, name: "Admin User", email: "admin@example.com", is_admin: true };

    const rStore = useReimbursementStore();
    rStore.items = [];
    rStore.fetchAll = vi.fn().mockResolvedValue([]);

    const caStore = useCashAdvanceStore();
    caStore.items = [];
    caStore.fetchAll = vi.fn().mockResolvedValue([]);

    const liqStore = useLiquidationStore();
    liqStore.settlements = [];
    liqStore.fetchSettlements = vi.fn().mockResolvedValue([]);

    const wrapper = mount(DashboardView, {
      global: {
        stubs: {
          BaseKpiGrid: true,
          SkeletonLoader: true,
          Bar: { template: "<div class='mock-bar-chart'></div>" },
          Line: { template: "<div class='mock-line-chart'></div>" },
          Pie: { template: "<div class='mock-pie-chart'></div>" },
        },
      },
    });

    expect(wrapper.text()).toContain("Welcome back, Admin");
    expect(wrapper.text()).toContain("Monthly Spending Trend");
    expect(wrapper.text()).toContain("Cash Advance Status Distribution");
    expect(wrapper.text()).toContain("Liquidation Volume");
    expect(wrapper.text()).toContain("Advance vs Outstanding Balance");

    expect(wrapper.findAll(".mock-bar-chart").length).toBe(1);
    expect(wrapper.findAll(".mock-pie-chart").length).toBe(2);
    expect(wrapper.findAll(".mock-line-chart").length).toBe(1);
  });
});
