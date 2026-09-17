import { Link, useNavigate } from 'react-router'
import { placeOrder } from '../api/orders'
import { EmptyState, ErrorMessage } from '../components/Feedback'
import FormField from '../components/FormField'
import OrderSummary from '../components/OrderSummary'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { useShop } from '../context/ShopContext'
import { useForm } from '../hooks/useForm'

/**
 * Turns the client-side cart into a real order. Only product ids and
 * quantities are sent; the response carries the server-computed totals.
 */
export default function CheckoutPage() {
  const { user } = useAuth()
  const { items, subtotal, deliveryFee, estimatedTotal, clearCart, removeItem } = useCart()
  const { money } = useShop()
  const navigate = useNavigate()
  const form = useForm({ delivery_address: '', contact_phone: '', notes: '' })

  if (items.length === 0) {
    return (
      <EmptyState icon="🛒" title="Nothing to check out">
        <p>Your cart is empty.</p>
        <Link to="/" className="btn btn-primary">
          Browse the menu
        </Link>
      </EmptyState>
    )
  }

  const handleSubmit = form.submit(async (values) => {
    const order = await placeOrder({
      items: items.map((item) => ({ product_id: item.productId, quantity: item.quantity })),
      delivery_address: values.delivery_address,
      contact_phone: values.contact_phone,
      notes: values.notes || null,
    })
    navigate(`/orders/${order.id}`, { replace: true, state: { justPlaced: true } })
    clearCart()
  })

  // Errors like "items.2.product_id" point at a cart line (e.g. a dish that
  // became unavailable) — show them against the dish, with a quick fix.
  const lineProblems = Object.entries(form.error?.errors ?? {})
    .map(([key, messages]) => ({ match: key.match(/^items\.(\d+)\./), message: messages[0] }))
    .filter(({ match }) => match)
    .map(({ match, message }) => ({ item: items[Number(match[1])], message }))
    .filter(({ item }) => item)

  return (
    <>
      <Link to="/cart" className="back-link">
        ← Back to cart
      </Link>
      <div className="page-header">
        <div>
          <h1>Checkout</h1>
          <p>Ordering as {user.name}</p>
        </div>
      </div>

      <form className="two-column" onSubmit={handleSubmit} noValidate>
        <div className="stack">
          <ErrorMessage error={form.formError} />
          {form.fieldError('items') && <div className="alert alert-error">{form.fieldError('items')}</div>}
          {lineProblems.map(({ item, message }) => (
            <div key={item.productId} className="alert alert-error">
              <span>{message}</span>
              <button type="button" className="btn btn-sm btn-secondary" onClick={() => removeItem(item.productId)}>
                Remove {item.name}
              </button>
            </div>
          ))}

          <section className="card">
            <h2>Delivery details</h2>
            <div className="form">
              <FormField id="delivery_address" label="Delivery address" error={form.fieldError('delivery_address')}>
                <textarea
                  id="delivery_address"
                  rows={3}
                  autoComplete="street-address"
                  placeholder="Street, number, city, postcode"
                  {...form.bind('delivery_address')}
                />
              </FormField>
              <FormField
                id="contact_phone"
                label="Phone number"
                type="tel"
                autoComplete="tel"
                placeholder="+44 20 7946 0958"
                hint="The rider will call if they can’t find you."
                error={form.fieldError('contact_phone')}
                {...form.bind('contact_phone')}
              />
              <FormField id="notes" label="Notes for the kitchen (optional)" error={form.fieldError('notes')}>
                <textarea id="notes" rows={2} placeholder="Allergies, doorbell, …" {...form.bind('notes')} />
              </FormField>
            </div>
          </section>
        </div>

        <aside className="card sticky-card">
          <h2>Your order</h2>
          <ul className="checkout-lines">
            {items.map((item) => (
              <li key={item.productId}>
                <span>
                  <strong>{item.quantity}×</strong> {item.name}
                </span>
                <span>{money(item.price * item.quantity)}</span>
              </li>
            ))}
          </ul>
          <OrderSummary subtotal={subtotal} deliveryFee={deliveryFee} total={estimatedTotal} estimated>
            <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
              {form.submitting ? 'Placing order…' : 'Place order'}
            </button>
          </OrderSummary>
        </aside>
      </form>
    </>
  )
}
