// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import LiquidationAdvancesList from "./LiquidationAdvancesList.vue";

describe("LiquidationAdvancesList Component", () => {
  const sortOptions = [
    { value: "date", label: "Date" },
    { value: "status", label: "Status" },
    { value: "amount", label: "Total Amount" },
  ];

  const generateAdvances = (count) =>
    Array.from({ length: count }, (_, i) => ({
      id: i + 1,
      purpose: `Advance Purpose ${i + 1}`,
      amount: (i + 1) * 1000,
      balance: (i + 1) * 1000,
      status: "signed",
      submitted_at: `2026-08-${String(10 + i).padStart(2, "0")}T08:00:00Z`,
      date: `2026-08-${String(10 + i).padStart(2, "0")}`,
    }));

  const mockGetBadgeStatus = (adv) => adv.status || "pending";
  const mockCalculateAging = () => ({
    daysSinceIssue: 3,
    isOverdue: false,
    penalty: 0,
  });

  it("renders at most 8 items when advances exceed 8", () => {
    const advances = generateAdvances(15);
    const wrapper = mount(LiquidationAdvancesList, {
      props: {
        sortOptions,
        sortKey: "date",
        sortDirection: "desc",
        isLoading: false,
        advances,
        getBadgeStatus: mockGetBadgeStatus,
        calculateAging: mockCalculateAging,
        collapsed: false,
        pageSize: 8,
      },
    });

    const purposeElements = wrapper.findAll(".truncate");
    // Should have 8 advance purpose titles on page 1
    const advancePurposes = purposeElements.filter((el) =>
      el.text().includes("Advance Purpose"),
    );
    expect(advancePurposes.length).toBe(8);
    expect(advancePurposes[0].text()).toBe("Advance Purpose 1");
    expect(advancePurposes[7].text()).toBe("Advance Purpose 8");
  });

  it("renders BasePagination when total advances > 8", () => {
    const advances = generateAdvances(10);
    const wrapper = mount(LiquidationAdvancesList, {
      props: {
        sortOptions,
        sortKey: "date",
        sortDirection: "desc",
        isLoading: false,
        advances,
        getBadgeStatus: mockGetBadgeStatus,
        calculateAging: mockCalculateAging,
        collapsed: false,
        pageSize: 8,
      },
    });

    expect(wrapper.text()).toContain("Showing 1-8 of 10 advances");
    expect(wrapper.text()).toContain("1 / 2");
  });

  it("does not render BasePagination when total advances <= 8", () => {
    const advances = generateAdvances(5);
    const wrapper = mount(LiquidationAdvancesList, {
      props: {
        sortOptions,
        sortKey: "date",
        sortDirection: "desc",
        isLoading: false,
        advances,
        getBadgeStatus: mockGetBadgeStatus,
        calculateAging: mockCalculateAging,
        collapsed: false,
        pageSize: 8,
      },
    });

    expect(wrapper.text()).not.toContain("Showing");
    expect(wrapper.text()).not.toContain("1 / 1");
  });

  it("navigates to the next page when Next button is clicked", async () => {
    const advances = generateAdvances(12);
    const wrapper = mount(LiquidationAdvancesList, {
      props: {
        sortOptions,
        sortKey: "date",
        sortDirection: "desc",
        isLoading: false,
        advances,
        getBadgeStatus: mockGetBadgeStatus,
        calculateAging: mockCalculateAging,
        collapsed: false,
        pageSize: 8,
      },
    });

    const nextButton = wrapper
      .findAll("button")
      .find((b) => b.text() === "Next");
    expect(nextButton).toBeDefined();

    await nextButton.trigger("click");

    expect(wrapper.text()).toContain("Showing 9-12 of 12 advances");
    expect(wrapper.text()).toContain("2 / 2");

    const purposeElements = wrapper.findAll(".truncate");
    const advancePurposes = purposeElements.filter((el) =>
      el.text().includes("Advance Purpose"),
    );
    expect(advancePurposes.length).toBe(4);
    expect(advancePurposes[0].text()).toBe("Advance Purpose 9");
    expect(advancePurposes[3].text()).toBe("Advance Purpose 12");
  });

  it("emits update:sortKey and update:sortDirection when a sort option is clicked", async () => {
    const advances = generateAdvances(3);
    const wrapper = mount(LiquidationAdvancesList, {
      props: {
        sortOptions,
        sortKey: "date",
        sortDirection: "desc",
        isLoading: false,
        advances,
        getBadgeStatus: mockGetBadgeStatus,
        calculateAging: mockCalculateAging,
        collapsed: false,
      },
    });

    const sortButtons = wrapper.findAll("button[type='button']");
    const amountSortBtn = sortButtons.find((b) =>
      b.text().includes("Total Amount"),
    );
    expect(amountSortBtn).toBeDefined();

    await amountSortBtn.trigger("click");
    expect(wrapper.emitted("update:sortKey")).toBeTruthy();
    expect(wrapper.emitted("update:sortKey")[0]).toEqual(["amount"]);
    expect(wrapper.emitted("update:sortDirection")).toBeTruthy();
    expect(wrapper.emitted("update:sortDirection")[0]).toEqual(["asc"]);
  });

  it("handles collapsed state pagination when advances > 8", () => {
    const advances = generateAdvances(10);
    const wrapper = mount(LiquidationAdvancesList, {
      props: {
        sortOptions,
        sortKey: "date",
        sortDirection: "desc",
        isLoading: false,
        advances,
        getBadgeStatus: mockGetBadgeStatus,
        calculateAging: mockCalculateAging,
        collapsed: true,
        pageSize: 8,
      },
    });

    expect(wrapper.text()).toContain("1 / 2");
    const purposeElements = wrapper.findAll(".truncate");
    const advancePurposes = purposeElements.filter((el) =>
      el.text().includes("Advance Purpose"),
    );
    expect(advancePurposes.length).toBe(8);
  });
});
