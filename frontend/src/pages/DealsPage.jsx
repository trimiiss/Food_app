import { useState } from 'react'
import { Link } from 'react-router'
import { getProducts, getPromotions } from '../api/catalog'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import ProductCard from '../components/ProductCard'
import { useShop } from '../context/ShopContext'
import { useApi } from '../hooks/useApi'
import { formatDateTime, pluralize } from '../utils/format'

/** Everything currently discounted: promo codes plus dishes on offer. */
export default function DealsPage() {
  const { money } = useShop()
  const [copiedCode, setCopiedCode] = useState(null)

  const promotions = useApi((options) => getPromotions(options), [])
  const products = useApi((options) => getProducts({ on_offer: 1, per_page: 100 }, options), [])

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
      <section className="hero hero-deals">
        <div>
          <h1>Deals &amp; offers 🔥</h1>
          <p>Discounted dishes and codes you can use at checkout.</p>
        </div>
      </section>

      <section className="section-heading">
        <h2>Promo codes</h2>
        <p className="muted">Enter the code in your cart or at checkout.</p>
      </section>

      {promotions.error ? (
        <ErrorMessage error={promotions.error} onRetry={promotions.reload} />
      ) : promotions.loading && !promotions.data ? (
        <Loader label="Loading offers…" />
      ) : promotions.data.length === 0 ? (
        <p className="muted">No promo codes are running right now.</p>
      ) : (
        <ul className="promo-grid">
          {promotions.data.map((promo) => (
            <li key={promo.id} className="promo-card">
              <span className="promo-summary">{promo.summary}</span>
              {promo.description && <span className="muted">{promo.description}</span>}
              <span className="promo-terms">
                {promo.min_subtotal > 0 ? `Minimum order ${money(promo.min_subtotal)}` : 'No minimum order'}
                {promo.ends_at ? ` · ends ${formatDateTime(promo.ends_at)}` : ''}
              </span>
              <button type="button" className="promo-code" onClick={() => copyCode(promo.code)}>
                <code>{promo.code}</code>
                <span className="promo-copy">{copiedCode === promo.code ? 'Copied ✓' : 'Copy'}</span>
              </button>
            </li>
          ))}
        </ul>
      )}

      <section className="section-heading deals-products-heading">
        <h2>Dishes on offer</h2>
        {products.data && <p className="muted">{pluralize(products.data.meta.total, 'dish', 'dishes')} discounted right now.</p>}
      </section>

      {products.error ? (
        <ErrorMessage error={products.error} onRetry={products.reload} />
      ) : products.loading && !products.data ? (
        <Loader label="Loading deals…" />
      ) : products.data.data.length === 0 ? (
        <EmptyState icon="🏷️" title="No dishes on offer">
          <p>Check back soon — offers change regularly.</p>
          <Link to="/" className="btn btn-primary">
            Browse the full menu
          </Link>
        </EmptyState>
      ) : (
        <div className="product-grid">
          {products.data.data.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}
    </>
  )
}
