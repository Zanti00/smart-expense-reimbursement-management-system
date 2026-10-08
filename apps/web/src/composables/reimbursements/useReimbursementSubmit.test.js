// @vitest-environment happy-dom
import { describe, it, expect, vi, beforeEach } from "vitest";
import { useReimbursementSubmit } from "./useReimbursementSubmit";

const submitMock = vi.fn().mockResolvedValue({ id: 1 });
const updateRequestMock = vi.fn().mockResolvedValue({ id: 1 });
const addToastMock = vi.fn();

vi.mock("@/stores/reimbursement", () => ({
  useReimbursementStore: () => ({
    submit: submitMock,
    updateRequest: updateRequestMock,
  }),
}));

vi.mock("@/composables/useToast", () => ({
  useToast: () => ({
    addToast: addToastMock,
  }),
}));

describe("useReimbursementSubmit — userComment handling", () => {
  const emit = vi.fn();
  const router = { push: vi.fn() };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  const validReceipt = {
    id: 10,
    fileName: "receipt.png",
    merchantName: "Vendor Inc",
    categoryId: 2,
    date: "2026-10-01",
    amount: 250,
    tax: 30,
    items: [],
  };

  it("appends user_comment when submitting a reimbursement with a comment", async () => {
    const { submitReimbursement } = useReimbursementSubmit(emit, router);

    const reportFile = new File(["dummy"], "report.pdf", { type: "application/pdf" });
    const success = await submitReimbursement({
      receipts: [validReceipt],
      cutoffPeriod: "2026-10-01 - 2026-10-15",
      reportFile,
      totalAmount: 250,
      userComment: "Optional note for finance.",
    });

    expect(success).toBe(true);
    expect(submitMock).toHaveBeenCalledTimes(1);

    const sentFormData = submitMock.mock.calls[0][0];
    expect(sentFormData.get("user_comment")).toBe("Optional note for finance.");
    expect(sentFormData.get("description")).toBe("Vendor Inc");
  });

  it("omits user_comment or does not append when userComment is not provided", async () => {
    const { submitReimbursement } = useReimbursementSubmit(emit, router);

    const reportFile = new File(["dummy"], "report.pdf", { type: "application/pdf" });
    const success = await submitReimbursement({
      receipts: [validReceipt],
      cutoffPeriod: "2026-10-01 - 2026-10-15",
      reportFile,
      totalAmount: 250,
    });

    expect(success).toBe(true);
    expect(submitMock).toHaveBeenCalledTimes(1);

    const sentFormData = submitMock.mock.calls[0][0];
    expect(sentFormData.get("user_comment")).toBeNull();
  });

  it("appends user_comment when updating a reimbursement", async () => {
    const { updateReimbursement } = useReimbursementSubmit(emit, router);

    const success = await updateReimbursement(1, {
      receipts: [validReceipt],
      cutoffPeriod: "2026-10-01 - 2026-10-15",
      reportFile: null,
      totalAmount: 250,
      userComment: "Updated rationale.",
    });

    expect(success).toBe(true);
    expect(updateRequestMock).toHaveBeenCalledTimes(1);

    const sentFormData = updateRequestMock.mock.calls[0][1];
    expect(sentFormData.get("user_comment")).toBe("Updated rationale.");
  });
});
