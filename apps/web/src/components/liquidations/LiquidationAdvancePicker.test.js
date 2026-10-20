// @vitest-environment happy-dom
import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import LiquidationAdvancePicker from "./LiquidationAdvancePicker.vue";
import { formatPeso } from "@/utils/formatters";

const advances = [
  {
    id: "ca-1",
    purpose: "Own Advance One",
    balance: 600,
    status: "signed",
    dueDate: "2026-10-08",
  },
  {
    id: "ca-2",
    purpose: "Own Advance Two",
    balance: 2500,
    status: "signed",
    dueDate: "2026-10-09",
  },
];

const getBadgeStatus = (advance) => advance.status;

describe("LiquidationAdvancePicker", () => {
  it("renders one selectable card per eligible advance with its key details", () => {
    const wrapper = mount(LiquidationAdvancePicker, {
      props: { advances, getBadgeStatus },
    });

    const cards = wrapper.findAll(
      '[data-testid="liquidation-advance-card"]',
    );
    expect(cards).toHaveLength(2);

    expect(cards[0].text()).toContain("Own Advance One");
    expect(cards[0].text()).toContain("Ref #ca-1");
    expect(cards[0].text()).toContain(formatPeso(600));
    expect(cards[0].text()).toContain("Oct 8, 2026");
    expect(cards[0].text()).toContain("Signed");
    expect(cards[1].text()).toContain("Own Advance Two");
    expect(cards[1].text()).toContain(formatPeso(2500));
  });

  it("emits select with the clicked advance", async () => {
    const wrapper = mount(LiquidationAdvancePicker, {
      props: { advances, getBadgeStatus },
    });

    await wrapper
      .find('[data-testid="liquidation-advance-card"][data-advance-id="ca-2"]')
      .trigger("click");

    expect(wrapper.emitted("select")).toHaveLength(1);
    expect(wrapper.emitted("select")[0][0]).toEqual(advances[1]);
  });

  it("shows an empty state when no advances are available", () => {
    const wrapper = mount(LiquidationAdvancePicker, {
      props: { advances: [], getBadgeStatus },
    });

    expect(wrapper.text()).toContain("No cash advances are ready for liquidation.");
    expect(
      wrapper.findAll('[data-testid="liquidation-advance-card"]'),
    ).toHaveLength(0);
  });
});
