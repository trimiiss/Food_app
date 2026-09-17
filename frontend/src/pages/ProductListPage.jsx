import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import { getCategories, getProducts } from '../api/catalog'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import Pagination from '../components/Pagination'
import ProductCard from '../components/ProductCard'
import { useApi } from '../hooks/useApi'

/**
 * Public menu. Filters live in the URL (?category=&search=&page=) so a
 * filtered view can be bookmarked/shared and the back button undoes a filter.
 */
export default function ProductListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const category = searchParams.get('category') ?? ''
  const search = searchParams.get('search') ?? ''
  const page = Number(searchParams.get('page') ?? 1)

  const categories = useApi((options) => getCategories(options), [])
  const products = useApi(
    (options) => getProducts({ category: category || undefined, search: search || undefined, page }, options),
    [category, search, page],
  )

  const updateParams = (changes) => {
    const next = new URLSearchParams(searchParams)
    Object.entries(changes).forEach(([key, value]) => (value ? next.set(key, value) : next.delete(key)))
    // Any filter change starts again from page 1.
    if (!('page' in changes)) next.delete('page')
    setSearchParams(next)
  }

  // Debounce typing so we don't fire a request per keystroke.
  const [searchInput, setSearchInput] = useState(search)
  useEffect(() => setSearchInput(search), [search])
  useEffect(() => {
    if (searchInput.trim() === search) return undefined
    const timeout = setTimeout(() => updateParams({ search: searchInput.trim() }), 300)
    return () => clearTimeout(timeout)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput])

  const activeCategory = categories.data?.find((c) => c.slug === category)

  return (
    <>
      <section className="hero">
        <div>
          <h1>Hungry? We’ve got you.</h1>
          <p>Fresh pizza, burgers, pasta and more — delivered hot to your door.</p>
        </div>
        <label className="search">
          <span className="visually-hidden">Search the menu</span>
          <input
            type="search"
            className="input"
            placeholder="Search dishes…"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
          />
        </label>
      </section>

      <nav className="chips" aria-label="Categories">
        <button
          type="button"
          className={`chip ${category === '' ? 'active' : ''}`}
          onClick={() => updateParams({ category: '' })}
          aria-pressed={category === ''}
        >
          All
        </button>
        {categories.data?.map((item) => (
          <button
            key={item.id}
            type="button"
            className={`chip ${category === item.slug ? 'active' : ''}`}
            onClick={() => updateParams({ category: item.slug })}
            aria-pressed={category === item.slug}
          >
            {item.name}
            <span className="chip-count">{item.products_count}</span>
          </button>
        ))}
      </nav>
      <ErrorMessage error={categories.error} onRetry={categories.reload} />

      <div className="section-heading">
        <h2>{activeCategory ? activeCategory.name : 'Full menu'}</h2>
        {activeCategory?.description && <p className="muted">{activeCategory.description}</p>}
      </div>

      {products.error ? (
        <ErrorMessage error={products.error} onRetry={products.reload} />
      ) : products.loading && !products.data ? (
        <Loader label="Loading the menu…" />
      ) : products.data?.data.length === 0 ? (
        <EmptyState icon="🔍" title="No dishes found">
          <p>Try a different search or category.</p>
          <button type="button" className="btn btn-secondary" onClick={() => setSearchParams({})}>
            Clear filters
          </button>
        </EmptyState>
      ) : (
        <>
          <div className={`product-grid ${products.loading ? 'is-refreshing' : ''}`}>
            {products.data?.data.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
          <Pagination meta={products.data?.meta} onPageChange={(next) => updateParams({ page: String(next) })} />
        </>
      )}
    </>
  )
}
