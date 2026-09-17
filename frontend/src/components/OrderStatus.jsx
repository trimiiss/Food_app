/**
 * Status badge + progress timeline for an order.
 *
 * The step *order* below is presentation only. What may actually happen next
 * (e.g. whether the order can still be cancelled) always comes from the API
 * via `allowed_transitions` / `can_cancel`.
 */

const STEPS = [
  { value: 'pending', label: 'Order placed' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'preparing', label: 'Preparing' },
  { value: 'out_for_delivery', label: 'Out for delivery' },
  { value: 'delivered', label: 'Delivered' },
]

export function StatusBadge({ status, label }) {
  return <span className={`badge badge-${status}`}>{label}</span>
}

export function OrderTimeline({ status }) {
  if (status === 'cancelled') {
    return (
      <div className="alert alert-error timeline-cancelled">
        <span>This order was cancelled.</span>
      </div>
    )
  }

  const currentIndex = STEPS.findIndex((step) => step.value === status)

  return (
    <ol className="timeline" aria-label="Order progress">
      {STEPS.map((step, index) => {
        const state = index < currentIndex ? 'done' : index === currentIndex ? 'current' : 'upcoming'
        return (
          <li key={step.value} className={`timeline-step is-${state}`} aria-current={state === 'current' ? 'step' : undefined}>
            <span className="timeline-dot" aria-hidden="true">
              {state === 'done' ? '✓' : index + 1}
            </span>
            <span className="timeline-label">{step.label}</span>
          </li>
        )
      })}
    </ol>
  )
}
