// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import MetaAndAttachments from "./MetaAndAttachments.vue";

describe("MetaAndAttachments", () => {
  it("renders optional comments textarea and relays update:userComment on input", async () => {
    const wrapper = mount(MetaAndAttachments, {
      props: {
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

  it("renders report attachment card and comments card with appropriate stacking contexts", () => {
    const wrapper = mount(MetaAndAttachments, {
      props: {
        reportFile: null,
      },
    });

    const cards = wrapper.findAll("section.card");
    expect(cards.length).toBe(2);

    // Report section (first card) must have z-20
    expect(cards[0].classes()).toContain("z-20");

    // Comments section (second card) must have z-10
    expect(cards[1].classes()).toContain("z-10");
  });

  it("renders attached report file details and allows removal", async () => {
    const fakeFile = new File(["dummy content"], "activity-report.pdf", {
      type: "application/pdf",
    });

    const wrapper = mount(MetaAndAttachments, {
      props: {
        reportFile: fakeFile,
      },
    });

    expect(wrapper.text()).toContain("activity-report.pdf");

    const removeBtn = wrapper.find("button[aria-label='Remove report attachment']");
    expect(removeBtn.exists()).toBe(true);

    await removeBtn.trigger("click");
    expect(wrapper.emitted("update:reportFile")).toBeTruthy();
    expect(wrapper.emitted("update:reportFile")[0]).toEqual([null]);
  });
});
