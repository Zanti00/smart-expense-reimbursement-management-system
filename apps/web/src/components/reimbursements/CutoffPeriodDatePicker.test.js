// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import CutoffPeriodDatePicker from "./CutoffPeriodDatePicker.vue";

describe("CutoffPeriodDatePicker", () => {
  const referenceDate = new Date(2026, 9, 8); // Oct 8, 2026 (month is 0-indexed: 9 = October)

  it("renders trigger input with placeholder when empty", () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    const trigger = wrapper.find("[data-testid='cutoff-trigger']");
    expect(trigger.exists()).toBe(true);
    expect(trigger.text()).toContain("Select cutoff period");
  });

  it("displays the selected cutoff period when modelValue is passed", () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "Oct 01 - Oct 15, 2026",
        referenceDate,
      },
    });

    const trigger = wrapper.find("[data-testid='cutoff-trigger']");
    expect(trigger.text()).toContain("Oct 01 - Oct 15, 2026");
  });

  it("opens the calendar popover when trigger is clicked", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    expect(wrapper.find("[data-testid='cutoff-calendar-popover']").exists()).toBe(false);

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");

    expect(wrapper.find("[data-testid='cutoff-calendar-popover']").exists()).toBe(true);
  });

  it("allows selecting only day 1 and day 16; other days are disabled", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");

    // Oct 1 and Oct 16 should not be disabled
    const day1Btn = wrapper.find("[data-testid='day-btn-1']");
    const day16Btn = wrapper.find("[data-testid='day-btn-16']");
    expect(day1Btn.exists()).toBe(true);
    expect(day16Btn.exists()).toBe(true);
    expect(day1Btn.attributes("disabled")).toBeUndefined();
    expect(day16Btn.attributes("disabled")).toBeUndefined();

    // Other days (e.g. 2, 15, 17) should be disabled
    const day2Btn = wrapper.find("[data-testid='day-btn-2']");
    const day15Btn = wrapper.find("[data-testid='day-btn-15']");
    expect(day2Btn.attributes("disabled")).toBeDefined();
    expect(day15Btn.attributes("disabled")).toBeDefined();
  });

  it("disables past cutoff dates and enables current and future dates", async () => {
    // If today is Oct 20, 2026, Oct 1st (Oct 1-15) is past, so day 1 should be disabled
    const lateMonthRef = new Date(2026, 9, 20); // Oct 20, 2026
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate: lateMonthRef,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");

    const day1Btn = wrapper.find("[data-testid='day-btn-1']");
    const day16Btn = wrapper.find("[data-testid='day-btn-16']");

    // Oct 1st is past on Oct 20th
    expect(day1Btn.attributes("disabled")).toBeDefined();
    // Oct 16th is current cutoff on Oct 20th
    expect(day16Btn.attributes("disabled")).toBeUndefined();
  });

  it("automatically selects 1st to 15th when day 1 is clicked", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");
    await wrapper.find("[data-testid='day-btn-1']").trigger("click");

    expect(wrapper.emitted("update:modelValue")).toBeTruthy();
    expect(wrapper.emitted("update:modelValue")[0]).toEqual(["Oct 01 - Oct 15, 2026"]);
    // Also emits update:cutoffPeriod for backward/direct v-model:cutoff-period compatibility
    expect(wrapper.emitted("update:cutoffPeriod")[0]).toEqual(["Oct 01 - Oct 15, 2026"]);
  });

  it("automatically selects 16th to end of month when day 16 is clicked", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");
    await wrapper.find("[data-testid='day-btn-16']").trigger("click");

    expect(wrapper.emitted("update:modelValue")).toBeTruthy();
    expect(wrapper.emitted("update:modelValue")[0]).toEqual(["Oct 16 - Oct 31, 2026"]);
  });

  it("calculates correct month end for 30-day month (November)", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");

    // Click next month (November 2026)
    const nextBtn = wrapper.find("[data-testid='next-month-btn']");
    await nextBtn.trigger("click");

    // Click day 16 in November
    await wrapper.find("[data-testid='day-btn-16']").trigger("click");

    expect(wrapper.emitted("update:modelValue")[0]).toEqual(["Nov 16 - Nov 30, 2026"]);
  });

  it("calculates leap year February 29th properly", async () => {
    // Leap year Feb 2028
    const leapYearDate = new Date(2028, 1, 5); // Feb 5, 2028
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate: leapYearDate,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");
    await wrapper.find("[data-testid='day-btn-16']").trigger("click");

    expect(wrapper.emitted("update:modelValue")[0]).toEqual(["Feb 16 - Feb 29, 2028"]);
  });

  it("disables prev-month navigation if previous month is completely in the past", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate, // Oct 2026
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");

    const prevBtn = wrapper.find("[data-testid='prev-month-btn']");
    expect(prevBtn.attributes("disabled")).toBeDefined();
  });

  it("toggles popover off when clicking trigger again", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        modelValue: "",
        referenceDate,
      },
    });

    const trigger = wrapper.find("[data-testid='cutoff-trigger']");
    await trigger.trigger("click");
    expect(wrapper.find("[data-testid='cutoff-calendar-popover']").exists()).toBe(true);

    await trigger.trigger("click");
    expect(wrapper.find("[data-testid='cutoff-calendar-popover']").exists()).toBe(false);
  });

  it("initializes calendar view to the provided cutoffPeriod prop month", async () => {
    const wrapper = mount(CutoffPeriodDatePicker, {
      props: {
        cutoffPeriod: "Dec 16 - Dec 31, 2026",
        referenceDate,
      },
    });

    await wrapper.find("[data-testid='cutoff-trigger']").trigger("click");

    const header = wrapper.find("h4");
    expect(header.text()).toContain("Dec 2026");
  });
});
