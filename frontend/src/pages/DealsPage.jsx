import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import { getProducts, getPromotions } from '../api/catalog'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import ProductCard from '../components/ProductCard'
import { useShop } from '../context/ShopContext'
import { useApi } from '../hooks/useApi'
import { formatDateTime, pluralize } from '../utils/format'

/** The big figure on the left of a coupon: "10%", "€5", "FREE". */
function couponValue(promo, moneyShort) {
  switch (promo.type) {
    case 'percent':
      return { amount: `${promo.value}%`, unit: 'off' }
    case 'fixed':
      return { amount: moneyShort(promo.value), unit: 'off' }
    default:
      return { amount: 'Free', unit: 'delivery' }
  }
}

/** Everything currently discounted: promo codes plus dishes on offer. */
export default function DealsPage() {
  const { money, moneyShort } = useShop()
  const [copiedCode, setCopiedCode] = useState(null)
  const [category, setCategory] = useState('')

  const promotions = useApi((options) => getPromotions(options), [])
  const products = useApi((options) => getProducts({ on_offer: 1, per_page: 100 }, options), [])

  // Biggest saving first, so the best deals are the first thing people see.
  const dishes = useMemo(
    () => [...(products.data?.data ?? [])].sort((a, b) => b.discount_percentage - a.discount_percentage),
    [products.data],
  )

  // Only the categories that actually have something on offer.
  const categories = useMemo(() => {
    const seen = new Map()
    dishes.forEach((dish) => dish.category && seen.set(dish.category.slug, dish.category.name))
    return [...seen].map(([slug, name]) => ({ slug, name })).sort((a, b) => a.name.localeCompare(b.name))
  }, [dishes])

  const visible = category ? dishes.filter((dish) => dish.category?.slug === category) : dishes

  const copyCode = async (code) => {
    try {
      await navigator.clipboard.writeText(code)
      setCopiedCode(code)
      setTimeout(() => setCopiedCode(null), 1500)
    } catch {
      // Clipboard blocked (permissions, insecure context) — the code is on screen anyway.
    }
  }

  return (
    <>
      <section className="store-hero deals-hero">
        <div className="store-hero-decor" aria-hidden="true">
          {dishes.slice(0, 4).map((dish, index) => (
            <img key={dish.id} className={`store-hero-photo photo-${index + 1}`} src={dish.image_url} alt="" />
          ))}
        </div>

        <div className="store-hero-inner">
          <p className="store-hero-brand">
            <span aria-hidden="true">🔥</span> Deals &amp; offers
          </p>
          <h1 className="store-hero-title">Hot deals, every day</h1>
          <p className="store-hero-sub">Discounted dishes and promo codes you can use at checkout.</p>
        </div>
      </section>

      <section className="deals-section">
        <div className="deals-section-head">
          <h2>Promo codes</h2>
          <p className="muted">Tap a code to copy it, then enter it in your cart or at checkout.</p>
        </div>

        {promotions.error ? (
          <ErrorMessage error={promotions.error} onRetry={promotions.reload} />
        ) : promotions.loading && !promotions.data ? (
          <Loader label="Loading offers…" />
        ) : promotions.data.length === 0 ? (
          <p className="muted">No promo codes are running right now.</p>
        ) : (
          <ul className="coupon-grid">
            {promotions.data.map((promo) => {
              const { amount, unit } = couponValue(promo, moneyShort)
              const copied = copiedCode === promo.code

              return (
                <li key={promo.id} className="coupon">
                  <div className="coupon-value" aria-hidden="true">
                    <span className="coupon-amount">{amount}</span>
                    <span className="coupon-unit">{unit}</span>
                  </div>
                  <div className="coupon-body">
                    <span className="coupon-title">{promo.summary}</span>
                    {promo.description && <span className="coupon-description">{promo.description}</span>}
                    <span className="coupon-terms">
                      {promo.min_subtotal > 0 ? `Minimum order ${money(promo.min_subtotal)}` : 'No minimum order'}
                      {promo.ends_at ? ` · ends ${formatDateTime(promo.ends_at)}` : ''}
                    </span>
                    <button
                      type="button"
                      className={`coupon-code ${copied ? 'is-copied' : ''}`}
                      onClick={() => copyCode(promo.code)}
                      aria-label={`Copy code ${promo.code}`}
                    >
                      <code>{promo.code}</code>
                      <span className="coupon-copy">{copied ? 'Copied ✓' : 'Copy'}</span>
                    </button>
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </section>

      <section className="deals-section">
        <div className="deals-section-head">
          <h2>Dishes on offer</h2>
          {products.data && (
            <p className="muted">
              {pluralize(dishes.length, 'dish', 'dishes')} discounted right now, biggest savings first.
            </p>
          )}
        </div>

        {products.error ? (
          <ErrorMessage error={products.error} onRetry={products.reload} />
        ) : products.loading && !products.data ? (
          <Loader label="Loading deals…" />
        ) : dishes.length === 0 ? (
          <EmptyState icon="🏷️" title="No dishes on offer">
            <p>Check back soon — offers change regularly.</p>
            <Link to="/" className="btn btn-primary">
              Browse the full menu
            </Link>
          </EmptyState>
        ) : (
          <>
            {categories.length > 1 && (
              <div className="deals-filters" role="group" aria-label="Filter deals by category">
                <button
                  type="button"
                  className={`chip ${category === '' ? 'active' : ''}`}
                  aria-pressed={category === ''}
                  onClick={() => setCategory('')}
                >
                  All deals
                </button>
                {categories.map((item) => (
                  <button
                    key={item.slug}
                    type="button"
                    className={`chip ${category === item.slug ? 'active' : ''}`}
                    aria-pressed={category === item.slug}
                    onClick={() => setCategory(item.slug)}
                  >
                    {item.name}
                  </button>
                ))}
              </div>
            )}

            <div className="product-grid">
              {visible.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </>
        )}
      </section>
    </>
  )
}
