import { describe, expect, it } from "vitest";
import { canEditReceipt, EDIT_FORBIDDEN_STATUSES } from "./receiptUtils";

describe("canEditReceipt (My Expense edit gating)", () => {
  const forbidden = [
    "approved",
    "pending",
    "pending-admin-re-review",
    "final-rejected",
  ];
  const editable = [
    "processing",
    "processed",
    "flagged",
    "rejected",
    "failed",
    "automatic-rejected",
  ];

  it("blocks the four forbidden statuses", () => {
    for (const status of forbidden) {
      expect(canEditReceipt({ status })).toBe(false);
    }
  });

  it("allows processing, processed, rejected, failed, and other non-forbidden statuses", () => {
    for (const status of editable) {
      expect(canEditReceipt({ status })).toBe(true);
    }
  });

  it("is case-insensitive", () => {
    expect(canEditReceipt({ status: "APPROVED" })).toBe(false);
    expect(canEditReceipt({ status: "Processing" })).toBe(true);
    expect(canEditReceipt({ status: "PENDING-ADMIN-RE-REVIEW" })).toBe(false);
  });

  it("falls back to complianceStatus when status is missing", () => {
    expect(canEditReceipt({ complianceStatus: "pending" })).toBe(false);
    expect(canEditReceipt({ complianceStatus: "processed" })).toBe(true);
    expect(canEditReceipt({ complianceStatus: "final-rejected" })).toBe(false);
  });

  it("returns false for null/undefined/empty receipt", () => {
    expect(canEditReceipt(null)).toBe(false);
    expect(canEditReceipt(undefined)).toBe(false);
    expect(canEditReceipt({})).toBe(false);
  });

  it("exposes the exact forbidden-status list", () => {
    expect(EDIT_FORBIDDEN_STATUSES).toEqual([
      "approved",
      "pending",
      "pending-admin-re-review",
      "final-rejected",
    ]);
  });
});
