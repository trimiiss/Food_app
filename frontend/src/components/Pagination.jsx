/**
 * Prev/next pager for Laravel paginated responses (uses `meta`).
 * Renders nothing when everything fits on one page.
 */
export default function Pagination({ meta, onPageChange }) {
  if (!meta || meta.last_page <= 1) return null

  return (
    <nav className="pagination" aria-label="Pagination">
      <button
        type="button"
        className="btn btn-sm btn-secondary"
        disabled={meta.current_page <= 1}
        onClick={() => onPageChange(meta.current_page - 1)}
      >
        ← Previous
      </button>
      <span>
        Page {meta.current_page} of {meta.last_page}
      </span>
      <button
        type="button"
        className="btn btn-sm btn-secondary"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPageChange(meta.current_page + 1)}
      >
        Next →
      </button>
    </nav>
  )
}
