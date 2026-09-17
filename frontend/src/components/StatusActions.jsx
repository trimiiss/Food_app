import { useState } from 'react'
import { updateOrderStatus } from '../api/admin'
import { ErrorMessage } from './Feedback'

/**
 * Admin controls to move an order along. Options come straight from the API's
 * `allowed_transitions`, so only legal next states are ever offered; the
 * server re-checks the state machine anyway.
 *
 *   variant="buttons"  one button per transition (order detail page)
 *   variant="select"   compact dropdown (orders table)
 */
export default function StatusActions({ order, onUpdated, variant = 'buttons' }) {
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const transitions = order.allowed_transitions

  if (transitions.length === 0) {
    return variant === 'select' ? <span className="muted small">—</span> : <p className="muted">This order is closed.</p>
  }

  const apply = async (status) => {
    if (!status) return
    // Cancelling is terminal — ask first.
    if (status === 'cancelled' && !window.confirm(`Cancel order ${order.order_number}?`)) return

    setSaving(true)
    setError(null)
    try {
      onUpdated(await updateOrderStatus(order.id, status))
    } catch (caught) {
      setError(caught)
    } finally {
      setSaving(false)
    }
  }

  if (variant === 'select') {
    return (
      <>
        <label className="visually-hidden" htmlFor={`status-${order.id}`}>
          Change status of {order.order_number}
        </label>
        <select
          id={`status-${order.id}`}
          className="input input-sm"
          value=""
          disabled={saving}
          onChange={(event) => apply(event.target.value)}
        >
          <option value="">{saving ? 'Saving…' : 'Move to…'}</option>
          {transitions.map((transition) => (
            <option key={transition.value} value={transition.value}>
              {transition.label}
            </option>
          ))}
        </select>
        {error && <div className="field-error">{error.fieldError('status') ?? error.message}</div>}
      </>
    )
  }

  return (
    <div className="stack">
      <ErrorMessage error={error && new Error(error.fieldError('status') ?? error.message)} />
      {transitions.map((transition) => (
        <button
          key={transition.value}
          type="button"
          className={`btn btn-block ${transition.value === 'cancelled' ? 'btn-danger' : 'btn-primary'}`}
          disabled={saving}
          onClick={() => apply(transition.value)}
        >
          {transition.value === 'cancelled' ? 'Cancel order' : `Mark as ${transition.label.toLowerCase()}`}
        </button>
      ))}
    </div>
  )
}
