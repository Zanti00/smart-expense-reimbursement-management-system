// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import CashAdvanceTable from "./CashAdvanceTable.vue";

describe("CashAdvanceTable Component Sorting", () => {
  const rows = [
    {
      id: 1,
      purpose: "Old Advance",
      user: "Employee One",
      amount: 1000,
      requested: "2026-08-01",
      created_at: "2026-08-01T08:00:00Z",
      dueDate: "2026-08-15",
      status: "pending",
    },
    {
      id: 2,
      purpose: "New Advance",
      user: "Employee Two",
      amount: 2500,
      requested: "2026-08-20",
      created_at: "2026-08-20T08:00:00Z",
      dueDate: "2026-08-30",
      status: "pending",
    },
    {
      id: 3,
      purpose: "Latest Advance Same Day Higher ID",
      user: "Employee Three",
      amount: 3000,
      requested: "2026-08-20",
      created_at: "2026-08-20T12:00:00Z",
      dueDate: "2026-08-30",
      status: "pending",
    },
  ];

  it("renders latest cash advances first by default (requested desc)", () => {
    const wrapper = mount(CashAdvanceTable, {
      props: {
        rows,
        isAdmin: true,
        isLoading: false,
      },
    });

    const purposeCells = wrapper.findAll("tbody tr td:first-child");
    expect(purposeCells.length).toBe(3);
    expect(purposeCells[0].text()).toContain("Latest Advance Same Day Higher ID");
    expect(purposeCells[1].text()).toContain("New Advance");
    expect(purposeCells[2].text()).toContain("Old Advance");
  });
});
