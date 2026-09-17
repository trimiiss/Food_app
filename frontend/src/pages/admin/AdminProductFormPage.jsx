import { useEffect } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { createProduct, getAdminCategories, getAdminProduct, updateProduct } from '../../api/admin'
import { EmptyState, ErrorMessage, Loader } from '../../components/Feedback'
import FormField from '../../components/FormField'
import ProductImage from '../../components/ProductImage'
import { useApi } from '../../hooks/useApi'
import { useForm } from '../../hooks/useForm'

const EMPTY_PRODUCT = {
  name: '',
  slug: '',
  category_id: '',
  price: '',
  image_url: '',
  description: '',
  is_available: true,
}

/** Create (/admin/products/new) and edit (/admin/products/:id/edit) share this form. */
export default function AdminProductFormPage() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const form = useForm(EMPTY_PRODUCT)

  const categories = useApi((options) => getAdminCategories(options), [])
  const product = useApi((options) => (isEdit ? getAdminProduct(id, options) : Promise.resolve(null)), [id])

  // Populate the form once the product to edit has loaded.
  const { setValues } = form
  useEffect(() => {
    if (!product.data) return
    setValues({
      name: product.data.name,
      slug: product.data.slug,
      category_id: String(product.data.category_id),
      price: String(product.data.price),
      image_url: product.data.image_url ?? '',
      description: product.data.description ?? '',
      is_available: product.data.is_available,
    })
  }, [product.data, setValues])

  if (categories.loading || product.loading) return <Loader />
  if (product.error?.status === 404) {
    return (
      <EmptyState icon="🍽️" title="Product not found">
        <Link to="/admin/products" className="btn btn-primary">
          Back to products
        </Link>
      </EmptyState>
    )
  }
  if (categories.error || product.error) {
    return <ErrorMessage error={categories.error ?? product.error} onRetry={isEdit ? product.reload : categories.reload} />
  }

  if (categories.data.length === 0) {
    return (
      <EmptyState icon="🗂️" title="Create a category first">
        <p>Every product belongs to a category.</p>
        <Link to="/admin/categories" className="btn btn-primary">
          Manage categories
        </Link>
      </EmptyState>
    )
  }

  const handleSubmit = form.submit(async (values) => {
    const payload = {
      ...values,
      category_id: values.category_id ? Number(values.category_id) : null,
      slug: values.slug || null, // blank -> derived from the name by the API
      image_url: values.image_url || null,
      description: values.description || null,
    }
    const saved = isEdit ? await updateProduct(id, payload) : await createProduct(payload)
    navigate('/admin/products', {
      state: { flash: `“${saved.name}” was ${isEdit ? 'updated' : 'created'}.` },
    })
  })

  return (
    <>
      <Link to="/admin/products" className="back-link">
        ← Products
      </Link>
      <div className="page-header">
        <div>
          <h1>{isEdit ? `Edit ${product.data?.name ?? 'product'}` : 'New product'}</h1>
          <p>{isEdit ? 'Changes apply to future orders only.' : 'Add a dish to the menu.'}</p>
        </div>
      </div>

      <form className="admin-form-grid" onSubmit={handleSubmit} noValidate>
        <div className="card form">
          <ErrorMessage error={form.formError} />
          <FormField label="Name" error={form.fieldError('name')} {...form.bind('name')} />
          <FormField
            label="URL slug"
            hint="Optional — generated from the name if left blank."
            placeholder="e.g. margherita"
            error={form.fieldError('slug')}
            {...form.bind('slug')}
          />

          <div className="form-grid">
            <FormField id="category_id" label="Category" error={form.fieldError('category_id')}>
              <select id="category_id" {...form.bind('category_id')}>
                <option value="">Choose a category…</option>
                {categories.data.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </FormField>
            <FormField
              label="Price"
              type="number"
              inputMode="decimal"
              min="0.01"
              step="0.01"
              error={form.fieldError('price')}
              {...form.bind('price')}
            />
          </div>

          <FormField id="description" label="Description" error={form.fieldError('description')}>
            <textarea id="description" rows={4} {...form.bind('description')} />
          </FormField>

          <label className="checkbox">
            <input type="checkbox" checked={form.values.is_available} onChange={form.bind('is_available').onChange} />
            Available to order
          </label>
          {form.fieldError('is_available') && <span className="field-error">{form.fieldError('is_available')}</span>}
        </div>

        <aside className="card form">
          <FormField
            label="Image URL"
            type="url"
            placeholder="https://…"
            error={form.fieldError('image_url')}
            {...form.bind('image_url')}
          />
          <div>
            <span className="field-label">Preview</span>
            {/* Only preview http(s) URLs — never feed javascript:/data: text straight into <img src>. */}
            <ProductImage
              src={/^https?:\/\//i.test(form.values.image_url) ? form.values.image_url : null}
              alt="Preview"
              className="preview-image"
            />
          </div>
          <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
            {form.submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create product'}
          </button>
        </aside>
      </form>
    </>
  )
}
