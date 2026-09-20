import client from './client'

/*
 * Admin endpoints (/api/v1/admin/*). The API enforces auth:sanctum + admin on
 * every one of these; the SPA's RequireAdmin guard is only for UX.
 */

export const getStats = (options) => client.get('/admin/stats', options).then((r) => r.data.data)

// ---- Categories -----------------------------------------------------------
export const getAdminCategories = (options) => client.get('/admin/categories', options).then((r) => r.data.data)
export const createCategory = (payload) => client.post('/admin/categories', payload).then((r) => r.data.data)
export const updateCategory = (id, payload) => client.put(`/admin/categories/${id}`, payload).then((r) => r.data.data)
export const deleteCategory = (id) => client.delete(`/admin/categories/${id}`)

// ---- Products ---------------------------------------------------------------
export const getAdminProducts = (params, options) =>
  client.get('/admin/products', { params, ...options }).then((r) => r.data)
export const getAdminProduct = (id, options) => client.get(`/admin/products/${id}`, options).then((r) => r.data.data)
export const createProduct = (payload) => client.post('/admin/products', payload).then((r) => r.data.data)
export const updateProduct = (id, payload) => client.put(`/admin/products/${id}`, payload).then((r) => r.data.data)
export const deleteProduct = (id) => client.delete(`/admin/products/${id}`)

// ---- Orders -------------------------------------------------------------------
// List resolves to { data, meta, links, statuses }.
export const getAdminOrders = (params, options) =>
  client.get('/admin/orders', { params, ...options }).then((r) => r.data)
export const getAdminOrder = (id, options) => client.get(`/admin/orders/${id}`, options).then((r) => r.data.data)
export const updateOrderStatus = (id, status) =>
  client.patch(`/admin/orders/${id}/status`, { status }).then((r) => r.data.data)

/**
 * The CSV export sits behind the bearer token, so it can't be a plain link:
 * fetch it as a blob and hand the browser an object URL to save.
 */
export const downloadOrdersCsv = async (params) => {
  const response = await client.get('/admin/orders/export', { params, responseType: 'blob' })
  const disposition = response.headers['content-disposition'] ?? ''
  const filename = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? 'leueats-orders.csv'

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

// ---- Analytics ------------------------------------------------------------------
// Sales over time, best sellers and promo-code performance for ?days={7,30,90,365}.
export const getAnalytics = (params, options) =>
  client.get('/admin/analytics', { params, ...options }).then((r) => r.data.data)

// ---- Promo codes ---------------------------------------------------------------
// The list resolves to the whole body: { data, types } — `types` drives the form's picker.
export const getAdminPromoCodes = (options) => client.get('/admin/promo-codes', options).then((r) => r.data)
export const createPromoCode = (payload) => client.post('/admin/promo-codes', payload).then((r) => r.data.data)
export const updatePromoCode = (id, payload) => client.put(`/admin/promo-codes/${id}`, payload).then((r) => r.data.data)
export const deletePromoCode = (id) => client.delete(`/admin/promo-codes/${id}`)
