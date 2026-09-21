import { Link } from 'react-router'
import { useAddToCart } from '../hooks/useAddToCart'
import Price from './Price'
import ProductImage from './ProductImage'

const DAY_MS = 24 * 60 * 60 * 1000

/** "5 days left" for a timed offer; null for an open-ended or lapsed one. */
function offerTimeLeft(endsAt) {
  if (!endsAt) return null
  const days = Math.ceil((new Date(endsAt).getTime() - Date.now()) / DAY_MS)
  if (days <= 0) return null
  return days === 1 ? 'Last day' : `${days} days left`
}

export default function ProductCard({ product }) {
  const { add, justAdded } = useAddToCart()
  const timeLeft = product.is_on_offer ? offerTimeLeft(product.discount_ends_at) : null

  return (
    <article className={`product-card ${product.is_on_offer ? 'is-on-offer' : ''}`}>
      <Link to={`/products/${product.slug}`} className="product-card-media" tabIndex={-1} aria-hidden="true">
        <ProductImage src={product.image_url} alt="" />
        {product.is_on_offer && <span className="ribbon">-{product.discount_percentage}%</span>}
        {timeLeft && <span className="offer-timer">⏱ {timeLeft}</span>}
      </Link>

      <div className="product-card-body">
        {product.category && <span className="product-card-category">{product.category.name}</span>}
        <h3 className="product-card-title">
          <Link to={`/products/${product.slug}`}>{product.name}</Link>
        </h3>
        {product.description && <p className="product-card-description">{product.description}</p>}

        <div className="product-card-footer">
          <Price product={product} />
          <button
            type="button"
            className={`btn btn-sm ${justAdded ? 'btn-secondary' : 'btn-primary'}`}
            onClick={() => add(product)}
            aria-live="polite"
          >
            {justAdded ? 'Added ✓' : 'Add to cart'}
          </button>
        </div>
      </div>
    </article>
  )
}
