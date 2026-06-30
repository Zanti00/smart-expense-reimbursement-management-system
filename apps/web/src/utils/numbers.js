export function numberOrZero(value) {
  const amount = Number(value);
  return Number.isFinite(amount) ? amount : 0;
}
