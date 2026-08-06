import {
  cleanName,
  getItems,
  normalizeVatClassification,
  receiptFinancials,
  tinFor,
} from "@/utils/receiptUtils";

export function canForwardToReimbursement(receipt) {
  return normalizeReceiptStatus(receipt?.status) === "processed" && !receipt?.isReimbursed;
}

export function getForwardingBlockReason(receipts) {
  const list = Array.isArray(receipts) ? receipts : [receipts];

  if (!list.length) {
    return "Select at least one receipt to forward.";
  }

  if (list.some((receipt) => !canForwardToReimbursement(receipt))) {
    return "Only processed receipts that are not already attached to a reimbursement can be forwarded.";
  }

  if (list.some((receipt) => !receipt?.dbId)) {
    return "Receipt database id is missing. Please refresh My Expense and try again.";
  }

  return "";
}

export function mapReceiptToReimbursement(receipt) {
  const vatClassification = normalizeVatClassification(receipt.vatClassification);
  const items = normalizeItems(receipt);
  const amounts = receiptFinancials(
    {
      amount: Number(receipt.amount) || 0,
      total_amount: Number(receipt.amount) || 0,
      vatAmount: Number(receipt.vatAmount) || 0,
      vat_amount: Number(receipt.vatAmount) || 0,
      items,
    },
    vatClassification,
  );

  return {
    sourceExpenseId: receipt.id,
    id: receipt.dbId,
    fileName: receipt.fileName || receipt.vendorName || `Receipt-${receipt.dbId}`,
    fileType: receipt.fileType,
    merchantName: receipt.vendorName || cleanName(receipt.fileName || ""),
    date: receipt.date,
    amount: amounts.gross,
    subtotal: amounts.subtotal.toFixed(2),
    tax: amounts.vat.toFixed(2),
    vatClassification: amounts.vatClassification,
    tin: receipt.tin || tinFor({ id: receipt.dbId || receipt.id }),
    invoiceNumber: receipt.invoiceNumber || receipt.dbId || receipt.id,
    category: receipt.category || "Uncategorized",
    categoryId: receipt.categoryId || null,
    location: receipt.location || "",
    thumbnail: receipt.thumbnail,
    items,
    isUploading: false,
    isForwardedFromExpense: true,
  };
}

function normalizeReceiptStatus(status) {
  return String(status || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "-");
}

function normalizeItems(receipt) {
  const totalAmount = Number(receipt.amount) || 0;

  if (Array.isArray(receipt.items) && receipt.items.length) {
    const items = receipt.items.map((item) => ({
      name: item.name || "",
      qty: Number(item.qty ?? item.quantity ?? 1),
      price: Number(item.price ?? 0),
    }));

    if (items.every((item) => item.name && item.qty > 0 && item.price > 0)) {
      return items;
    }
  }

  const fallbackName =
    cleanName(receipt.fileName || "") ||
    getItems(receipt.category || "Food & Dining")[0] ||
    "Receipt total";

  return [{
    name: fallbackName,
    qty: 1,
    price: Number(totalAmount.toFixed(2)),
  }];
}
