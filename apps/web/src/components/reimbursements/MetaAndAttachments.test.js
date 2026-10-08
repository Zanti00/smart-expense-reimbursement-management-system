// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import MetaAndAttachments from "./MetaAndAttachments.vue";
import CutoffPeriodDatePicker from "./CutoffPeriodDatePicker.vue";

describe("MetaAndAttachments", () => {
  it("renders CutoffPeriodDatePicker instead of a select dropdown", () => {
    const wrapper = mount(MetaAndAttachments, {
      props: {
        cutoffPeriod: "Oct 01 - Oct 15, 2026",
        reportFile: null,
      },
    });

    // select dropdown should no longer exist
    expect(wrapper.find("select").exists()).toBe(false);

    // CutoffPeriodDatePicker should be rendered
    const datePicker = wrapper.findComponent(CutoffPeriodDatePicker);
    expect(datePicker.exists()).toBe(true);
    expect(datePicker.props("modelValue")).toBe("Oct 01 - Oct 15, 2026");
  });

  it("relays update:cutoffPeriod when date picker emits update", async () => {
    const wrapper = mount(MetaAndAttachments, {
      props: {
        cutoffPeriod: "",
        reportFile: null,
      },
    });

    const datePicker = wrapper.findComponent(CutoffPeriodDatePicker);
    await datePicker.vm.$emit("update:modelValue", "Nov 16 - Nov 30, 2026");

    expect(wrapper.emitted("update:cutoffPeriod")).toBeTruthy();
    expect(wrapper.emitted("update:cutoffPeriod")[0]).toEqual(["Nov 16 - Nov 30, 2026"]);
  });
});
