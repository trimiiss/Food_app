import { Link } from 'react-router'
import { useShop } from '../context/ShopContext'
import { useAddToCart } from '../hooks/useAddToCart'
import ProductImage from './ProductImage'

export default function ProductCard({ product }) {
  const { money } = useShop()
  const { add, justAdded } = useAddToCart()

  return (
    <article className="product-card">
      <Link to={`/products/${product.slug}`} className="product-card-media" tabIndex={-1} aria-hidden="true">
        <ProductImage src={product.image_url} alt="" />
      </Link>

      <div className="product-card-body">
        {product.category && <span className="product-card-category">{product.category.name}</span>}
        <h3 className="product-card-title">
          <Link to={`/products/${product.slug}`}>{product.name}</Link>
        </h3>
        {product.description && <p className="product-card-description">{product.description}</p>}

        <div className="product-card-footer">
          <span className="price">{money(product.price)}</span>
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
