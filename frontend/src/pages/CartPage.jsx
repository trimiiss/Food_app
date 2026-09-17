import { Link } from 'react-router'
import { EmptyState } from '../components/Feedback'
import OrderSummary from '../components/OrderSummary'
import ProductImage from '../components/ProductImage'
import QuantityStepper from '../components/QuantityStepper'
import { useCart } from '../context/CartContext'
import { useShop } from '../context/ShopContext'

export default function CartPage() {
  const { items, itemCount, subtotal, deliveryFee, estimatedTotal, maxQuantity, setQuantity, removeItem, clearCart } =
    useCart()
  const { money } = useShop()

  if (items.length === 0) {
    return (
      <EmptyState icon="🛒" title="Your cart is empty">
        <p>Browse the menu and add something delicious.</p>
        <Link to="/" className="btn btn-primary">
          Browse the menu
        </Link>
      </EmptyState>
    )
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Your cart</h1>
          <p>
            {itemCount} {itemCount === 1 ? 'item' : 'items'}
          </p>
        </div>
        <button type="button" className="btn btn-sm btn-ghost" onClick={clearCart}>
          Clear cart
        </button>
      </div>

      <div className="two-column">
        <ul className="cart-list">
          {items.map((item) => (
            <li key={item.productId} className="cart-item">
              <Link to={`/products/${item.slug}`} className="cart-item-image" tabIndex={-1} aria-hidden="true">
                <ProductImage src={item.imageUrl} alt="" />
              </Link>

              <div className="cart-item-info">
                <Link to={`/products/${item.slug}`} className="cart-item-name">
                  {item.name}
                </Link>
                <span className="muted">{money(item.price)} each</span>
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
                <span className="price">{money(item.price * item.quantity)}</span>
              </div>
            </li>
          ))}
        </ul>

        <aside className="card sticky-card">
          <h2>Order summary</h2>
          <OrderSummary subtotal={subtotal} deliveryFee={deliveryFee} total={estimatedTotal} estimated>
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
