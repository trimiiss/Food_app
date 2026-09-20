import { useShop } from '../context/ShopContext'

/**
 * Subtotal / delivery / discount / total block, shared by the cart, checkout
 * and order pages. Every figure is server-computed: the cart and checkout get
 * theirs from /cart/preview, order pages from the order itself.
 */
export default function OrderSummary({
  subtotal,
  deliveryFee,
  total,
  discountTotal = 0,
  promoCode = null,
  savings = 0,
  children,
}) {
  const { money } = useShop()

  return (
    <div className="summary">
      <dl>
        <div>
          <dt>Subtotal</dt>
          <dd>{money(subtotal)}</dd>
        </div>
        <div>
          <dt>Delivery</dt>
          <dd>{money(deliveryFee)}</dd>
        </div>
        {discountTotal > 0 && (
          <div className="summary-discount">
            <dt>Discount{promoCode ? ` (${promoCode})` : ''}</dt>
            <dd>−{money(discountTotal)}</dd>
          </div>
        )}
        <div className="summary-total">
          <dt>Total</dt>
          <dd>{money(total)}</dd>
        </div>
      </dl>
      {savings > 0 && <p className="summary-savings">🎉 You saved {money(savings)} on this order.</p>}
      {children}
    </div>
  )
}
