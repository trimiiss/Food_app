import { Link, useNavigate } from 'react-router'
import { placeOrder } from '../api/orders'
import { EmptyState, ErrorMessage } from '../components/Feedback'
import FormField from '../components/FormField'
import FulfillmentToggle from '../components/FulfillmentToggle'
import OrderSummary from '../components/OrderSummary'
import PromoCodeField from '../components/PromoCodeField'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'
import { useShop } from '../context/ShopContext'
import { useCartPricing } from '../hooks/useCartPricing'
import { useForm } from '../hooks/useForm'

/**
 * Turns the client-side cart into a real order. Only product ids, quantities,
 * how the order is fulfilled and the promo code are sent; the response carries
 * the server's totals.
 */
export default function CheckoutPage() {
  const { user } = useAuth()
  const {
    items,
    subtotal,
    deliveryFee,
    estimatedTotal,
    promoCode,
    fulfillment,
    setFulfillment,
    isPickup,
    clearCart,
    removeItem,
  } = useCart()
  const { money, pickup_address: pickupAddress, pickup_ready_in_minutes: pickupReadyIn } = useShop()
  const navigate = useNavigate()
  const form = useForm({ delivery_address: '', contact_phone: '', notes: '' })
  const { pricing, promoError, loading: pricingLoading, error: pricingError } = useCartPricing()

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
      fulfillment_type: fulfillment,
      // A pickup has nowhere to be delivered to; the API stores null either way.
      delivery_address: isPickup ? null : values.delivery_address,
      contact_phone: values.contact_phone,
      notes: values.notes || null,
      // Only send a code the server just accepted, so a stale bad code can't
      // block checkout; it is validated again server-side regardless.
      promo_code: pricing?.promo_code ? promoCode : null,
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

  const totals = pricing ?? { subtotal, delivery_fee: deliveryFee, total: estimatedTotal, discount_total: 0, total_savings: 0 }

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
          <ErrorMessage error={form.formError ?? pricingError} />
          {form.fieldError('items') && <div className="alert alert-error">{form.fieldError('items')}</div>}
          {form.fieldError('promo_code') && <div className="alert alert-error">{form.fieldError('promo_code')}</div>}
          {lineProblems.map(({ item, message }) => (
            <div key={item.productId} className="alert alert-error">
              <span>{message}</span>
              <button type="button" className="btn btn-sm btn-secondary" onClick={() => removeItem(item.productId)}>
                Remove {item.name}
              </button>
            </div>
          ))}

          <section className="card">
            <h2>{isPickup ? 'Pickup details' : 'Delivery details'}</h2>
            <div className="form">
              <FulfillmentToggle value={fulfillment} onChange={setFulfillment} />

              {isPickup ? (
                // Nothing to ask for: we already know where the shop is.
                <div className="pickup-note">
                  <span className="pickup-note-icon" aria-hidden="true">
                    🛍️
                  </span>
                  <div>
                    <strong>Collect from {pickupAddress ?? 'our kitchen'}</strong>
                    <p className="muted small">
                      Usually ready about {pickupReadyIn} minutes after we confirm your order. We’ll call when it’s
                      waiting for you.
                    </p>
                  </div>
                </div>
              ) : (
                <FormField id="delivery_address" label="Delivery address" error={form.fieldError('delivery_address')}>
                  <textarea
                    id="delivery_address"
                    rows={3}
                    autoComplete="street-address"
                    placeholder="Street, number, city, postcode"
                    {...form.bind('delivery_address')}
                  />
                </FormField>
              )}

              <FormField
                id="contact_phone"
                label="Phone number"
                type="tel"
                autoComplete="tel"
                placeholder="+44 20 7946 0958"
                hint={isPickup ? 'We’ll call when your order is ready to collect.' : 'The rider will call if they can’t find you.'}
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
            {items.map((item) => {
              const priced = pricing?.lines.find((line) => line.product_id === item.productId)
              const unitPrice = priced?.unit_price ?? item.price
              return (
                <li key={item.productId}>
                  <span>
                    <strong>{item.quantity}×</strong> {item.name}
                  </span>
                  <span>{money(unitPrice * item.quantity)}</span>
                </li>
              )
            })}
          </ul>

          <PromoCodeField applied={pricing?.promo_code} error={promoError} loading={pricingLoading} />

          <OrderSummary
            subtotal={totals.subtotal}
            deliveryFee={totals.delivery_fee}
            discountTotal={totals.discount_total}
            promoCode={pricing?.promo_code?.code}
            total={totals.total}
            savings={totals.total_savings}
            fulfillmentType={pricing?.fulfillment_type ?? fulfillment}
          >
            <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting || pricingLoading}>
              {form.submitting ? 'Placing order…' : `Place order · ${money(totals.total)}`}
            </button>
          </OrderSummary>
        </aside>
      </form>
    </>
  )
}
