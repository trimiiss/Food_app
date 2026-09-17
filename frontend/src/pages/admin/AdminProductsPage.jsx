import { useEffect, useState } from 'react'
import { Link, useLocation, useSearchParams } from 'react-router'
import { deleteProduct, getAdminCategories, getAdminProducts, updateProduct } from '../../api/admin'
import { EmptyState, ErrorMessage, Loader } from '../../components/Feedback'
import Pagination from '../../components/Pagination'
import ProductImage from '../../components/ProductImage'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { pluralize } from '../../utils/format'

export default function AdminProductsPage() {
  const { money } = useShop()
  const location = useLocation()
  const [searchParams, setSearchParams] = useSearchParams()
  const category = searchParams.get('category') ?? ''
  const search = searchParams.get('search') ?? ''
  const page = Number(searchParams.get('page') ?? 1)

  const [busyId, setBusyId] = useState(null)
  const [actionError, setActionError] = useState(null)
  const [flash, setFlash] = useState(location.state?.flash ?? null)

  const categories = useApi((options) => getAdminCategories(options), [])
  const products = useApi(
    (options) => getAdminProducts({ category: category || undefined, search: search || undefined, page }, options),
    [category, search, page],
  )

  const [searchInput, setSearchInput] = useState(search)
  useEffect(() => setSearchInput(search), [search])
  useEffect(() => {
    if (searchInput.trim() === search) return undefined
    const timeout = setTimeout(() => updateParams({ search: searchInput.trim() }), 300)
    return () => clearTimeout(timeout)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchInput])

  function updateParams(changes) {
    const next = new URLSearchParams(searchParams)
    Object.entries(changes).forEach(([key, value]) => (value ? next.set(key, value) : next.delete(key)))
    if (!('page' in changes)) next.delete('page')
    setSearchParams(next)
  }

  const runAction = async (productId, action, successMessage) => {
    setBusyId(productId)
    setActionError(null)
    setFlash(null)
    try {
      await action()
      setFlash(successMessage)
      products.reload()
    } catch (error) {
      setActionError(error)
    } finally {
      setBusyId(null)
    }
  }

  // Quick "sold out" switch — the most common edit during service.
  const toggleAvailability = (product) =>
    runAction(
      product.id,
      () =>
        updateProduct(product.id, {
          category_id: product.category_id,
          name: product.name,
          slug: product.slug,
          description: product.description,
          price: product.price,
          image_url: product.image_url,
          is_available: !product.is_available,
        }),
      `“${product.name}” is now ${product.is_available ? 'sold out' : 'available'}.`,
    )

  const handleDelete = (product) => {
    if (!window.confirm(`Delete “${product.name}”? Past orders keep their copy of the name and price.`)) return
    runAction(product.id, () => deleteProduct(product.id), `“${product.name}” was deleted.`)
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Products</h1>
          <p>{products.data ? pluralize(products.data.meta.total, 'product') : 'Manage the menu'}</p>
        </div>
        <Link to="/admin/products/new" className="btn btn-primary">
          + New product
        </Link>
      </div>

      {flash && <div className="alert alert-success">{flash}</div>}
      <ErrorMessage error={actionError} />

      <div className="toolbar">
        <label className="toolbar-search">
          <span className="visually-hidden">Search products</span>
          <input
            type="search"
            className="input"
            placeholder="Search by name…"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
          />
        </label>
        <label>
          <span className="visually-hidden">Filter by category</span>
          <select className="input" value={category} onChange={(event) => updateParams({ category: event.target.value })}>
            <option value="">All categories</option>
            {categories.data?.map((item) => (
              <option key={item.id} value={item.slug}>
                {item.name}
              </option>
            ))}
          </select>
        </label>
      </div>

      {products.error && !products.data ? (
        <ErrorMessage error={products.error} onRetry={products.reload} />
      ) : !products.data ? (
        <Loader label="Loading products…" />
      ) : products.data.data.length === 0 ? (
        <EmptyState icon="🍽️" title="No products found">
          <p>{search || category ? 'Try a different search or category.' : 'Add your first dish to the menu.'}</p>
          <Link to="/admin/products/new" className="btn btn-primary">
            + New product
          </Link>
        </EmptyState>
      ) : (
        <>
          <div className={`table-wrap ${products.loading ? 'is-refreshing' : ''}`}>
            <table className="table">
              <thead>
                <tr>
                  <th aria-label="Image" />
                  <th>Name</th>
                  <th>Category</th>
                  <th className="num">Price</th>
                  <th>Status</th>
                  <th className="actions">Actions</th>
                </tr>
              </thead>
              <tbody>
                {products.data.data.map((product) => (
                  <tr key={product.id}>
                    <td className="thumb-cell">
                      <ProductImage src={product.image_url} alt="" className="thumb" />
                    </td>
                    <td>
                      <strong>{product.name}</strong>
                      <div className="muted small">/{product.slug}</div>
                    </td>
                    <td>{product.category?.name}</td>
                    <td className="num">{money(product.price)}</td>
                    <td>
                      <button
                        type="button"
                        className={`badge badge-button badge-${product.is_available ? 'available' : 'unavailable'}`}
                        onClick={() => toggleAvailability(product)}
                        disabled={busyId === product.id}
                        title="Click to toggle availability"
                      >
                        {product.is_available ? 'Available' : 'Sold out'}
                      </button>
                    </td>
                    <td className="actions">
                      <Link to={`/admin/products/${product.id}/edit`} className="btn btn-sm btn-secondary">
                        Edit
                      </Link>
                      <button
                        type="button"
                        className="btn btn-sm btn-danger"
                        onClick={() => handleDelete(product)}
                        disabled={busyId === product.id}
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={products.data.meta} onPageChange={(next) => updateParams({ page: String(next) })} />
        </>
      )}
    </>
  )
}
