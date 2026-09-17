import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { getAdminOrders } from '../../api/admin'
import { EmptyState, ErrorMessage, Loader } from '../../components/Feedback'
import { StatusBadge } from '../../components/OrderStatus'
import Pagination from '../../components/Pagination'
import StatusActions from '../../components/StatusActions'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { formatDateTime, pluralize } from '../../utils/format'

export default function AdminOrdersPage() {
  const { money } = useShop()
  const [searchParams, setSearchParams] = useSearchParams()
  const status = searchParams.get('status') ?? ''
  const search = searchParams.get('search') ?? ''
  const page = Number(searchParams.get('page') ?? 1)
  const [flash, setFlash] = useState(null)

  const orders = useApi(
    (options) => getAdminOrders({ status: status || undefined, search: search || undefined, page }, options),
    [status, search, page],
  )

  const [searchInput, setSearchInput] = useState(search)
  useEffect(() => setSearchInput(search), [search])
  useEffect(() => {
    if (searchInput.trim() === search) return undefined
    const timeout = setTimeout(() => updateParams({ search: searchInput.trim() }), 300)
    return () => clearTimeout(timeout)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput])

  function updateParams(changes) {
    const next = new URLSearchParams(searchParams)
    Object.entries(changes).forEach(([key, value]) => (value ? next.set(key, value) : next.delete(key)))
    if (!('page' in changes)) next.delete('page')
    setSearchParams(next)
  }

  // Swap the updated order into the current page without refetching the list.
  const handleUpdated = (updated) => {
    setFlash(`${updated.order_number} is now “${updated.status_label}”.`)
    orders.setData((current) => ({
      ...current,
      data: current.data.map((order) => (order.id === updated.id ? { ...order, ...updated } : order)),
    }))
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Orders</h1>
          <p>{orders.data ? pluralize(orders.data.meta.total, 'order') : 'Track and update every order'}</p>
        </div>
        <button type="button" className="btn btn-sm btn-secondary" onClick={orders.reload} disabled={orders.loading}>
          {orders.loading ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>

      {flash && <div className="alert alert-success">{flash}</div>}

      <div className="toolbar">
        <label className="toolbar-search">
          <span className="visually-hidden">Search orders</span>
          <input
            type="search"
            className="input"
            placeholder="Order number, customer name or email…"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
          />
        </label>
        <label>
          <span className="visually-hidden">Filter by status</span>
          <select className="input" value={status} onChange={(event) => updateParams({ status: event.target.value })}>
            <option value="">All statuses</option>
            {/* Built from the enum the API sends, not a hardcoded list. */}
            {orders.data?.statuses.map((item) => (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      {orders.error && !orders.data ? (
        <ErrorMessage error={orders.error} onRetry={orders.reload} />
      ) : !orders.data ? (
        <Loader label="Loading orders…" />
      ) : orders.data.data.length === 0 ? (
        <EmptyState icon="🧾" title="No orders found">
          <p>{status || search ? 'Try a different status or search.' : 'Orders will appear here as customers check out.'}</p>
        </EmptyState>
      ) : (
        <>
          <div className={`table-wrap ${orders.loading ? 'is-refreshing' : ''}`}>
            <table className="table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Placed</th>
                  <th className="num">Items</th>
                  <th className="num">Total</th>
                  <th>Status</th>
                  <th>Update</th>
                </tr>
              </thead>
              <tbody>
                {orders.data.data.map((order) => (
                  <tr key={order.id}>
                    <td>
                      <Link to={`/admin/orders/${order.id}`} className="order-number">
                        {order.order_number}
                      </Link>
                    </td>
                    <td>
                      {order.customer?.name}
                      <div className="muted small">{order.customer?.email}</div>
                    </td>
                    <td className="nowrap">{formatDateTime(order.created_at)}</td>
                    <td className="num">{order.items_count}</td>
                    <td className="num">{money(order.total)}</td>
                    <td>
                      <StatusBadge status={order.status} label={order.status_label} />
                    </td>
                    <td>
                      <StatusActions order={order} variant="select" onUpdated={handleUpdated} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={orders.data.meta} onPageChange={(next) => updateParams({ page: String(next) })} />
        </>
      )}
    </>
  )
}
