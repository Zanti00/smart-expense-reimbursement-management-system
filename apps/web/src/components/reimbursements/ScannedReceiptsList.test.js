// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import ScannedReceiptsList from "./ScannedReceiptsList.vue";

describe("ScannedReceiptsList — Required Field Highlighting", () => {
  const baseReceipt = {
    id: "r-1",
    fileName: "receipt.jpg",
    invoiceNumber: "",
    date: "",
    tin: "",
    merchantName: "",
    location: "",
    categoryId: null,
    amount: "",
    subtotal: "",
    tax: "",
    vatClassification: "vat",
    currency: "PHP",
    items: [],
    isUploading: false,
    isProcessing: false,
    hasOcrReturned: false,
  };

  it("does not highlight missing fields with red border before OCR returns", () => {
    const wrapper = mount(ScannedReceiptsList, {
      props: {
        receipts: [{ ...baseReceipt, hasOcrReturned: false }],
        categories: [{ id: 1, name: "Meals" }],
      },
    });

    const invoiceInput = wrapper.find("input[type='text']");
    expect(invoiceInput.classes()).not.toContain("input-error");
  });

  it("highlights missing required fields with red border (input-error) after OCR returns", () => {
    const wrapper = mount(ScannedReceiptsList, {
      props: {
        receipts: [{ ...baseReceipt, hasOcrReturned: true }],
        categories: [{ id: 1, name: "Meals" }],
      },
    });

    // Inputs with missing values should receive error styling
    const errorInputs = wrapper.findAll(".input-error");
    expect(errorInputs.length).toBeGreaterThan(0);
  });

  it("removes red highlight when user enters a value into a missing field", async () => {
    const receipt = { ...baseReceipt, hasOcrReturned: true, invoiceNumber: "" };
    const wrapper = mount(ScannedReceiptsList, {
      props: {
        receipts: [receipt],
        categories: [{ id: 1, name: "Meals" }],
      },
    });

    const invoiceInput = wrapper.find("input[type='text']");
    expect(invoiceInput.classes()).toContain("input-error");

    // Enter value
    await invoiceInput.setValue("INV-9999");

    expect(invoiceInput.classes()).not.toContain("input-error");
  });
});
