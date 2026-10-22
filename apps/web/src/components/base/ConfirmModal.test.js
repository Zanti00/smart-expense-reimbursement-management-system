// @vitest-environment happy-dom
import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import ConfirmModal from "./ConfirmModal.vue";
import DeleteConfirmModal from "./DeleteConfirmModal.vue";
import ReceiptViewModal from "@/components/expenses/ReceiptViewModal.vue";

describe("Confirmation Modals — Centered & Intrinsic Button Sizing Guardrails", () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it("ConfirmModal centers action buttons with px-5 and no flex-1", () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: "Confirm Action",
        message: "Are you sure you want to proceed?",
      },
      global: {
        stubs: {
          BaseModal: {
            template: `<div><slot /></div>`,
          },
        },
      },
    });

    const buttons = wrapper.findAllComponents({ name: "BaseButton" });
    expect(buttons.length).toBe(2);

    buttons.forEach((btn) => {
      expect(btn.classes()).not.toContain("flex-1");
      expect(btn.classes()).toContain("px-5");
    });

    const footer = buttons[0].element.parentElement;
    expect(footer.classList.contains("justify-center")).toBe(true);
  });

  it("DeleteConfirmModal centers action buttons with px-5 and no flex-1", () => {
    const wrapper = mount(DeleteConfirmModal, {
      props: {
        modelValue: true,
        title: "Delete Item",
        message: "This cannot be undone.",
      },
      global: {
        stubs: {
          BaseModal: {
            template: `<div><slot /></div>`,
          },
        },
      },
    });

    const buttons = wrapper.findAllComponents({ name: "BaseButton" });
    expect(buttons.length).toBe(2);

    buttons.forEach((btn) => {
      expect(btn.classes()).not.toContain("flex-1");
      expect(btn.classes()).toContain("px-5");
    });

    const footer = buttons[0].element.parentElement;
    expect(footer.classList.contains("justify-center")).toBe(true);
  });

  it("ReceiptViewModal renders actions without flex-1 and with justify-end", () => {
    const wrapper = mount(ReceiptViewModal, {
      props: {
        modelValue: true,
        receipt: {
          id: 10,
          status: "processed",
          vendorName: "Grab",
          amount: 300,
        },
      },
      global: {
        stubs: {
          BaseReceiptDetailModal: {
            template: `<div><slot name="actions" /></div>`,
          },
        },
      },
    });

    const editBtn = wrapper.findAll("button").find((b) => b.text().includes("Edit"));
    expect(editBtn).toBeDefined();
    expect(editBtn.classes()).not.toContain("flex-1");
    expect(editBtn.classes()).toContain("px-4");

    const container = editBtn.element.parentElement;
    expect(container.classList.contains("justify-end")).toBe(true);
  });
});
