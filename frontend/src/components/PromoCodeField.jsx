import { useState } from 'react'
import { useCart } from '../context/CartContext'

/**
 * Promo code entry. The applied code lives in the cart, so it survives moving
 * between the cart and checkout; whether it is valid is decided by the server.
 */
export default function PromoCodeField({ applied, error, loading }) {
  const { promoCode, setPromoCode } = useCart()
  const [draft, setDraft] = useState(promoCode ?? '')
  // Keep the box in step when the code changes elsewhere (applied, removed,
  // cleared with the cart). Adjusting during render rather than in an effect
  // avoids a second render pass.
  const [syncedCode, setSyncedCode] = useState(promoCode)

  if (promoCode !== syncedCode) {
    setSyncedCode(promoCode)
    setDraft(promoCode ?? '')
  }

  if (applied) {
    return (
      <div className="promo-applied">
        <span>
          <span className="promo-applied-code">{applied.code}</span> applied — {applied.summary}
        </span>
        <button type="button" className="link-button" onClick={() => setPromoCode(null)}>
          Remove
        </button>
      </div>
    )
  }

  const apply = (event) => {
    event.preventDefault()
    setPromoCode(draft.trim() || null)
  }

  return (
    <div className="promo-field">
      {/* Nested forms are invalid HTML, so this is a div with a click handler
          rather than a <form> inside the checkout form. */}
      <label className="visually-hidden" htmlFor="promo_code">
        Promo code
      </label>
      <div className="promo-field-row">
        <input
          id="promo_code"
          className="input"
          placeholder="Promo code"
          autoComplete="off"
          autoCapitalize="characters"
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          onKeyDown={(event) => event.key === 'Enter' && apply(event)}
          aria-invalid={Boolean(error)}
        />
        <button type="button" className="btn btn-secondary" onClick={apply} disabled={loading || !draft.trim()}>
          {loading ? 'Checking…' : 'Apply'}
        </button>
      </div>
      {error && <span className="field-error">{error}</span>}
    </div>
  )
}
