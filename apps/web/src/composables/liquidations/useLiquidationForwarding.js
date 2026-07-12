import { useRouter } from "vue-router";

export function useLiquidationForwarding(
  selectedAdvance,
  overpaymentAmount,
  receipts,
  defaultReceiptCategoryId
) {
  const router = useRouter();

  function forwardOverpaymentToReimbursement() {
    if (!selectedAdvance.value || overpaymentAmount.value <= 0) return;

    const forwardedReceipts = receipts.value.map((receipt, index) => ({
      id: `LIQ-${selectedAdvance.value.id}-${index + 1}`,
      fileName:
        receipt.name || receipt.file?.name || `Liquidation Receipt ${index + 1}`,
      fileType: receipt.file?.type || "application/pdf",
      thumbnail: receipt.preview || "",
      amount: Number(receipt.ocrData?.amount ?? receipt.amount ?? 0),
      date: receipt.ocrData?.date || new Date().toISOString().slice(0, 10),
      category: receipt.category || "Other",
      categoryId: receipt.categoryId || null,
      source: "liquidation-receipt",
      cashAdvanceId: selectedAdvance.value.id,
    }));

    const cashAdvanceAmount = Number(selectedAdvance.value.amount || 0);
    if (cashAdvanceAmount > 0) {
      forwardedReceipts.push({
        id: `LIQ-${selectedAdvance.value.id}-deduction`,
        fileName: `Cash Advance Deduction (CA-${selectedAdvance.value.id})`,
        fileType: "application/pdf",
        thumbnail: "",
        amount: -cashAdvanceAmount,
        date: new Date().toISOString().slice(0, 10),
        category: "Other",
        categoryId: defaultReceiptCategoryId.value,
        source: "liquidation-deduction",
        cashAdvanceId: selectedAdvance.value.id,
        vatClassification: "non-vat",
        subtotal: (-cashAdvanceAmount).toFixed(2),
        tax: "0.00",
      });
    }

    sessionStorage.setItem(
      "serms_forwarded_liquidation_receipts",
      JSON.stringify(forwardedReceipts),
    );
    router.push("/reimbursements/new");
  }

  return {
    forwardOverpaymentToReimbursement,
  };
}
