// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import BaseToggleSwitch from "./BaseToggleSwitch.vue";

describe("BaseToggleSwitch", () => {
  it("renders Real OCR label when OFF and Mock OCR when ON", () => {
    const off = mount(BaseToggleSwitch, { props: { modelValue: false } });
    expect(off.text()).toMatch(/Real OCR/i);

    const on = mount(BaseToggleSwitch, { props: { modelValue: true } });
    expect(on.text()).toMatch(/Mock OCR/i);
  });

  it("emits update:modelValue with flipped value on click and has switch role", async () => {
    const wrapper = mount(BaseToggleSwitch, { props: { modelValue: false } });
    const btn = wrapper.find('[role="switch"]');
    expect(btn.exists()).toBe(true);
    expect(btn.attributes("aria-checked")).toBe("false");

    await btn.trigger("click");
    expect(wrapper.emitted("update:modelValue")).toBeTruthy();
    expect(wrapper.emitted("update:modelValue")[0]).toEqual([true]);
  });

  it("does not emit when disabled", async () => {
    const wrapper = mount(BaseToggleSwitch, {
      props: { modelValue: false, disabled: true },
    });
    await wrapper.find('[role="switch"]').trigger("click");
    expect(wrapper.emitted("update:modelValue")).toBeFalsy();
  });
});
