import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { downloadOrdersCsv, getAdminOrders } from '../../api/admin'
import { EmptyState, ErrorMessage, Loader } from '../../components/Feedback'
import { FulfillmentBadge, StatusBadge } from '../../components/OrderStatus'
import Pagination from '../../components/Pagination'
import StatusActions from '../../components/StatusActions'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { formatDateTime, pluralize } from '../../utils/format'

export default function AdminOrdersPage() {
  const { money } = useShop()
  const [searchParams, setSearchParams] = useSearchParams()
  const status = searchParams.get('status') ?? ''
  const fulfillment = searchParams.get('fulfillment_type') ?? ''
  const search = searchParams.get('search') ?? ''
  const page = Number(searchParams.get('page') ?? 1)
  const [flash, setFlash] = useState(null)
  const [exporting, setExporting] = useState(false)
  const [exportError, setExportError] = useState(null)

  const orders = useApi(
    (options) =>
      getAdminOrders(
        {
          status: status || undefined,
          fulfillment_type: fulfillment || undefined,
          search: search || undefined,
          page,
        },
        options,
      ),
    [status, fulfillment, search, page],
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

  const handleExport = async () => {
    setExporting(true)
    setExportError(null)
    try {
      await downloadOrdersCsv({
        status: status || undefined,
        fulfillment_type: fulfillment || undefined,
        search: search || undefined,
      })
    } catch (caught) {
      setExportError(caught)
    } finally {
      setExporting(false)
    }
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
        <div className="row">
          {/* Exports exactly what the filters above are showing. */}
          <button type="button" className="btn btn-sm btn-secondary" onClick={handleExport} disabled={exporting}>
            {exporting ? 'Preparing…' : '⬇ Export CSV'}
          </button>
          <button type="button" className="btn btn-sm btn-secondary" onClick={orders.reload} disabled={orders.loading}>
            {orders.loading ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>
      </div>

      {flash && <div className="alert alert-success">{flash}</div>}
      <ErrorMessage error={exportError} />

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
        <label>
          <span className="visually-hidden">Filter by delivery or pickup</span>
          <select
            className="input"
            value={fulfillment}
            onChange={(event) => updateParams({ fulfillment_type: event.target.value })}
          >
            <option value="">Delivery &amp; pickup</option>
            {orders.data?.fulfillment_types?.map((item) => (
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
          <p>
            {status || search || fulfillment
              ? 'Try a different filter or search.'
              : 'Orders will appear here as customers check out.'}
          </p>
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
                      <div className="mt-xs">
                        <FulfillmentBadge type={order.fulfillment_type} label={order.fulfillment_label} />
                      </div>
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
