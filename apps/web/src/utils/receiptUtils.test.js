import { describe, expect, it } from "vitest";
import {
  canEditReceipt,
  canDeleteReceipt,
  EDIT_FORBIDDEN_STATUSES,
  DELETE_FORBIDDEN_STATUSES,
} from "./receiptUtils";

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

describe("canDeleteReceipt (delete gating, allows final-rejected)", () => {
  const forbidden = [
    "approved",
    "pending",
    "pending-admin-re-review",
  ];
  const deletable = [
    "processing",
    "processed",
    "flagged",
    "rejected",
    "failed",
    "automatic-rejected",
    "final-rejected",
  ];

  it("blocks the forbidden statuses", () => {
    for (const status of forbidden) {
      expect(canDeleteReceipt({ status })).toBe(false);
    }
  });

  it("allows processing, processed, flagged, rejected, failed, automatic-rejected, and final-rejected", () => {
    for (const status of deletable) {
      expect(canDeleteReceipt({ status })).toBe(true);
    }
  });

  it("allows final-rejected even though edit does not", () => {
    expect(canDeleteReceipt({ status: "final-rejected" })).toBe(true);
    expect(canEditReceipt({ status: "final-rejected" })).toBe(false);
  });

  it("matches canEditReceipt for all statuses except final-rejected", () => {
    const others = [
      ...forbidden,
      "processing",
      "processed",
      "flagged",
      "rejected",
      "failed",
      "automatic-rejected",
    ];
    for (const status of others) {
      expect(canDeleteReceipt({ status })).toBe(canEditReceipt({ status }));
    }
  });

  it("is case-insensitive", () => {
    expect(canDeleteReceipt({ status: "APPROVED" })).toBe(false);
    expect(canDeleteReceipt({ status: "Processing" })).toBe(true);
    expect(canDeleteReceipt({ status: "PENDING-ADMIN-RE-REVIEW" })).toBe(false);
  });

  it("falls back to complianceStatus when status is missing", () => {
    expect(canDeleteReceipt({ complianceStatus: "pending" })).toBe(false);
    expect(canDeleteReceipt({ complianceStatus: "processed" })).toBe(true);
    expect(canDeleteReceipt({ complianceStatus: "final-rejected" })).toBe(true);
  });

  it("returns false for null/undefined/empty receipt", () => {
    expect(canDeleteReceipt(null)).toBe(false);
    expect(canDeleteReceipt(undefined)).toBe(false);
    expect(canDeleteReceipt({})).toBe(false);
  });

  it("exposes the exact delete forbidden-status list", () => {
    expect(DELETE_FORBIDDEN_STATUSES).toEqual([
      "approved",
      "pending",
      "pending-admin-re-review",
    ]);
  });
});
