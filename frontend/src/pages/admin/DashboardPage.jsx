import { Link } from 'react-router'
import { getStats } from '../../api/admin'
import { ErrorMessage, Loader } from '../../components/Feedback'
import { StatusBadge } from '../../components/OrderStatus'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { formatDateTime } from '../../utils/format'

export default function DashboardPage() {
  const { money } = useShop()
  const { data: stats, error, loading, reload } = useApi((options) => getStats(options), [])

  if (loading && !stats) return <Loader label="Loading dashboard…" />
  if (error && !stats) return <ErrorMessage error={error} onRetry={reload} />

  const maxStatusCount = Math.max(1, ...stats.orders_by_status.map((status) => status.count))

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Dashboard</h1>
          <p>What’s happening in the kitchen right now.</p>
        </div>
        <button type="button" className="btn btn-sm btn-secondary" onClick={reload} disabled={loading}>
          {loading ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>

      <div className="stats-grid">
        <Link to="/admin/orders" className="stat-card">
          <span className="stat-label">Open orders</span>
          <span className="stat-value">{stats.open_orders_count}</span>
          <span className="stat-sub">{stats.orders_count} orders in total</span>
        </Link>
        <div className="stat-card">
          <span className="stat-label">Revenue</span>
          <span className="stat-value">{money(stats.revenue)}</span>
          <span className="stat-sub">from delivered orders</span>
        </div>
        <Link to="/admin/products" className="stat-card">
          <span className="stat-label">Products</span>
          <span className="stat-value">{stats.products_count}</span>
          <span className="stat-sub">{stats.available_products_count} available</span>
        </Link>
        <Link to="/admin/categories" className="stat-card">
          <span className="stat-label">Categories</span>
          <span className="stat-value">{stats.categories_count}</span>
          <span className="stat-sub">on the menu</span>
        </Link>
      </div>

      <div className="dashboard-grid">
        <section className="card">
          <h2>Orders by status</h2>
          <ul className="bar-list">
            {stats.orders_by_status.map(({ value, label, count }) => (
              <li key={value}>
                <Link to={`/admin/orders?status=${value}`} className="bar-row">
                  <span className="bar-label">{label}</span>
                  <span className="bar-track">
                    <span className={`bar-fill bar-${value}`} style={{ width: `${(count / maxStatusCount) * 100}%` }} />
                  </span>
                  <span className="bar-count">{count}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>

        <section className="card">
          <div className="card-header">
            <h2>Recent orders</h2>
            <Link to="/admin/orders">View all →</Link>
          </div>
          {stats.recent_orders.length === 0 ? (
            <p className="muted">No orders yet.</p>
          ) : (
            <ul className="recent-orders">
              {stats.recent_orders.map((order) => (
                <li key={order.id}>
                  <Link to={`/admin/orders/${order.id}`} className="recent-order">
                    <span>
                      <strong className="order-number">{order.order_number}</strong>
                      <span className="muted">
                        {order.customer?.name} · {formatDateTime(order.created_at)}
                      </span>
                    </span>
                    <span className="recent-order-side">
                      <StatusBadge status={order.status} label={order.status_label} />
                      <span className="price">{money(order.total)}</span>
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </>
  )
}
