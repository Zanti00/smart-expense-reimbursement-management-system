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

  it("renders optional comments textarea and relays update:userComment on input", async () => {
    const wrapper = mount(MetaAndAttachments, {
      props: {
        cutoffPeriod: "Oct 01 - Oct 15, 2026",
        reportFile: null,
        userComment: "Initial note",
      },
    });

    const textarea = wrapper.find("textarea#reimbursement-user-comment");
    expect(textarea.exists()).toBe(true);
    expect(textarea.element.value).toBe("Initial note");
    expect(wrapper.text()).toContain("Comments / Notes (Optional)");
    expect(wrapper.text()).toContain("12 / 1000");

    await textarea.setValue("Updated comment for admin");
    expect(wrapper.emitted("update:userComment")).toBeTruthy();
    expect(wrapper.emitted("update:userComment")[0]).toEqual(["Updated comment for admin"]);
  });

  it("assigns higher stacking context z-index to Cutoff Period card than Comments card", () => {
    const wrapper = mount(MetaAndAttachments, {
      props: {
        cutoffPeriod: "Oct 01 - Oct 15, 2026",
        reportFile: null,
      },
    });

    const cards = wrapper.findAll("section.card");
    expect(cards.length).toBe(3);

    // Cutoff Period section (first card) must have z-30
    expect(cards[0].classes()).toContain("z-30");

    // Report section (second card) must have z-20
    expect(cards[1].classes()).toContain("z-20");

    // Comments section (third card) must have z-10
    expect(cards[2].classes()).toContain("z-10");
  });
});


