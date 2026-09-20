import { useEffect, useMemo, useState } from 'react'
import { previewCart } from '../api/orders'
import { useCart } from '../context/CartContext'

const EMPTY = { pricing: null, promoError: null, loading: false, error: null }
const DEBOUNCE_MS = 250

/**
 * Prices the current cart on the server (offers, delivery or pickup, promo code).
 *
 * The browser never computes discounts or the delivery fee: it sends ids,
 * quantities, how the order will be fulfilled and the code, then shows what
 * comes back — the same numbers checkout will charge.
 *
 * If the code itself is the problem (expired, under the minimum, delivery-only
 * on a pickup order), the cart is re-priced without it so the customer still
 * sees their totals next to an explanation, instead of the page breaking.
 */
export function useCartPricing() {
  const { items, promoCode, fulfillment } = useCart()
  const [state, setState] = useState(EMPTY)

  const lines = useMemo(
    () => items.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
    [items],
  )
  // Re-price whenever the cart contents, the code or delivery/pickup change.
  const requestKey = useMemo(
    () => JSON.stringify([lines, promoCode ?? null, fulfillment]),
    [lines, promoCode, fulfillment],
  )

  useEffect(() => {
    if (lines.length === 0) {
      setState(EMPTY)
      return undefined
    }

    const controller = new AbortController()
    const settle = (next) => {
      if (!controller.signal.aborted) setState(next)
    }

    setState((previous) => ({ ...previous, loading: true }))

    // Coalesce rapid changes (holding "+" on a quantity) into one request.
    const timer = setTimeout(() => {
      const request = { items: lines, fulfillmentType: fulfillment }

      previewCart({ ...request, promoCode }, { signal: controller.signal })
        .then((pricing) => settle({ pricing, promoError: null, loading: false, error: null }))
        .catch((error) => {
          if (controller.signal.aborted) return

          const promoMessage = error?.fieldError?.('promo_code')
          if (!promoMessage) {
            settle({ pricing: null, promoError: null, loading: false, error })
            return
          }

          // Bad code: keep the cart usable by pricing it without one.
          previewCart({ ...request, promoCode: null }, { signal: controller.signal })
            .then((pricing) => settle({ pricing, promoError: promoMessage, loading: false, error: null }))
            .catch((fallbackError) =>
              settle({ pricing: null, promoError: promoMessage, loading: false, error: fallbackError }),
            )
        })
    }, DEBOUNCE_MS)

    return () => {
      clearTimeout(timer)
      controller.abort()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [requestKey])

  return state
}
