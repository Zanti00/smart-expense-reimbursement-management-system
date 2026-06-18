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
