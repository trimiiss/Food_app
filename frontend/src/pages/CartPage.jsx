import { Link } from 'react-router'
import { EmptyState, ErrorMessage } from '../components/Feedback'
import OrderSummary from '../components/OrderSummary'
import ProductImage from '../components/ProductImage'
import PromoCodeField from '../components/PromoCodeField'
import QuantityStepper from '../components/QuantityStepper'
import { useCart } from '../context/CartContext'
import { useShop } from '../context/ShopContext'
import { useCartPricing } from '../hooks/useCartPricing'
import { pluralize } from '../utils/format'

export default function CartPage() {
  const { items, itemCount, subtotal, deliveryFee, estimatedTotal, maxQuantity, setQuantity, removeItem, clearCart } =
    useCart()
  const { money } = useShop()
  // Totals (offers + promo code) are computed by the API, not here.
  const { pricing, promoError, loading, error } = useCartPricing()

  if (items.length === 0) {
    return (
      <EmptyState icon="🛒" title="Your cart is empty">
        <p>Browse the menu and add something delicious.</p>
        <div className="row empty-actions">
          <Link to="/" className="btn btn-primary">
            Browse the menu
          </Link>
          <Link to="/deals" className="btn btn-secondary">
            See today’s deals 🔥
          </Link>
        </div>
      </EmptyState>
    )
  }

  // Fall back to the local estimate only while the first price call is in flight.
  const totals = pricing ?? { subtotal, delivery_fee: deliveryFee, total: estimatedTotal, discount_total: 0, total_savings: 0 }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Your cart</h1>
          <p>{pluralize(itemCount, 'item')}</p>
        </div>
        <button type="button" className="btn btn-sm btn-ghost" onClick={clearCart}>
          Clear cart
        </button>
      </div>

      <ErrorMessage error={error} />

      <div className="two-column">
        <ul className="cart-list">
          {items.map((item) => {
            // The priced line tells us whether this one is on offer right now.
            const priced = pricing?.lines.find((line) => line.product_id === item.productId)
            const unitPrice = priced?.unit_price ?? item.price
            const wasPrice = priced?.original_unit_price ?? null

            return (
              <li key={item.productId} className="cart-item">
                <Link to={`/products/${item.slug}`} className="cart-item-image" tabIndex={-1} aria-hidden="true">
                  <ProductImage src={item.imageUrl} alt="" />
                </Link>

                <div className="cart-item-info">
                  <Link to={`/products/${item.slug}`} className="cart-item-name">
                    {item.name}
                  </Link>
                  <span className="muted">
                    {money(unitPrice)} each{' '}
                    {wasPrice && <s className="price-was">{money(wasPrice)}</s>}
                  </span>
                  <button type="button" className="link-button" onClick={() => removeItem(item.productId)}>
                    Remove
                  </button>
                </div>

                <div className="cart-item-controls">
                  {/* min=0: stepping below 1 removes the line (handled by the cart reducer). */}
                  <QuantityStepper
                    value={item.quantity}
                    min={0}
                    max={maxQuantity}
                    label={`Quantity of ${item.name}`}
                    onChange={(quantity) => setQuantity(item.productId, quantity)}
                  />
                  <span className="price">{money(unitPrice * item.quantity)}</span>
                </div>
              </li>
            )
          })}
        </ul>

        <aside className="card sticky-card">
          <h2>Order summary</h2>
          <PromoCodeField applied={pricing?.promo_code} error={promoError} loading={loading} />
          <OrderSummary
            subtotal={totals.subtotal}
            deliveryFee={totals.delivery_fee}
            discountTotal={totals.discount_total}
            promoCode={pricing?.promo_code?.code}
            total={totals.total}
            savings={totals.total_savings}
          >
            <Link to="/checkout" className="btn btn-primary btn-block">
              Proceed to checkout
            </Link>
            <Link to="/" className="btn btn-ghost btn-block">
              Continue shopping
            </Link>
          </OrderSummary>
        </aside>
      </div>
    </>
  )
}
