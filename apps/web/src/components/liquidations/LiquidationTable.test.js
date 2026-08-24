// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import LiquidationTable from "./LiquidationTable.vue";

describe("LiquidationTable Component", () => {
  const columns = [
    { key: "purpose", label: "Purpose" },
    { key: "requestorName", label: "Requestor Name" },
    { key: "dateSubmitted", label: "Date Submitted" },
    { key: "dueDate", label: "Due Date" },
    { key: "cashAdvanceAmount", label: "Cash Advance Amount", align: "right" },
    { key: "status", label: "Status", align: "center" },
    { key: "actions", sortKey: "databaseId", label: "Actions", align: "center" },
  ];

  const rows = [
    {
      id: "LIQ-003",
      databaseId: 3,
      purpose: "Latest Liquidation",
      requestorName: "Alice Smith",
      dateSubmitted: "2026-08-20",
      dueDate: "2026-08-25",
      cashAdvanceAmount: 3000,
      status: "Pending",
    },
    {
      id: "LIQ-001",
      databaseId: 1,
      purpose: "Oldest Liquidation",
      requestorName: "Bob Jones",
      dateSubmitted: "2026-08-01",
      dueDate: "2026-08-10",
      cashAdvanceAmount: 1500,
      status: "Liquidated",
    },
  ];

  it("renders rows correctly including dateSubmitted column", () => {
    const wrapper = mount(LiquidationTable, {
      props: {
        columns,
        rows,
        isLoading: false,
        sortKey: "dateSubmitted",
        sortDirection: "desc",
      },
    });

    const ths = wrapper.findAll("thead th");
    expect(ths.length).toBe(7);
    expect(ths[2].text()).toContain("Date Submitted");

    const trs = wrapper.findAll("tbody tr");
    expect(trs.length).toBe(2);
    expect(trs[0].text()).toContain("Latest Liquidation");
    expect(trs[1].text()).toContain("Oldest Liquidation");
  });

  it("emits sort event when clicking on header column", async () => {
    const wrapper = mount(LiquidationTable, {
      props: {
        columns,
        rows,
        isLoading: false,
        sortKey: "dateSubmitted",
        sortDirection: "desc",
      },
    });

    const dateColButton = wrapper.findAll("thead th button")[2];
    await dateColButton.trigger("click");

    expect(wrapper.emitted("sort")).toBeTruthy();
    expect(wrapper.emitted("sort")[0][0]).toEqual(columns[2]);
  });
});
