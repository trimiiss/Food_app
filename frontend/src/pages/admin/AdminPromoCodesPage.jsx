import { useState } from 'react'
import { createPromoCode, deletePromoCode, getAdminPromoCodes, updatePromoCode } from '../../api/admin'
import { ErrorMessage, Loader } from '../../components/Feedback'
import FormField from '../../components/FormField'
import { useShop } from '../../context/ShopContext'
import { useApi } from '../../hooks/useApi'
import { useForm } from '../../hooks/useForm'
import { formatDate } from '../../utils/format'

const EMPTY = {
  code: '',
  description: '',
  type: 'percent',
  value: '10',
  min_subtotal: '',
  starts_at: '',
  ends_at: '',
  max_uses: '',
  is_active: true,
  is_public: true,
}

/** ISO timestamp -> the "YYYY-MM-DDTHH:mm" a datetime-local input expects. */
function toDateTimeInput(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

export default function AdminPromoCodesPage() {
  const { money } = useShop()
  const promoCodes = useApi((options) => getAdminPromoCodes(options), [])
  const codes = promoCodes.data?.data
  const types = promoCodes.data?.types
  const form = useForm(EMPTY)
  const [editing, setEditing] = useState(null)
  const [flash, setFlash] = useState(null)
  const [actionError, setActionError] = useState(null)

  const isFreeDelivery = form.values.type === 'free_delivery'

  const startEdit = (promo) => {
    setEditing(promo)
    setFlash(null)
    form.setValues({
      code: promo.code,
      description: promo.description ?? '',
      type: promo.type,
      value: String(promo.value),
      min_subtotal: promo.min_subtotal ? String(promo.min_subtotal) : '',
      starts_at: toDateTimeInput(promo.starts_at),
      ends_at: toDateTimeInput(promo.ends_at),
      max_uses: promo.max_uses == null ? '' : String(promo.max_uses),
      is_active: promo.is_active,
      is_public: promo.is_public,
    })
  }

  const resetForm = () => {
    setEditing(null)
    form.setValues(EMPTY)
  }

  const handleSubmit = form.submit(async (values) => {
    const payload = {
      ...values,
      description: values.description || null,
      min_subtotal: values.min_subtotal || 0,
      starts_at: values.starts_at || null,
      ends_at: values.ends_at || null,
      max_uses: values.max_uses === '' ? null : Number(values.max_uses),
    }
    const saved = editing ? await updatePromoCode(editing.id, payload) : await createPromoCode(payload)
    setFlash(`${saved.code} was ${editing ? 'updated' : 'created'}.`)
    setActionError(null)
    resetForm()
    promoCodes.reload()
  })

  const handleDelete = async (promo) => {
    if (!window.confirm(`Delete ${promo.code}? Past orders keep the code they used.`)) return
    setFlash(null)
    setActionError(null)
    try {
      await deletePromoCode(promo.id)
      setFlash(`${promo.code} was deleted.`)
      if (editing?.id === promo.id) resetForm()
      promoCodes.reload()
    } catch (error) {
      setActionError(error)
    }
  }

  const statusOf = (promo) => {
    if (!promo.is_active) return { label: 'Inactive', className: 'badge-cancelled' }
    if (!promo.is_redeemable) return { label: 'Not usable', className: 'badge-cancelled' }
    return { label: promo.is_public ? 'Live' : 'Live · private', className: 'badge-available' }
  }

  return (
    <>
      <div className="page-header">
        <div>
          <h1>Promo codes</h1>
          <p>Discounts customers can enter at checkout.</p>
        </div>
      </div>

      {flash && <div className="alert alert-success">{flash}</div>}
      <ErrorMessage error={actionError} />

      <div className="promo-layout">
        <div>
          {promoCodes.error && !promoCodes.data ? (
            <ErrorMessage error={promoCodes.error} onRetry={promoCodes.reload} />
          ) : !codes ? (
            <Loader />
          ) : (
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th className="num">Min order</th>
                    <th>Valid</th>
                    <th className="num">Used</th>
                    <th>Status</th>
                    <th className="actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {codes.length === 0 && (
                    <tr>
                      <td colSpan={7} className="muted">
                        No promo codes yet — create one with the form.
                      </td>
                    </tr>
                  )}
                  {codes.map((promo) => {
                    const status = statusOf(promo)
                    return (
                      <tr key={promo.id} className={editing?.id === promo.id ? 'is-selected' : ''}>
                        <td>
                          <strong className="order-number">{promo.code}</strong>
                          {promo.description && <div className="muted small promo-description">{promo.description}</div>}
                        </td>
                        <td>{promo.summary}</td>
                        <td className="num">{promo.min_subtotal > 0 ? money(promo.min_subtotal) : '—'}</td>
                        <td className="small nowrap">
                          {promo.starts_at && <div>from {formatDate(promo.starts_at)}</div>}
                          {promo.ends_at ? <div>until {formatDate(promo.ends_at)}</div> : <div className="muted">—</div>}
                        </td>
                        <td className="num">
                          {promo.uses_count}
                          {promo.max_uses != null && ` / ${promo.max_uses}`}
                        </td>
                        <td>
                          <span className={`badge ${status.className}`}>{status.label}</span>
                        </td>
                        <td className="actions">
                          <button type="button" className="btn btn-sm btn-secondary" onClick={() => startEdit(promo)}>
                            Edit
                          </button>
                          <button type="button" className="btn btn-sm btn-danger" onClick={() => handleDelete(promo)}>
                            Delete
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <aside className="card promo-form">
          <h2>{editing ? `Edit ${editing.code}` : 'New promo code'}</h2>
          <form className="form" onSubmit={handleSubmit} noValidate>
            <ErrorMessage error={form.formError} />

            <FormField
              label="Code"
              placeholder="SUMMER20"
              hint="Letters, numbers, dashes. Stored upper-cased."
              error={form.fieldError('code')}
              {...form.bind('code')}
            />
            <FormField label="Description" error={form.fieldError('description')} {...form.bind('description')} />

            <div className="form-grid">
              <FormField id="promo_type" label="Type" error={form.fieldError('type')}>
                <select id="promo_type" {...form.bind('type')}>
                  {/* Options come from the API's enum, not a hardcoded copy. */}
                  {(types ?? []).map((type) => (
                    <option key={type.value} value={type.value}>
                      {type.label}
                    </option>
                  ))}
                </select>
              </FormField>
              <FormField
                label={form.values.type === 'percent' ? 'Percentage' : 'Amount'}
                type="number"
                step="0.01"
                min="0"
                disabled={isFreeDelivery}
                hint={isFreeDelivery ? 'Not used for free delivery.' : undefined}
                error={form.fieldError('value')}
                {...form.bind('value')}
              />
            </div>

            <div className="form-grid">
              <FormField
                label="Minimum order"
                type="number"
                step="0.01"
                min="0"
                placeholder="0"
                error={form.fieldError('min_subtotal')}
                {...form.bind('min_subtotal')}
              />
              <FormField
                label="Usage limit"
                type="number"
                min="1"
                placeholder="Unlimited"
                error={form.fieldError('max_uses')}
                {...form.bind('max_uses')}
              />
            </div>

            <div className="form-grid">
              <FormField
                label="Starts"
                type="datetime-local"
                error={form.fieldError('starts_at')}
                {...form.bind('starts_at')}
              />
              <FormField label="Ends" type="datetime-local" error={form.fieldError('ends_at')} {...form.bind('ends_at')} />
            </div>

            <label className="checkbox">
              <input type="checkbox" checked={form.values.is_active} onChange={form.bind('is_active').onChange} />
              Active
            </label>
            <label className="checkbox">
              <input type="checkbox" checked={form.values.is_public} onChange={form.bind('is_public').onChange} />
              Show on the Deals page
            </label>

            <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
              {form.submitting ? 'Saving…' : editing ? 'Save changes' : 'Create promo code'}
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
