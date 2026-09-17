import { useState } from 'react'
import { createCategory, deleteCategory, getAdminCategories, updateCategory } from '../../api/admin'
import { ErrorMessage, Loader } from '../../components/Feedback'
import FormField from '../../components/FormField'
import { useApi } from '../../hooks/useApi'
import { useForm } from '../../hooks/useForm'

const EMPTY_CATEGORY = { name: '', slug: '', description: '' }

/**
 * Categories are small (name, slug, description), so create and edit happen
 * in one inline form next to the list instead of on separate pages.
 */
export default function AdminCategoriesPage() {
  const categories = useApi((options) => getAdminCategories(options), [])
  const form = useForm(EMPTY_CATEGORY)
  const [editing, setEditing] = useState(null)
  const [flash, setFlash] = useState(null)
  const [deleteError, setDeleteError] = useState(null)

  const startEdit = (category) => {
    setEditing(category)
    setFlash(null)
    form.setValues({ name: category.name, slug: category.slug, description: category.description ?? '' })
  }

  const resetForm = () => {
    setEditing(null)
    form.setValues(EMPTY_CATEGORY)
  }

  const handleSubmit = form.submit(async (values) => {
    const payload = { ...values, slug: values.slug || null, description: values.description || null }
    const saved = editing ? await updateCategory(editing.id, payload) : await createCategory(payload)
    setFlash(`“${saved.name}” was ${editing ? 'updated' : 'created'}.`)
    setDeleteError(null)
    resetForm()
    categories.reload()
  })

  const handleDelete = async (category) => {
    if (!window.confirm(`Delete the “${category.name}” category?`)) return
    setFlash(null)
    setDeleteError(null)
    try {
      await deleteCategory(category.id)
      setFlash(`“${category.name}” was deleted.`)
      if (editing?.id === category.id) resetForm()
      categories.reload()
    } catch (error) {
      // 409 when it still has products — the API message explains what to do.
      setDeleteError(error)
    }
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Categories</h1>
          <p>Group dishes on the menu.</p>
        </div>
      </div>

      {flash && <div className="alert alert-success">{flash}</div>}
      <ErrorMessage error={deleteError} />

      <div className="admin-form-grid">
        <div>
          {categories.error && !categories.data ? (
            <ErrorMessage error={categories.error} onRetry={categories.reload} />
          ) : !categories.data ? (
            <Loader />
          ) : (
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Description</th>
                    <th className="num">Products</th>
                    <th className="actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {categories.data.length === 0 && (
                    <tr>
                      <td colSpan={4} className="muted">
                        No categories yet — create one with the form.
                      </td>
                    </tr>
                  )}
                  {categories.data.map((category) => (
                    <tr key={category.id} className={editing?.id === category.id ? 'is-selected' : ''}>
                      <td>
                        <strong>{category.name}</strong>
                        <div className="muted small">/{category.slug}</div>
                      </td>
                      <td className="muted">{category.description}</td>
                      <td className="num">{category.products_count}</td>
                      <td className="actions">
                        <button type="button" className="btn btn-sm btn-secondary" onClick={() => startEdit(category)}>
                          Edit
                        </button>
                        <button type="button" className="btn btn-sm btn-danger" onClick={() => handleDelete(category)}>
                          Delete
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <aside className="card">
          <h2>{editing ? `Edit “${editing.name}”` : 'New category'}</h2>
          <form className="form" onSubmit={handleSubmit} noValidate>
            <ErrorMessage error={form.formError} />
            <FormField label="Name" error={form.fieldError('name')} {...form.bind('name')} />
            <FormField
              label="URL slug"
              hint="Optional — generated from the name."
              error={form.fieldError('slug')}
              {...form.bind('slug')}
            />
            <FormField id="category_description" label="Description" error={form.fieldError('description')}>
              <textarea id="category_description" rows={3} {...form.bind('description')} />
            </FormField>
            <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
              {form.submitting ? 'Saving…' : editing ? 'Save changes' : 'Create category'}
            </button>
            {editing && (
              <button type="button" className="btn btn-ghost btn-block" onClick={resetForm}>
                Cancel editing
              </button>
            )}
          </form>
        </aside>
      </div>
    </>
  )
}
