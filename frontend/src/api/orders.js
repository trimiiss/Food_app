import client from './client'

/**
 * @param {{ items: {product_id: number, quantity: number}[], delivery_address: string, contact_phone: string, notes?: string }} payload
 *   Only ids and quantities are sent — prices are decided by the server.
 */
export const placeOrder = (payload) => client.post('/orders', payload).then((r) => r.data.data)

export const getMyOrders = (params, options) => client.get('/orders', { params, ...options }).then((r) => r.data)

export const getMyOrder = (id, options) => client.get(`/orders/${id}`, options).then((r) => r.data.data)

export const cancelMyOrder = (id) => client.post(`/orders/${id}/cancel`).then((r) => r.data.data)

/**
 * Prices a cart server-side (offers + promo code) without creating an order,
 * so the cart, checkout and the final charge all agree.
 */
export const previewCart = (items, promoCode, options) =>
  client.post('/cart/preview', { items, promo_code: promoCode || null }, options).then((r) => r.data.data)
