import { describe, expect, it } from "vitest";
import { buildNavLinks } from "./useNavLinks";

describe("buildNavLinks", () => {
  it("includes My Expense for every authenticated user", () => {
    const links = buildNavLinks();
    expect(links.some((l) => l.name === "My Expense" && l.to === "/my-expense")).toBe(true);
  });

  it("includes the base operator links first", () => {
    const links = buildNavLinks();
    expect(links[0].header).toBe("OPERATOR");
    expect(links.map((l) => l.name).filter(Boolean)).toEqual([
      "Dashboard",
      "Reimbursements",
      "Cash Advances",
      "Liquidations",
      "My Expense",
    ]);
  });
});
