// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import BaseReceiptImage from "./BaseReceiptImage.vue";

describe("BaseReceiptImage", () => {
  it("renders an img tag when a valid image src is provided", () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: "https://example.com/receipt.jpg",
        alt: "Valid Receipt",
      },
    });

    const img = wrapper.find("img");
    expect(img.exists()).toBe(true);
    expect(img.attributes("src")).toBe("https://example.com/receipt.jpg");
    expect(img.attributes("alt")).toBe("Valid Receipt");
  });

  it("renders the placeholder when src is null or empty", () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: null,
      },
    });

    expect(wrapper.find("img").exists()).toBe(false);
    expect(wrapper.text()).toContain("No Preview");
  });

  it("switches to placeholder when img triggers an error event", async () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: "https://example.com/non-existent-image.jpg",
        alt: "Broken Receipt",
      },
    });

    expect(wrapper.find("img").exists()).toBe(true);

    // Trigger error on img
    await wrapper.find("img").trigger("error");

    expect(wrapper.find("img").exists()).toBe(false);
    expect(wrapper.text()).toContain("No Preview");
    expect(wrapper.emitted("error")).toBeTruthy();
  });

  it("resets error state when src prop updates", async () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: "https://example.com/broken.jpg",
      },
    });

    // Trigger error
    await wrapper.find("img").trigger("error");
    expect(wrapper.find("img").exists()).toBe(false);

    // Update src prop to new url
    await wrapper.setProps({ src: "https://example.com/new-valid.jpg" });

    // Should attempt to render img again
    const img = wrapper.find("img");
    expect(img.exists()).toBe(true);
    expect(img.attributes("src")).toBe("https://example.com/new-valid.jpg");
  });

  it("renders PDF placeholder when fileType is application/pdf", () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: "https://example.com/document.pdf",
        fileType: "application/pdf",
      },
    });

    expect(wrapper.find("img").exists()).toBe(false);
    expect(wrapper.text()).toContain("No Preview");
  });

  it("renders custom noPreviewText if provided", () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: null,
        noPreviewText: "Custom Placeholder",
      },
    });

    expect(wrapper.text()).toContain("Custom Placeholder");
  });

  it("allows overriding placeholder via slot", () => {
    const wrapper = mount(BaseReceiptImage, {
      props: {
        src: null,
      },
      slots: {
        placeholder: "<div class='custom-slot'>Custom Slot Content</div>",
      },
    });

    expect(wrapper.find(".custom-slot").exists()).toBe(true);
    expect(wrapper.text()).toContain("Custom Slot Content");
  });
});
