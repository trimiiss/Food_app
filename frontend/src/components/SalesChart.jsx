import { useMemo, useState } from 'react'
import { useShop } from '../context/ShopContext'
import { formatDayMonth } from '../utils/format'

/*
 * Sales per day, drawn as inline SVG — one series, one hue, no chart library.
 *
 * Deliberate choices:
 *  - One measure on one axis. Order counts live in the tooltip rather than on a
 *    second y-scale: two scales on one plot invent a correlation that isn't
 *    in the data.
 *  - Quiet days are plotted as zero, never skipped, so the shape of a week is
 *    honest.
 *  - Values are labelled only at the peak and the last point; a number on every
 *    point is noise. Everything else is in the tooltip and the table below.
 */

const VIEW = { width: 800, height: 260, top: 18, right: 18, bottom: 30, left: 56 }
const PLOT_WIDTH = VIEW.width - VIEW.left - VIEW.right
const PLOT_HEIGHT = VIEW.height - VIEW.top - VIEW.bottom
const TICKS = 4

/** A round axis maximum (10, 25, 50, 100…) so the ticks read as clean numbers. */
function niceMax(value) {
  if (value <= 0) return 1
  const magnitude = 10 ** Math.floor(Math.log10(value))
  const step = [1, 1.5, 2, 2.5, 5, 10].find((multiple) => value <= multiple * magnitude) ?? 10
  return step * magnitude
}

export default function SalesChart({ days }) {
  const { money, moneyShort } = useShop()
  const [activeIndex, setActiveIndex] = useState(null)

  const chart = useMemo(() => {
    const max = niceMax(Math.max(...days.map((day) => day.sales), 0))
    const lastIndex = days.length - 1

    const x = (index) => (lastIndex === 0 ? VIEW.left + PLOT_WIDTH / 2 : VIEW.left + (index / lastIndex) * PLOT_WIDTH)
    const y = (value) => VIEW.top + PLOT_HEIGHT - (value / max) * PLOT_HEIGHT

    const points = days.map((day, index) => ({ ...day, index, x: x(index), y: y(day.sales) }))
    const baseline = VIEW.top + PLOT_HEIGHT
    const line = points.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x},${point.y}`).join(' ')

    // The peak is worth naming; the rest of the values are in the tooltip.
    const peak = points.reduce((best, point) => (point.sales > best.sales ? point : best), points[0])

    return {
      max,
      points,
      baseline,
      line,
      area: `${line} L${points[lastIndex].x},${baseline} L${points[0].x},${baseline} Z`,
      peak: peak.sales > 0 && peak.index !== lastIndex ? peak : null,
      last: points[lastIndex],
      isEmpty: points.every((point) => point.sales === 0),
    }
  }, [days])

  const active = activeIndex === null ? null : chart.points[activeIndex]

  const handleMove = (event) => {
    const bounds = event.currentTarget.getBoundingClientRect()
    const ratio = (event.clientX - bounds.left) / bounds.width
    const index = Math.round(ratio * (chart.points.length - 1))
    setActiveIndex(Math.min(chart.points.length - 1, Math.max(0, index)))
  }

  const total = days.reduce((sum, day) => sum + day.sales, 0)

  return (
    <figure className="chart">
      <div className="chart-plot">
        <svg
          viewBox={`0 0 ${VIEW.width} ${VIEW.height}`}
          className="chart-svg"
          role="img"
          aria-label={`Sales per day over the last ${days.length} days, ${money(total)} in total. The table below lists every day.`}
        >
          {/* Gridlines: hairline, solid, recessive — they must never compete with the data. */}
          {Array.from({ length: TICKS + 1 }, (_, tick) => {
            const value = (chart.max / TICKS) * tick
            const lineY = VIEW.top + PLOT_HEIGHT - (tick / TICKS) * PLOT_HEIGHT
            return (
              <g key={tick}>
                <line
                  x1={VIEW.left}
                  x2={VIEW.width - VIEW.right}
                  y1={lineY}
                  y2={lineY}
                  className="chart-grid"
                  vectorEffect="non-scaling-stroke"
                />
                <text x={VIEW.left - 10} y={lineY + 4} className="chart-tick" textAnchor="end">
                  {moneyShort(value)}
                </text>
              </g>
            )
          })}

          {/* Date ticks: first, last and a few between, never every day. */}
          {chart.points
            .filter((_, index) => index === 0 || index === chart.points.length - 1 || index % Math.ceil(chart.points.length / 6) === 0)
            .map((point) => (
              <text key={point.date} x={point.x} y={VIEW.height - 8} className="chart-tick" textAnchor="middle">
                {formatDayMonth(point.date)}
              </text>
            ))}

          <path d={chart.area} className="chart-area" />
          <path d={chart.line} className="chart-line" vectorEffect="non-scaling-stroke" />

          {chart.peak && (
            <>
              <circle cx={chart.peak.x} cy={chart.peak.y} r="4" className="chart-dot" vectorEffect="non-scaling-stroke" />
              <text x={chart.peak.x} y={chart.peak.y - 12} className="chart-value" textAnchor="middle">
                {moneyShort(chart.peak.sales)}
              </text>
            </>
          )}

          {!chart.isEmpty && (
            <>
              <circle cx={chart.last.x} cy={chart.last.y} r="4" className="chart-dot" vectorEffect="non-scaling-stroke" />
              <text
                x={chart.last.x}
                y={chart.last.y - 12}
                className="chart-value"
                textAnchor={chart.points.length > 1 ? 'end' : 'middle'}
              >
                {moneyShort(chart.last.sales)}
              </text>
            </>
          )}

          {active && (
            <>
              <line
                x1={active.x}
                x2={active.x}
                y1={VIEW.top}
                y2={chart.baseline}
                className="chart-crosshair"
                vectorEffect="non-scaling-stroke"
              />
              <circle cx={active.x} cy={active.y} r="5" className="chart-dot is-active" vectorEffect="non-scaling-stroke" />
            </>
          )}

          {/* Transparent hit layer: the whole plot is hoverable, not just the 2px line. */}
          <rect
            x={VIEW.left}
            y={VIEW.top}
            width={PLOT_WIDTH}
            height={PLOT_HEIGHT}
            fill="transparent"
            onMouseMove={handleMove}
            onMouseLeave={() => setActiveIndex(null)}
          />
        </svg>

        {active && (
          <div
            className="chart-tooltip"
            style={{ left: `${(active.x / VIEW.width) * 100}%` }}
            role="status"
          >
            <strong>{formatDayMonth(active.date)}</strong>
            <span>{money(active.sales)}</span>
            <span className="muted small">
              {active.orders} {active.orders === 1 ? 'order' : 'orders'}
            </span>
          </div>
        )}

        {chart.isEmpty && <p className="chart-empty">No sales in this period yet.</p>}
      </div>

      {/* Colour and hover are never the only way to read this. */}
      <figcaption>
        <details className="chart-table">
          <summary>View as table</summary>
          <div className="table-wrap flat">
            <table className="table">
              <thead>
                <tr>
                  <th>Day</th>
                  <th className="num">Orders</th>
                  <th className="num">Sales</th>
                </tr>
              </thead>
              <tbody>
                {days.map((day) => (
                  <tr key={day.date}>
                    <td className="nowrap">{formatDayMonth(day.date)}</td>
                    <td className="num">{day.orders}</td>
                    <td className="num">{money(day.sales)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      </figcaption>
    </figure>
  )
}
