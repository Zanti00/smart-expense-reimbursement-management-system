// @vitest-environment happy-dom
import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { mount } from "@vue/test-utils";
import DaysLeftBadge from "./DaysLeftBadge.vue";

describe("DaysLeftBadge", () => {
  beforeEach(() => {
    // Pin time to 2026-10-21 00:00:00
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-10-21T00:00:00Z"));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it("renders 'x days left' with progress bar when due date is in the future", () => {
    const wrapper = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-26T00:00:00Z", // 5 days left
        startDate: "2026-10-19T00:00:00Z", // 7-day total duration, 2 elapsed
      },
    });

    expect(wrapper.text()).toContain("5 days left");
    const progressbar = wrapper.find('[role="progressbar"]');
    expect(progressbar.exists()).toBe(true);
    // 2 of 7 elapsed -> Math.round((2 / 7) * 100) = 29%
    expect(progressbar.attributes("aria-valuenow")).toBe("29");
  });

  it("handles singular '1 day left' correctly", () => {
    const wrapper = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-22T00:00:00Z", // 1 day left
        startDate: "2026-10-15T00:00:00Z",
      },
    });

    expect(wrapper.text()).toContain("1 day left");
    expect(wrapper.classes()).toContain("bg-amber-50");
  });

  it("renders '0 days left' with 100% progress and red styling when status is overdue", () => {
    const wrapper = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-15T00:00:00Z",
        status: "overdue",
      },
    });

    expect(wrapper.text()).toContain("0 days left");
    expect(wrapper.classes()).toContain("bg-red-50");
    expect(wrapper.classes()).toContain("text-red-700");
    const progressbar = wrapper.find('[role="progressbar"]');
    expect(progressbar.attributes("aria-valuenow")).toBe("100");
  });

  it("renders '0 days left' when isOverdue prop is true", () => {
    const wrapper = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-30T00:00:00Z",
        isOverdue: true,
      },
    });

    expect(wrapper.text()).toContain("0 days left");
    const progressbar = wrapper.find('[role="progressbar"]');
    expect(progressbar.attributes("aria-valuenow")).toBe("100");
  });

  it("does not render when status is liquidated, settled, or rejected", () => {
    const wrapperLiquidated = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-25T00:00:00Z",
        status: "liquidated",
      },
    });
    expect(wrapperLiquidated.find('[data-testid="days-left-badge"]').exists()).toBe(false);

    const wrapperSettled = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-25T00:00:00Z",
        status: "settled",
      },
    });
    expect(wrapperSettled.find('[data-testid="days-left-badge"]').exists()).toBe(false);

    const wrapperRejected = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-25T00:00:00Z",
        status: "rejected",
      },
    });
    expect(wrapperRejected.find('[data-testid="days-left-badge"]').exists()).toBe(false);
  });

  it("falls back to 7 days duration when startDate is omitted", () => {
    const wrapper = mount(DaysLeftBadge, {
      props: {
        dueDate: "2026-10-25T00:00:00Z", // 4 days left
      },
    });

    expect(wrapper.text()).toContain("4 days left");
    // 7 - 4 = 3 elapsed -> Math.round((3 / 7) * 100) = 43%
    const progressbar = wrapper.find('[role="progressbar"]');
    expect(progressbar.attributes("aria-valuenow")).toBe("43");
  });
});
