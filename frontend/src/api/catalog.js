import client from './client'

/*
 * Conventions for every api/* module:
 *   - list endpoints resolve to the full body { data, meta, links } (pagination);
 *   - single-resource endpoints resolve to the resource itself.
 *   - `options` is passed to axios, mainly for { signal } cancellation.
 */

export const getShopSettings = (options) => client.get('/shop', options).then((r) => r.data.data)

export const getCategories = (options) => client.get('/categories', options).then((r) => r.data.data)

export const getProducts = (params, options) =>
  client.get('/products', { params, ...options }).then((r) => r.data)

export const getProduct = (slug, options) =>
  client.get(`/products/${encodeURIComponent(slug)}`, options).then((r) => r.data.data)

/** Publicly advertised promo codes for the Deals page. */
export const getPromotions = (options) => client.get('/promotions', options).then((r) => r.data.data)
