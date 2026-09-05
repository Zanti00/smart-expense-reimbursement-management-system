// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import BaseKpiGrid from "./BaseKpiGrid.vue";

describe("BaseKpiGrid", () => {
  it("formats decimal values to 2 decimal places in KPI cards", () => {
    const kpis = [
      { label: "Total Reimbursement", value: "₱7,510.4", sub: "Approved claims" },
      { label: "Requests Count", value: 7, sub: "All filed requests" },
      { label: "Total Filed Cash Advance", value: 1200.1, sub: "Total amount" },
      { label: "Total Filed Liquidation", value: "₱44,750", sub: "Total amount" },
    ];

    const wrapper = mount(BaseKpiGrid, {
      props: { kpis, isLoading: false },
    });

    const values = wrapper.findAll(".kpi-value").map((el) => el.text());

    expect(values).toEqual([
      "₱7,510.40",
      "7",
      "1,200.10",
      "₱44,750.00",
    ]);
  });
});
