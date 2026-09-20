import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { downloadOrdersCsv, getAnalytics } from '../../api/admin'
import { ErrorMessage, Loader } from '../../components/Feedback'
import SalesChart from '../../components/SalesChart'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { formatDayMonth, pluralize } from '../../utils/format'

// Must match the `in:` rule on the API's days parameter.
const RANGES = [
  { value: 7, label: 'Last 7 days' },
  { value: 30, label: 'Last 30 days' },
  { value: 90, label: 'Last 90 days' },
  { value: 365, label: 'Last year' },
]

export default function AnalyticsPage() {
  const { money, moneyShort } = useShop()
  const [searchParams, setSearchParams] = useSearchParams()
  const days = RANGES.some((range) => range.value === Number(searchParams.get('days')))
    ? Number(searchParams.get('days'))
    : 30

  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState(null)

  const { data, error, loading, reload } = useApi((options) => getAnalytics({ days }, options), [days])

  const handleExport = async () => {
    setExporting(true)
    setExportError(null)
    try {
      await downloadOrdersCsv({})
    } catch (caught) {
      setExportError(caught)
    } finally {
      setExporting(false)
    }
  }

  if (loading && !data) return <Loader label="Crunching the numbers…" />
  if (error && !data) return <ErrorMessage error={error} onRetry={reload} />

  const { totals, sales_by_day: salesByDay, top_products: topProducts, promo_codes: promoCodes } = data
  const split = data.fulfillment_split
  const splitOrders = split.reduce((sum, row) => sum + row.orders, 0)
  const topQuantity = Math.max(1, ...topProducts.map((product) => product.quantity))

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Analytics</h1>
          <p>
            {formatDayMonth(data.range.from)} – {formatDayMonth(data.range.to)} · cancelled orders excluded
          </p>
        </div>
        <div className="row">
          {/* One filter row above the charts. */}
          <label>
            <span className="visually-hidden">Date range</span>
            <select
              className="input"
              value={days}
              onChange={(event) => setSearchParams({ days: event.target.value })}
            >
              {RANGES.map((range) => (
                <option key={range.value} value={range.value}>
                  {range.label}
                </option>
              ))}
            </select>
          </label>
          <button type="button" className="btn btn-sm btn-secondary" onClick={handleExport} disabled={exporting}>
            {exporting ? 'Preparing…' : '⬇ Export orders CSV'}
          </button>
        </div>
      </div>

      <ErrorMessage error={exportError} />

      <div className="stats-grid">
        <div className="stat-card">
          <span className="stat-label">Sales</span>
          <span className="stat-value">{moneyShort(totals.sales)}</span>
          <span className="stat-sub">{pluralize(totals.orders, 'order')}</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Average order</span>
          <span className="stat-value">{money(totals.average_order_value)}</span>
          <span className="stat-sub">across the period</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Dishes sold</span>
          <span className="stat-value">{totals.items_sold}</span>
          <span className="stat-sub">items across all orders</span>
        </div>
        <div className="stat-card">
          <span className="stat-label">Discounts given</span>
          <span className="stat-value">{moneyShort(totals.discounts)}</span>
          <span className="stat-sub">offers and promo codes</span>
        </div>
        <Link to={`/admin/orders?status=cancelled`} className="stat-card">
          <span className="stat-label">Cancelled</span>
          <span className="stat-value">{totals.cancelled_orders}</span>
          <span className="stat-sub">not counted above</span>
        </Link>
      </div>

      <section className="card">
        <h2>Sales per day</h2>
        <SalesChart days={salesByDay} />
      </section>

      <div className="dashboard-grid">
        <section className="card">
          <h2>Best sellers</h2>
          {topProducts.length === 0 ? (
            <p className="muted">Nothing sold in this period yet.</p>
          ) : (
            <ul className="bar-list">
              {topProducts.map((product) => (
                <li key={product.name}>
                  <div className="bar-row">
                    <span className="bar-label">{product.name}</span>
                    <span className="bar-track">
                      <span
                        className="bar-fill bar-sales"
                        style={{ width: `${(product.quantity / topQuantity) * 100}%` }}
                      />
                    </span>
                    {/* The value rides the bar; the money is beside it. */}
                    <span className="bar-count">
                      {product.quantity}
                      <span className="muted small"> · {moneyShort(product.sales)}</span>
                    </span>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="card">
          <h2>Promo codes</h2>
          {promoCodes.length === 0 ? (
            <p className="muted">No codes were used in this period.</p>
          ) : (
            <div className="table-wrap flat">
              <table className="table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th className="num">Orders</th>
                    <th className="num">Given away</th>
                    <th className="num">Sales</th>
                  </tr>
                </thead>
                <tbody>
                  {promoCodes.map((promo) => (
                    <tr key={promo.code}>
                      <td>
                        <code>{promo.code}</code>
                      </td>
                      <td className="num">{promo.orders}</td>
                      <td className="num">−{money(promo.discount_total)}</td>
                      <td className="num">{money(promo.sales)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>

      <section className="card">
        <h2>Delivery or pickup</h2>
        {splitOrders === 0 ? (
          <p className="muted">No orders in this period yet.</p>
        ) : (
          <>
            {/* A share of one total: a meter on one hue, not two rival colours. */}
            <div className="meter" role="img" aria-label={split.map((row) => `${row.label}: ${row.orders} orders`).join(', ')}>
              <span className="meter-fill" style={{ width: `${(split[0].orders / splitOrders) * 100}%` }} />
            </div>
            <ul className="split-legend">
              {split.map((row, index) => (
                <li key={row.value}>
                  <span className={`meter-key ${index === 0 ? 'is-filled' : ''}`} aria-hidden="true" />
                  <strong>{row.label}</strong>
                  <span className="muted">
                    {pluralize(row.orders, 'order')} · {money(row.sales)} ·{' '}
                    {Math.round((row.orders / splitOrders) * 100)}%
                  </span>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>
    </>
  )
}
