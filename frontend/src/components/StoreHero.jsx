import { Link } from 'react-router'
import { useShop } from '../context/ShopContext'

// Decorative food photos (checked to load). Purely presentational.
const DECOR = [
  '1574071318508-1cdbab80d002', // pizza
  '1568901346375-23c9450c58cd', // burger
  '1541592106381-b31e9677c0e5', // fries
  '1600271886742-f049cd451bba', // juice
]

/**
 * Picks the promotion worth shouting about: free delivery first (it reads best
 * as a headline), then the biggest percentage, then a fixed amount.
 */
function headlinePromo(promotions) {
  // `promotions` is null until the request resolves — a default parameter only
  // covers undefined, so normalise here.
  const list = promotions ?? []
  const byType = (type) => list.filter((promo) => promo.type === type)
  const biggest = (list) => [...list].sort((a, b) => b.value - a.value)[0]

  return byType('free_delivery')[0] ?? biggest(byType('percent')) ?? biggest(byType('fixed')) ?? null
}

/**
 * Full-bleed storefront hero: the current headline offer, a search box and the
 * shortcuts a first-time visitor needs.
 */
export default function StoreHero({ promotions, searchValue, onSearchChange, onSearchSubmit }) {
  const { moneyShort } = useShop()
  const promo = headlinePromo(promotions)

  let headline = 'Food you love, delivered fast'
  let subline = 'Fresh pizza, burgers, pasta and more — hot to your door.'

  if (promo) {
    const minimum = promo.min_subtotal > 0 ? ` on orders over ${moneyShort(promo.min_subtotal)}` : ''
    headline =
      promo.type === 'free_delivery'
        ? `Free delivery${minimum}`
        : promo.type === 'percent'
          ? `${promo.summary} your order${minimum}`
          : `${promo.summary}${minimum}`
    subline = `Use code ${promo.code} at checkout · other offers apply`
  }

  return (
    <section className="store-hero">
      <div className="store-hero-decor" aria-hidden="true">
        {DECOR.map((photo, index) => (
          <img
            key={photo}
            className={`store-hero-photo photo-${index + 1}`}
            src={`https://images.unsplash.com/photo-${photo}?w=320&h=320&q=70&auto=format&fit=crop`}
            alt=""
            loading="lazy"
          />
        ))}
      </div>

      <div className="store-hero-inner">
        <p className="store-hero-brand">
          <span aria-hidden="true">🍴</span> LeuEats
        </p>
        <h1 className="store-hero-title">{headline}</h1>
        <p className="store-hero-sub">{subline}</p>

        <form
          className="store-hero-search"
          onSubmit={(event) => {
            event.preventDefault()
            onSearchSubmit?.()
          }}
          role="search"
        >
          <label className="visually-hidden" htmlFor="hero-search">
            Search the menu
          </label>
          <span className="store-hero-search-icon" aria-hidden="true">
            🔎
          </span>
          <input
            id="hero-search"
            type="search"
            placeholder="Search dishes — pizza, burger, pasta…"
            value={searchValue}
            onChange={(event) => onSearchChange(event.target.value)}
          />
          <button type="submit" className="store-hero-search-button" aria-label="Search">
            →
          </button>
        </form>

        <div className="store-hero-actions">
          <Link to="/deals" className="btn btn-hero-primary">
            See today’s deals 🔥
          </Link>
          <a href="#menu" className="btn btn-hero-ghost">
            Browse the menu
          </a>
        </div>
      </div>
    </section>
  )
}
