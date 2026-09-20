const moneyFormatters = new Map()

export function formatMoney(amount, currency = 'EUR') {
  if (!moneyFormatters.has(currency)) {
    moneyFormatters.set(currency, new Intl.NumberFormat(undefined, { style: 'currency', currency }))
  }
  return moneyFormatters.get(currency).format(Number(amount) || 0)
}

/** Drops ".00" for headline copy: "€20" rather than "€20.00". */
export function formatMoneyShort(amount, currency = 'EUR') {
  const value = Number(amount) || 0
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
    minimumFractionDigits: Number.isInteger(value) ? 0 : 2,
  }).format(value)
}

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' })

export function formatDateTime(isoString) {
  return isoString ? dateTimeFormatter.format(new Date(isoString)) : ''
}

const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' })

/** Date without the time, for compact table cells. */
export function formatDate(isoString) {
  return isoString ? dateFormatter.format(new Date(isoString)) : ''
}

const dayMonthFormatter = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' })

/** "20 Sep" — for chart axes and daily rows, where the year is noise. */
export function formatDayMonth(isoDate) {
  // A bare "YYYY-MM-DD" is parsed as UTC; adding the time keeps it local, so a
  // day never renders as the one before it.
  return isoDate ? dayMonthFormatter.format(new Date(`${isoDate}T00:00:00`)) : ''
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
