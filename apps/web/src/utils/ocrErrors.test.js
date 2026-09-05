import { describe, expect, it } from "vitest";
import { isOcrOfflineFailure } from "@/utils/ocrErrors";

describe("isOcrOfflineFailure", () => {
  it("treats ocr_failed rejection code as offline failure", () => {
    expect(isOcrOfflineFailure({ rejectionCode: "ocr_failed" })).toBe(true);
  });

  it("treats failed status as offline failure", () => {
    expect(isOcrOfflineFailure({ status: "failed" })).toBe(true);
  });

  it("treats 'OCR could not be started' reason as offline failure", () => {
    expect(
      isOcrOfflineFailure({
        rejectionCode: "ocr_failed",
        rejectionReason: "OCR could not be started. Please retry OCR.",
      }),
    ).toBe(true);
  });

  it("does NOT treat quality rejections as offline failures", () => {
    expect(isOcrOfflineFailure({ rejectionCode: "blurry" })).toBe(false);
    expect(isOcrOfflineFailure({ rejectionCode: "too_dark" })).toBe(false);
    expect(isOcrOfflineFailure({ rejectionCode: "too_small" })).toBe(false);
    expect(
      isOcrOfflineFailure({
        rejectionCode: "blurry",
        rejectionReason: "Receipt image quality is too low for accurate OCR data extraction.",
      }),
    ).toBe(false);
  });

  it("does NOT treat duplicate rejections as offline failures", () => {
    expect(isOcrOfflineFailure({ rejectionCode: "duplicate" })).toBe(false);
  });
});
