const moneyFormatters = new Map()

export function formatMoney(amount, currency = 'EUR') {
  if (!moneyFormatters.has(currency)) {
    moneyFormatters.set(currency, new Intl.NumberFormat(undefined, { style: 'currency', currency }))
  }
  return moneyFormatters.get(currency).format(Number(amount) || 0)
}

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' })

export function formatDateTime(isoString) {
  return isoString ? dateTimeFormatter.format(new Date(isoString)) : ''
}

/** "1 product" / "3 products" */
export function pluralize(count, singular, plural = `${singular}s`) {
  return `${count} ${count === 1 ? singular : plural}`
}

/**
 * Sum line totals in integer cents — the same reason the backend does:
 * 0.1 + 0.2 !== 0.3 in floating point.
 */
export function sumMoney(lines) {
  const cents = lines.reduce((total, { price, quantity }) => total + Math.round(price * 100) * quantity, 0)
  return cents / 100
}
