import { describe, it, expect } from "vitest";
import { formatPeso, formatAmount, formatKpiValue } from "./formatters";

describe("formatters", () => {
  describe("formatPeso", () => {
    it("formats amounts to 2 decimal places with PHP currency symbol", () => {
      expect(formatPeso(1200.12)).toBe("₱1,200.12");
      expect(formatPeso(7510.4)).toBe("₱7,510.40");
      expect(formatPeso(44750)).toBe("₱44,750.00");
      expect(formatPeso(0)).toBe("₱0.00");
    });
  });

  describe("formatAmount", () => {
    it("formats amounts with 2 decimal places for specified currency", () => {
      expect(formatAmount(1200.12, "PHP")).toBe("₱1,200.12");
      expect(formatAmount(150, "USD")).toBe("$150.00");
    });
  });

  describe("formatKpiValue", () => {
    it("formats float numbers to 2 decimal places", () => {
      expect(formatKpiValue(1200.12)).toBe("1,200.12");
      expect(formatKpiValue(1200.1)).toBe("1,200.10");
      expect(formatKpiValue(1200.128)).toBe("1,200.13");
      expect(formatKpiValue(7510.4)).toBe("7,510.40");
    });

    it("formats integer numbers without unnecessary decimal places", () => {
      expect(formatKpiValue(7)).toBe("7");
      expect(formatKpiValue(100)).toBe("100");
      expect(formatKpiValue(0)).toBe("0");
    });

    it("formats currency strings to exactly 2 decimals", () => {
      expect(formatKpiValue("₱7,510.4")).toBe("₱7,510.40");
      expect(formatKpiValue("₱44,750")).toBe("₱44,750.00");
      expect(formatKpiValue("₱1,200.12")).toBe("₱1,200.12");
      expect(formatKpiValue("₱1200.1234")).toBe("₱1,200.12");
      expect(formatKpiValue("$1200.1")).toBe("$1,200.10");
    });

    it("leaves non-numeric strings untouched", () => {
      expect(formatKpiValue("Ready to forward")).toBe("Ready to forward");
      expect(formatKpiValue("—")).toBe("—");
      expect(formatKpiValue(null)).toBe("—");
      expect(formatKpiValue(undefined)).toBe("—");
    });
  });
});
