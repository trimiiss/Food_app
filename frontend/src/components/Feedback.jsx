/**
 * Shared loading / error / empty states so every page handles the three
 * non-happy paths consistently.
 */

export function Loader({ label = 'Loading…' }) {
  return (
    <div className="loader" role="status" aria-live="polite">
      <span className="spinner" aria-hidden="true" />
      <span>{label}</span>
    </div>
  )
}

export function ErrorMessage({ error, onRetry }) {
  if (!error) return null

  return (
    <div className="alert alert-error" role="alert">
      <span>{error.message ?? String(error)}</span>
      {onRetry && (
        <button type="button" className="btn btn-sm btn-secondary" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  )
}

export function EmptyState({ icon = '🍽️', title, children }) {
  return (
    <div className="empty-state">
      <div className="empty-state-icon" aria-hidden="true">
        {icon}
      </div>
      <h2>{title}</h2>
      {children}
    </div>
  )
}
