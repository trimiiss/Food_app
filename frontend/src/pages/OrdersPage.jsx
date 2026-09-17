import { Link, useSearchParams } from 'react-router'
import { getMyOrders } from '../api/orders'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import { StatusBadge } from '../components/OrderStatus'
import Pagination from '../components/Pagination'
import { useShop } from '../context/ShopContext'
import { useApi } from '../hooks/useApi'
import { formatDateTime, pluralize } from '../utils/format'

/** The signed-in customer's order history, newest first. */
export default function OrdersPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const { money } = useShop()

  const { data, error, loading, reload } = useApi((options) => getMyOrders({ page }, options), [page])

  if (loading && !data) return <Loader label="Loading your orders…" />
  if (error) return <ErrorMessage error={error} onRetry={reload} />

  if (data.data.length === 0) {
    return (
      <EmptyState icon="🧾" title="No orders yet">
        <p>When you place an order it will show up here so you can track it.</p>
        <Link to="/" className="btn btn-primary">
          Order something
        </Link>
      </EmptyState>
    )
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>My orders</h1>
          <p>{pluralize(data.meta.total, 'order')}</p>
        </div>
      </div>

      <ul className="order-list">
        {data.data.map((order) => (
          <li key={order.id}>
            <Link to={`/orders/${order.id}`} className="order-row">
              <div className="order-row-main">
                <span className="order-number">{order.order_number}</span>
                <span className="muted">{formatDateTime(order.created_at)}</span>
                <span className="order-row-items">
                  {order.items.map((item) => `${item.quantity}× ${item.product_name}`).join(', ')}
                </span>
              </div>
              <div className="order-row-side">
                <StatusBadge status={order.status} label={order.status_label} />
                <span className="price">{money(order.total)}</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>

      <Pagination meta={data.meta} onPageChange={(next) => setSearchParams({ page: String(next) })} />
    </>
  )
}
