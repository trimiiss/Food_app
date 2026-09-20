import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { getAdminOrder } from '../../api/admin'
import { EmptyState, ErrorMessage, Loader } from '../../components/Feedback'
import { OrderTimeline, StatusBadge } from '../../components/OrderStatus'
import OrderSummary from '../../components/OrderSummary'
import StatusActions from '../../components/StatusActions'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { formatDateTime } from '../../utils/format'

export default function AdminOrderDetailPage() {
  const { id } = useParams()
  const { money } = useShop()
  const [flash, setFlash] = useState(null)
  const { data: order, error, loading, reload, setData } = useApi((options) => getAdminOrder(id, options), [id])

  if (loading && !order) return <Loader label="Loading order…" />

  if (error?.status === 404) {
    return (
      <EmptyState icon="🧾" title="Order not found">
        <Link to="/admin/orders" className="btn btn-primary">
          Back to orders
        </Link>
      </EmptyState>
    )
  }

  if (error && !order) return <ErrorMessage error={error} onRetry={reload} />

  const handleUpdated = (updated) => {
    setData(updated)
    setFlash(`Status changed to “${updated.status_label}”.`)
  }

  return (
    <>
      <Link to="/admin/orders" className="back-link">
        ← Orders
      </Link>

      <div className="page-header">
        <div>
          <h1>Order {order.order_number}</h1>
          <p>
            Placed {formatDateTime(order.created_at)} · last updated {formatDateTime(order.updated_at)}
          </p>
        </div>
        <StatusBadge status={order.status} label={order.status_label} />
      </div>

      {flash && <div className="alert alert-success">{flash}</div>}

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
                      <td>
                        {item.product_name}
                        {item.product_id === null && <div className="muted small">Product since removed from the menu</div>}
                      </td>
                      <td className="num">
                        {money(item.unit_price)}
                        {item.original_unit_price && <s className="price-was"> {money(item.original_unit_price)}</s>}
                      </td>
                      <td className="num">{item.quantity}</td>
                      <td className="num">{money(item.line_total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <OrderSummary
              subtotal={order.subtotal}
              deliveryFee={order.delivery_fee}
              discountTotal={order.discount_total}
              promoCode={order.promo_code}
              total={order.total}
            />
          </section>

          <section className="card">
            <h2>Customer & delivery</h2>
            <dl className="details">
              <dt>Customer</dt>
              <dd>
                {order.customer?.name} · <a href={`mailto:${order.customer?.email}`}>{order.customer?.email}</a>
              </dd>
              <dt>Phone</dt>
              <dd>
                <a href={`tel:${order.contact_phone}`}>{order.contact_phone}</a>
              </dd>
              <dt>Address</dt>
              <dd>{order.delivery_address}</dd>
              {order.notes && (
                <>
                  <dt>Notes</dt>
                  <dd className="notes">{order.notes}</dd>
                </>
              )}
            </dl>
          </section>
        </div>

        <aside className="card sticky-card">
          <h2>Update status</h2>
          <StatusActions order={order} onUpdated={handleUpdated} />
        </aside>
      </div>
    </>
  )
}
