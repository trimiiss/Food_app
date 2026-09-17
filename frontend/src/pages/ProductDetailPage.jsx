import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { getProduct } from '../api/catalog'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import ProductImage from '../components/ProductImage'
import QuantityStepper from '../components/QuantityStepper'
import { useCart } from '../context/CartContext'
import { useShop } from '../context/ShopContext'
import { useAddToCart } from '../hooks/useAddToCart'
import { useApi } from '../hooks/useApi'

export default function ProductDetailPage() {
  const { slug } = useParams()
  const { money } = useShop()
  const { items, maxQuantity } = useCart()
  const { add, justAdded } = useAddToCart()
  const [quantity, setQuantity] = useState(1)

  const { data: product, error, loading, reload } = useApi((options) => getProduct(slug, options), [slug])

  if (loading) return <Loader />

  if (error?.status === 404) {
    return (
      <EmptyState icon="🥡" title="Dish not found">
        <p>This dish doesn’t exist or is currently unavailable.</p>
        <Link to="/" className="btn btn-primary">
          Back to the menu
        </Link>
      </EmptyState>
    )
  }

  if (error) return <ErrorMessage error={error} onRetry={reload} />

  const inCart = items.find((item) => item.productId === product.id)?.quantity ?? 0

  return (
    <>
      <Link to="/" className="back-link">
        ← Back to menu
      </Link>

      <article className="product-detail">
        <ProductImage src={product.image_url} alt={product.name} className="product-detail-image" />

        <div className="product-detail-body">
          {product.category && (
            <Link to={`/?category=${product.category.slug}`} className="product-card-category">
              {product.category.name}
            </Link>
          )}
          <h1>{product.name}</h1>
          <p className="price price-lg">{money(product.price)}</p>
          {product.description && <p className="product-detail-description">{product.description}</p>}

          <div className="row add-row">
            <QuantityStepper value={quantity} onChange={setQuantity} max={maxQuantity} />
            <button type="button" className="btn btn-primary" onClick={() => add(product, quantity)}>
              {justAdded ? 'Added ✓' : `Add to cart · ${money(product.price * quantity)}`}
            </button>
          </div>

          {inCart > 0 && (
            <p className="muted in-cart-note">
              {inCart} already in your cart · <Link to="/cart">View cart</Link>
            </p>
          )}
        </div>
      </article>
    </>
  )
}
