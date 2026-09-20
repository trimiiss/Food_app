import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import { getCategories, getProducts, getPromotions } from '../api/catalog'
import CategorySidebar from '../components/CategorySidebar'
import { EmptyState, ErrorMessage, Loader } from '../components/Feedback'
import Pagination from '../components/Pagination'
import ProductCard from '../components/ProductCard'
import StoreHero from '../components/StoreHero'
import { useApi } from '../hooks/useApi'
import { pluralize } from '../utils/format'

/**
 * Public menu. Filters live in the URL (?category=&search=&page=) so a
 * filtered view can be bookmarked/shared and the back button undoes a filter.
 */
export default function ProductListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const category = searchParams.get('category') ?? ''
  const search = searchParams.get('search') ?? ''
  const page = Number(searchParams.get('page') ?? 1)

  const promotions = useApi((options) => getPromotions(options), [])
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

  const activeCategory = categories.data?.find((item) => item.slug === category)
  const totalAvailable = categories.data?.reduce((total, item) => total + item.products_count, 0)

  const selectCategory = (slug) => {
    updateParams({ category: slug })
    document.getElementById('menu')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  return (
    <>
      <StoreHero
        promotions={promotions.data}
        searchValue={searchInput}
        onSearchChange={setSearchInput}
        onSearchSubmit={() => updateParams({ search: searchInput.trim() })}
      />

      <div className="menu-layout" id="menu">
        <CategorySidebar
          categories={categories.data ?? []}
          activeSlug={category}
          onSelect={selectCategory}
          totalCount={totalAvailable}
        />

        <div className="menu-content">
          <div className="section-heading">
            <h2>{activeCategory ? activeCategory.name : search ? `Results for “${search}”` : 'Full menu'}</h2>
            {activeCategory?.description && <p className="muted">{activeCategory.description}</p>}
            {!activeCategory && products.data && (
              <p className="muted">{pluralize(products.data.meta.total, 'dish', 'dishes')} available today.</p>
            )}
          </div>

          <ErrorMessage error={categories.error} onRetry={categories.reload} />

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
        </div>
      </div>
    </>
  )
}
