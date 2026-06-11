export function vatOf(amount) {
  return amount > 0 ? (amount * 0.12) / 1.12 : 0;
}

export function subtotalOf(amount) {
  return amount > 0 ? amount - vatOf(amount) : 0;
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
