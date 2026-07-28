/** Format a monetary amount using the Philippine Peso (₱) locale. */
export function formatPeso(value) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(value);
}

/**
 * Format a monetary amount with an explicit ISO 4217 currency code.
 *
 * @param {number} value - The amount to format.
 * @param {string} [currencyCode="PHP"] - An ISO 4217 currency code (e.g. "USD", "JPY").
 * @returns {string} A locale-formatted string with the correct currency symbol.
 *
 * @example
 * formatAmount(150.00, "USD")  // → "$150.00"
 * formatAmount(12000, "JPY")   // → "¥12,000"
 * formatAmount(500.00)         // → "₱500.00"
 */
export function formatAmount(value, currencyCode = "PHP") {
  try {
    return new Intl.NumberFormat("en", {
      style: "currency",
      currency: currencyCode || "PHP",
    }).format(value ?? 0);
  } catch {
    // Fallback if an unsupported code is somehow passed (defensive).
    return formatPeso(value);
  }
}

export function getInitials(name) {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
}

export function formatDate(dateStr, options = { year: "numeric", month: "short", day: "numeric" }) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  if (isNaN(d)) return dateStr;
  return d.toLocaleDateString("en-US", options);
}
