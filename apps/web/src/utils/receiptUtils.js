export function vatOf(amount) {
  return amount > 0 ? (amount * 0.12) / 1.12 : 0;
}

export function normalizeVatClassification(value) {
  return String(value || "").toLowerCase() === "non-vat" ? "non-vat" : "vat";
}

export function vatFor(amount, vatClassification = "vat") {
  return normalizeVatClassification(vatClassification) === "non-vat"
    ? 0
    : vatOf(amount);
}

export function subtotalOf(amount, vatClassification = "vat") {
  return amount > 0 ? amount - vatFor(amount, vatClassification) : 0;
}

export function itemGrossAmount(item) {
  const quantity = Number(item?.quantity ?? item?.qty ?? 1) || 1;
  const price = Number(item?.price) || 0;

  return quantity * price;
}

export function itemsGrossAmount(items = []) {
  return items.reduce((sum, item) => sum + itemGrossAmount(item), 0);
}

export function receiptGrossAmount(receipt) {
  if (receipt?.items?.length) {
    return itemsGrossAmount(receipt.items);
  }

  return Number(receipt?.amount ?? receipt?.total_amount ?? 0);
}

export function receiptFinancials(receipt, vatClassification) {
  const classification = normalizeVatClassification(
    vatClassification ??
      receipt?.vatClassification ??
      receipt?.vat_classification,
  );
  const gross = receiptGrossAmount(receipt);
  const vat = vatFor(gross, classification);

  return {
    gross,
    vat,
    subtotal: Math.max(gross - vat, 0),
    vatClassification: classification,
  };
}

export function cleanName(fileName) {
  return (fileName || "").replace(/\.[^.]+$/, "").replace(/[_-]/g, " ");
}

export function tinFor(receipt) {
  const seed = String(receipt.id || receipt.fileName || "")
    .replace(/\D/g, "")
    .padEnd(9, "0");
  return `${seed.slice(0, 3)}-${seed.slice(3, 6)}-${seed.slice(6, 9)}-000`;
}

const MOCK_ITEMS = {
  "Food & Dining": ["Chickenjoy 2pc Meal", "Yumburger w/ Cheese", "Iced Tea"],
  Lodging: [
    "1 Night - Deluxe Room",
    "Breakfast Buffet (x2)",
    "Airport Transfer",
  ],
  Transportation: [
    "GrabCar Ride - Terminal to CBD",
    "Toll Fee - SLEX",
    "Parking Fee",
  ],
  Meals: ["Set Meal A (x2)", "Drinks & Dessert", "Service Charge"],
  Supplies: ["Bond Paper (5 reams)", "Ballpens & Markers", "Correction Tape"],
  Uncategorized: ["Item 1", "Item 2"],
};

export function getItems(cat) {
  return MOCK_ITEMS[cat] || MOCK_ITEMS.Uncategorized;
}

function buildDefaultItems(categoryLabel, amount) {
  const itemNames = getItems(categoryLabel || "Expense");
  const splitPrice = itemNames.length > 0 ? Number(amount || 0) / itemNames.length : 0;

  return itemNames.map((name) => ({
    name,
    qty: 1,
    price: Number(splitPrice.toFixed(2)),
  }));
}

export function buildPrefilledReceiptDraft({
  id,
  file,
  receiptData = {},
  thumbnail = "",
  defaultLocation = "Metro Manila, Philippines",
} = {}) {
  const resolvedId = receiptData.id || id || `temp-${Date.now()}`;
  const fileName = file?.name || receiptData.file_name || `Receipt-${resolvedId}`;
  const fileType = file?.type || receiptData.file_type || "";
  const merchantName = receiptData.vendor_name || cleanName(fileName);
  const vatClassification = normalizeVatClassification(
    receiptData.vat_classification,
  );
  const amount = Number(receiptData.total_amount) || 0;
  const categoryName = receiptData.category?.name || "";
  const items =
    Array.isArray(receiptData.items) && receiptData.items.length > 0
      ? receiptData.items.map((item) => ({
          name: item.name || "Item",
          qty: Number(item.quantity ?? item.qty) || 1,
          price: Number(item.price) || 0,
        }))
      : buildDefaultItems(categoryName || "Expense", amount);
  const amounts = receiptFinancials({ amount, items }, vatClassification);

  return {
    id: resolvedId,
    invoiceNumber:
      receiptData.invoice_number || `INV-${String(resolvedId).replace(/\D/g, "").slice(-6) || "000000"}`,
    tin: receiptData.tin || tinFor({ id: resolvedId, fileName }),
    merchantName,
    location: defaultLocation,
    fileName,
    fileType,
    thumbnail,
    amount: amounts.gross,
    subtotal: amounts.subtotal.toFixed(2),
    tax:
      receiptData.vat_amount != null && receiptData.vat_amount !== ""
        ? Number(receiptData.vat_amount).toFixed(2)
        : amounts.vat.toFixed(2),
    vatClassification: amounts.vatClassification,
    date:
      receiptData.transaction_date || new Date().toISOString().slice(0, 10),
    category: categoryName,
    categoryId: receiptData.expense_category_id || null,
    items,
    isUploading: false,
    sourceFile: file || null,
  };
}

export function buildReceiptUploadFormPrefill(options = {}) {
  const receipt = buildPrefilledReceiptDraft(options);

  return {
    invoice_number: receipt.invoiceNumber,
    transaction_date: receipt.date,
    tin: receipt.tin,
    vendor_name: receipt.merchantName,
    expense_category_id: receipt.categoryId || "",
    total_amount: receipt.amount > 0 ? Number(receipt.amount.toFixed(2)) : "",
    vat_amount: receipt.tax,
    vat_classification: receipt.vatClassification,
    items: (receipt.items || []).map((item) => ({
      name: item.name,
      quantity: Number(item.qty ?? item.quantity) || 1,
      price: Number(item.price) || 0,
    })),
  };
}
