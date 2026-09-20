import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router'
import { cancelMyOrder, getMyOrder } from '../api/orders'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import { OrderTimeline, StatusBadge } from '../components/OrderStatus'
import OrderSummary from '../components/OrderSummary'
import { useShop } from '../context/ShopContext'
import { useApi } from '../hooks/useApi'
import { formatDateTime } from '../utils/format'

const POLL_INTERVAL_MS = 15000

/** Order tracking: live-ish status timeline, items, totals and cancellation. */
export default function OrderDetailPage() {
  const { id } = useParams()
  const location = useLocation()
  const { money } = useShop()
  const [cancelling, setCancelling] = useState(false)
  const [actionError, setActionError] = useState(null)

  const { data: order, error, loading, reload, setData } = useApi((options) => getMyOrder(id, options), [id])

  // Poll while the order is still moving so the timeline advances on its own.
  // (WebSockets would be the production answer; polling keeps the demo simple.)
  const isFinal = order ? order.allowed_transitions.length === 0 : true
  useEffect(() => {
    if (isFinal) return undefined
    const interval = setInterval(reload, POLL_INTERVAL_MS)
    return () => clearInterval(interval)
  }, [isFinal, reload])

  if (loading && !order) return <Loader label="Loading order…" />

  if (error?.status === 404) {
    return (
      <EmptyState icon="🧾" title="Order not found">
        <Link to="/orders" className="btn btn-primary">
          Back to my orders
        </Link>
      </EmptyState>
    )
  }

  if (error && !order) return <ErrorMessage error={error} onRetry={reload} />

  const handleCancel = async () => {
    if (!window.confirm('Cancel this order?')) return
    setCancelling(true)
    setActionError(null)
    try {
      setData(await cancelMyOrder(order.id))
    } catch (caught) {
      setActionError(caught)
      reload() // the status may have moved on (e.g. kitchen started preparing)
    } finally {
      setCancelling(false)
    }
  }

  return (
    <>
      <Link to="/orders" className="back-link">
        ← All orders
      </Link>

      {location.state?.justPlaced && (
        <div className="alert alert-success">
          <span>🎉 Thanks! Your order has been placed. We’ll keep this page updated.</span>
        </div>
      )}

      <div className="page-header">
        <div>
          <h1>Order {order.order_number}</h1>
          <p>Placed {formatDateTime(order.created_at)}</p>
        </div>
        <StatusBadge status={order.status} label={order.status_label} />
      </div>

      <section className="card timeline-card">
        <OrderTimeline status={order.status} />
      </section>

      <div className="two-column">
        <div className="stack">
          <section className="card">
            <h2>Items</h2>
            <div className="table-wrap flat">
              <table className="table">
                <thead>
                  <tr>
                    <th>Dish</th>
                    <th className="num">Price</th>
                    <th className="num">Qty</th>
                    <th className="num">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {order.items.map((item) => (
                    <tr key={item.id}>
                      <td>{item.product_name}</td>
                      <td className="num">
                        {money(item.unit_price)}
                        {/* Bought on offer: show what it normally costs. */}
                        {item.original_unit_price && <s className="price-was"> {money(item.original_unit_price)}</s>}
                      </td>
                      <td className="num">{item.quantity}</td>
                      <td className="num">{money(item.line_total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>

          <section className="card">
            <h2>Delivery</h2>
            <dl className="details">
              <dt>Address</dt>
              <dd>{order.delivery_address}</dd>
              <dt>Phone</dt>
              <dd>{order.contact_phone}</dd>
              {order.notes && (
                <>
                  <dt>Notes</dt>
                  <dd>{order.notes}</dd>
                </>
              )}
            </dl>
          </section>
        </div>

        <aside className="card sticky-card">
          <h2>Summary</h2>
          <OrderSummary
            subtotal={order.subtotal}
            deliveryFee={order.delivery_fee}
            discountTotal={order.discount_total}
            promoCode={order.promo_code}
            total={order.total}
          >
            <ErrorMessage error={actionError} />
            {order.can_cancel && (
              <button type="button" className="btn btn-danger btn-block" onClick={handleCancel} disabled={cancelling}>
                {cancelling ? 'Cancelling…' : 'Cancel order'}
              </button>
            )}
          </OrderSummary>
          {!isFinal && <p className="summary-note">Status refreshes automatically.</p>}
        </aside>
      </div>
    </>
  )
}
