import { describe, it, expect } from "vitest";
import { formatPeso, formatAmount, formatKpiValue, formatDate, formatCutoffPeriod } from "./formatters";

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

  describe("formatDate", () => {
    it("formats ISO and valid date strings to Medium Date format", () => {
      expect(formatDate("2026-09-01")).toBe("Sep 1, 2026");
      expect(formatDate("2026-01-15T10:30:00Z")).toMatch(/Jan 15, 2026/);
      expect(formatDate("2026-10-14")).toBe("Oct 14, 2026");
    });

    it("handles null, undefined and empty values gracefully", () => {
      expect(formatDate(null)).toBe("—");
      expect(formatDate(undefined)).toBe("—");
      expect(formatDate("")).toBe("—");
    });

    it("returns raw string if not a valid parseable date", () => {
      expect(formatDate("Invalid Date String")).toBe("Invalid Date String");
    });
  });

  describe("formatCutoffPeriod", () => {
    it("formats YYYY-M and YYYY-MM strings correctly", () => {
      expect(formatCutoffPeriod("2026-7")).toBe("Jul 2026");
      expect(formatCutoffPeriod("2026-07")).toBe("Jul 2026");
      expect(formatCutoffPeriod("2026-8")).toBe("Aug 2026");
      expect(formatCutoffPeriod("2026-08")).toBe("Aug 2026");
      expect(formatCutoffPeriod("2026-1")).toBe("Jan 2026");
      expect(formatCutoffPeriod("2026-12")).toBe("Dec 2026");
    });

    it("formats cutoff period half/day suffixes", () => {
      expect(formatCutoffPeriod("2026-06-A")).toBe("Jun 1 - 15, 2026");
      expect(formatCutoffPeriod("2026-06-B")).toBe("Jun 16 - 30, 2026");
      expect(formatCutoffPeriod("2026-07-B")).toBe("Jul 16 - 31, 2026");
      expect(formatCutoffPeriod("2026-02-B")).toBe("Feb 16 - 28, 2026");
    });

    it("preserves already human-readable cutoff period strings", () => {
      expect(formatCutoffPeriod("Jan 01 - Jan 15, 2025")).toBe("Jan 01 - Jan 15, 2025");
      expect(formatCutoffPeriod("June 1-15, 2026")).toBe("June 1-15, 2026");
    });

    it("handles null, undefined, and empty string gracefully", () => {
      expect(formatCutoffPeriod(null)).toBe("—");
      expect(formatCutoffPeriod(undefined)).toBe("—");
      expect(formatCutoffPeriod("")).toBe("—");
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
