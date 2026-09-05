/**
 * OCR offline / pipeline failure detection.
 *
 * When the OCR pipeline is offline (AI service unreachable, queue dispatch
 * failed, worker not running) the backend marks receipts with:
 *   - status = "failed"
 *   - rejection_code = "ocr_failed"
 *   - rejection_reason like "OCR could not be started. Please retry OCR."
 *     / "OCR processing could not be started..." / "OCR processing failed..."
 *
 * Those failures must NOT open the ReceiptQualityRejectionModal (which is
 * reserved for genuine image-quality problems: blurry / too_dark /
 * too_small / duplicate). A toast is enough.
 */

/**
 * @param {object} input
 * @param {string} [input.status]
 * @param {string} [input.rejectionCode] camelCase code from frontend state
 * @param {string} [input.rejection_code] snake_case code from backend payload
 * @param {string} [input.rejectionReason] camelCase reason
 * @param {string} [input.rejection_reason] snake_case reason
 * @param {string} [input.message] generic message / error field
 * @param {string} [input.error] generic error field
 * @returns {boolean} true when the failure is OCR-offline related
 */
export function isOcrOfflineFailure(input = {}) {
  const status = String(input.status || "").toLowerCase().trim();
  if (status === "failed") return true;

  const code = String(
    input.rejectionCode ?? input.rejection_code ?? "",
  )
    .toLowerCase()
    .trim();

  // Explicit offline / infra codes — never quality codes.
  if (
    code === "ocr_failed" ||
    code === "ocr_offline" ||
    code === "ocr_unavailable" ||
    code === "ocr_error" ||
    code === "service_unavailable"
  ) {
    return true;
  }

  const reason = String(
    input.rejectionReason ??
      input.rejection_reason ??
      input.message ??
      input.error ??
      "",
  ).toLowerCase();

  if (!reason) return false;

  // Backend copy used when the OCR job could not even be dispatched.
  if (
    reason.includes("could not be started") ||
    reason.includes("could not start") ||
    reason.includes("retry ocr") ||
    reason.includes("ocr processing failed") ||
    reason.includes("dispatch failed") ||
    reason.includes("ai service") ||
    reason.includes("service unreachable") ||
    reason.includes("service unavailable") ||
    reason.includes("ocr offline") ||
    reason.includes("ocr is offline") ||
    reason.includes("offline")
  ) {
    return true;
  }

  return false;
}
