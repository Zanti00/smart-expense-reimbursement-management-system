// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import UnifiedRoadmapStepper from "./UnifiedRoadmapStepper.vue";

describe("UnifiedRoadmapStepper", () => {
  const baseCashAdvance = {
    id: 1,
    status: "disbursed",
    amount: 5000,
    requestedBy: "General Employee",
    date: "2026-08-20",
  };

  it("extracts and formats actor name when actor in statusHistory is an object instead of stringifying it", () => {
    const statusHistory = [
      {
        id: 1,
        status: "pending",
        changed_at: "2026-08-20T08:00:00Z",
        actor: {
          id: 106,
          auth_id: 7,
          name: "General Employee",
          email: "employee@example.com",
          role: "employee",
        },
      },
      {
        id: 2,
        status: "approved",
        changed_at: "2026-08-21T09:30:00Z",
        changed_by: {
          id: 102,
          name: "Finance Manager",
          email: "approver@example.com",
        },
      },
    ];

    const wrapper = mount(UnifiedRoadmapStepper, {
      props: {
        cashAdvance: baseCashAdvance,
        statusHistory,
      },
    });

    const renderedText = wrapper.text();

    // Verify actor names appear as human-readable strings
    expect(renderedText).toContain("General Employee");
    expect(renderedText).toContain("Finance Manager");

    // Verify raw JSON object strings are NOT leaked into the rendered output
    expect(renderedText).not.toContain('{"id":');
    expect(renderedText).not.toContain('"auth_id":');
    expect(renderedText).not.toContain('"role":');
    expect(renderedText).not.toContain("[object Object]");
  });

  it("handles string actor and JSON-stringified actor in statusHistory", () => {
    const statusHistory = [
      {
        id: 1,
        status: "pending",
        changed_at: "2026-08-20T08:00:00Z",
        actor: "Jane Doe",
      },
      {
        id: 2,
        status: "approved",
        changed_at: "2026-08-21T09:30:00Z",
        actor: JSON.stringify({ name: "John Admin", role: "admin" }),
      },
    ];

    const wrapper = mount(UnifiedRoadmapStepper, {
      props: {
        cashAdvance: baseCashAdvance,
        statusHistory,
      },
    });

    const renderedText = wrapper.text();
    expect(renderedText).toContain("Jane Doe");
    expect(renderedText).toContain("John Admin");
    expect(renderedText).not.toContain('"role":');
  });

  it("renders horizontal 1-row layout by default (for cash advance pages and modals)", () => {
    const wrapper = mount(UnifiedRoadmapStepper, {
      props: {
        cashAdvance: baseCashAdvance,
        statusHistory: [],
      },
    });

    const desktopLines = wrapper.findAll(".sm\\:block.h-0\\.5");
    expect(desktopLines.length).toBe(1);

    expect(desktopLines[0].classes()).toContain("top-4");
    expect(desktopLines[0].classes()).toContain("-translate-y-1/2");

    const styleAttr = desktopLines[0].attributes("style");
    // For 8 steps horizontal, offset is 100 / (2 * 8) = 6.25%
    expect(styleAttr).toContain("6.25%");
  });

  it("renders 4x2 serpentine (reverted S) layout when layout='serpentine' (for liquidation settlement form)", () => {
    const wrapper = mount(UnifiedRoadmapStepper, {
      props: {
        cashAdvance: baseCashAdvance,
        layout: "serpentine",
        statusHistory: [],
      },
    });

    const text = wrapper.text();
    expect(text).toContain("Phase 1: Cash Advance");
    expect(text).toContain("Phase 2: Liquidation & Settlement");

    // Check connector lines for row 1 and row 2
    const lines = wrapper.findAll(".sm\\:block.h-0\\.5");
    expect(lines.length).toBe(2);

    lines.forEach((line) => {
      expect(line.classes()).toContain("top-4");
      expect(line.classes()).toContain("-translate-y-1/2");
      const styleAttr = line.attributes("style");
      expect(styleAttr).toContain("12.5%");
    });
  });
});
