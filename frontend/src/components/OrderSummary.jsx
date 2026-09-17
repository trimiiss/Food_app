import { useShop } from '../context/ShopContext'

/**
 * Subtotal / delivery / total block, shared by the cart, checkout and order pages.
 * `estimated` marks client-side numbers that the server will recompute.
 */
export default function OrderSummary({ subtotal, deliveryFee, total, estimated = false, children }) {
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
        <div className="summary-total">
          <dt>{estimated ? 'Estimated total' : 'Total'}</dt>
          <dd>{money(total)}</dd>
        </div>
      </dl>
      {estimated && <p className="summary-note">Prices are confirmed by the restaurant when you place your order.</p>}
      {children}
    </div>
  )
}
