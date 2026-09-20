import { useShop } from '../context/ShopContext'

/**
 * Price with its offer state: struck-through list price plus a "-20%" badge
 * when the dish is discounted. `product` comes straight from the API, which
 * decides what is on offer — the UI never works that out itself.
 */
export default function Price({ product, size = 'md', quantity = 1 }) {
  const { money } = useShop()

  if (!product.is_on_offer) {
    return <span className={`price ${size === 'lg' ? 'price-lg' : ''}`}>{money(product.price * quantity)}</span>
  }

  return (
    <span className="price-group">
      <span className={`price price-offer ${size === 'lg' ? 'price-lg' : ''}`}>
        {money(product.effective_price * quantity)}
      </span>
      <s className="price-was">{money(product.price * quantity)}</s>
      <span className="discount-badge">-{product.discount_percentage}%</span>
    </span>
  )
}
