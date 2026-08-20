/** Format a monetary amount using the Philippine Peso (₱) locale with 2 decimals. */
export function formatPeso(value) {
  const num = typeof value === "number" ? value : parseFloat(value) || 0;
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num);
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
 * formatAmount(12000, "JPY")   // → "¥12,000.00"
 * formatAmount(500.00)         // → "₱500.00"
 */
export function formatAmount(value, currencyCode = "PHP") {
  try {
    const num = typeof value === "number" ? value : parseFloat(value) || 0;
    return new Intl.NumberFormat("en", {
      style: "currency",
      currency: currencyCode || "PHP",
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(num);
  } catch {
    // Fallback if an unsupported code is somehow passed (defensive).
    return formatPeso(value);
  }
}

/**
 * Format a KPI value to display at most 2 decimal digits for monetary or decimal numbers (e.g. 1,200.12 or ₱1,200.12).
 *
 * @param {string|number} value - The KPI value to format.
 * @returns {string} Formatted KPI value.
 */
export function formatKpiValue(value) {
  if (value === null || value === undefined) return "—";

  if (typeof value === "number") {
    if (Number.isInteger(value)) {
      return value.toLocaleString();
    }
    return value.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  if (typeof value === "string") {
    const trimmed = value.trim();

    // Match currency-prefixed strings like ₱1200.1, $500, ₱44,750, ₱03200.006500
    const currencyMatch = trimmed.match(/^([^\d\-+]+)([-+]?[\d,]+(?:\.\d+)?)$/);
    if (currencyMatch) {
      const prefix = currencyMatch[1];
      const rawNum = parseFloat(currencyMatch[2].replace(/,/g, ""));
      if (!isNaN(rawNum)) {
        return `${prefix}${rawNum.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}`;
      }
    }

    // Match raw decimal numbers formatted as strings like "1200.1234"
    const numOnlyMatch = trimmed.match(/^([-+]?[\d,]+(?:\.\d+)?)$/);
    if (numOnlyMatch) {
      const rawNum = parseFloat(numOnlyMatch[1].replace(/,/g, ""));
      if (!isNaN(rawNum)) {
        if (Number.isInteger(rawNum) && !trimmed.includes(".")) {
          return rawNum.toLocaleString();
        }
        return rawNum.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      }
    }
  }

  return String(value);
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

export const SUPPORTED_CURRENCIES = [
  { code: "PHP", symbol: "₱", name: "Philippine Peso" },
  { code: "USD", symbol: "$", name: "US Dollar" },
  { code: "SGD", symbol: "S$", name: "Singapore Dollar" },
  { code: "MYR", symbol: "RM", name: "Malaysian Ringgit" },
  { code: "BND", symbol: "B$", name: "Brunei Dollar" },
  { code: "JPY", symbol: "¥", name: "Japanese Yen" },
  { code: "HKD", symbol: "HK$", name: "Hong Kong Dollar" },
  { code: "THB", symbol: "฿", name: "Thai Baht" },
  { code: "AUD", symbol: "A$", name: "Australian Dollar" },
  { code: "GBP", symbol: "£", name: "British Pound" },
  { code: "EUR", symbol: "€", name: "Euro" },
];

/**
 * Get currency symbol for a 3-letter currency code using Intl or fallback list.
 * @param {string} currencyCode
 * @returns {string}
 */
export function getCurrencySymbol(currencyCode = "PHP") {
  if (!currencyCode || typeof currencyCode !== "string") return "";
  const cleaned = currencyCode.trim().toUpperCase();
  if (cleaned.length !== 3) return "";

  const known = SUPPORTED_CURRENCIES.find((c) => c.code === cleaned);
  if (known) return known.symbol;

  try {
    const parts = new Intl.NumberFormat("en", {
      style: "currency",
      currency: cleaned,
      currencyDisplay: "narrowSymbol",
    }).formatToParts(0);
    const symbolPart = parts.find((p) => p.type === "currency");
    return symbolPart ? symbolPart.value : cleaned;
  } catch {
    try {
      const parts = new Intl.NumberFormat("en", {
        style: "currency",
        currency: cleaned,
        currencyDisplay: "symbol",
      }).formatToParts(0);
      const symbolPart = parts.find((p) => p.type === "currency");
      return symbolPart ? symbolPart.value : cleaned;
    } catch {
      return cleaned;
    }
  }
}

