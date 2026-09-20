import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useState } from 'react'
import { sumMoney } from '../utils/format'
import { useConsent } from './ConsentContext'
import { useShop } from './ShopContext'

const STORAGE_KEY = 'leueats.cart'
const PROMO_STORAGE_KEY = 'leueats.promo'
const FULFILLMENT_STORAGE_KEY = 'leueats.fulfillment'

export const DELIVERY = 'delivery'
export const PICKUP = 'pickup'

/*
 * The cart is purely client-side (React state persisted to the browser).
 *
 * Each line keeps a display snapshot of the product (name, price, image) so the
 * cart renders without extra requests. Those prices are an estimate only: at
 * checkout the API receives just product ids + quantities and re-prices
 * everything from the database.
 *
 * Where it is persisted depends on the visitor's cookie choice (ConsentContext):
 * localStorage if they accepted, sessionStorage if they asked us to forget on
 * close. Reads look in both, so changing that choice mid-visit doesn't empty a
 * cart someone is in the middle of filling.
 */

function read(key) {
  try {
    return window.localStorage.getItem(key) ?? window.sessionStorage.getItem(key)
  } catch {
    return null
  }
}

function write(kind, key, value) {
  try {
    const target = kind === 'local' ? window.localStorage : window.sessionStorage
    // Never leave a copy behind in the store the visitor didn't choose.
    const other = kind === 'local' ? window.sessionStorage : window.localStorage
    other.removeItem(key)
    if (value === null) target.removeItem(key)
    else target.setItem(key, value)
  } catch {
    /* storage unavailable: the cart lasts until reload */
  }
}

function loadCart() {
  try {
    const parsed = JSON.parse(read(STORAGE_KEY) ?? '[]')
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function loadPromoCode() {
  return read(PROMO_STORAGE_KEY) || null
}

/** Delivery or pickup, remembered between visits. Delivery is the default. */
function loadFulfillment() {
  return read(FULFILLMENT_STORAGE_KEY) === PICKUP ? PICKUP : DELIVERY
}

const clamp = (quantity, max) => Math.max(1, Math.min(max, Math.floor(quantity)))

function cartReducer(items, action) {
  switch (action.type) {
    case 'add': {
      const { product, quantity, max } = action
      const existing = items.find((item) => item.productId === product.id)

      if (existing) {
        return items.map((item) =>
          item.productId === product.id ? { ...item, quantity: clamp(item.quantity + quantity, max) } : item,
        )
      }

      return [
        ...items,
        {
          productId: product.id,
          slug: product.slug,
          name: product.name,
          // The price on offer right now, so the local estimate shown before the
          // server prices the cart matches what will actually be charged.
          price: product.effective_price ?? product.price,
          listPrice: product.price,
          imageUrl: product.image_url,
          quantity: clamp(quantity, max),
        },
      ]
    }

    case 'setQuantity':
      if (action.quantity < 1) {
        return items.filter((item) => item.productId !== action.productId)
      }
      return items.map((item) =>
        item.productId === action.productId ? { ...item, quantity: clamp(action.quantity, action.max) } : item,
      )

    case 'remove':
      return items.filter((item) => item.productId !== action.productId)

    case 'clear':
      return []

    default:
      throw new Error(`Unknown cart action: ${action.type}`)
  }
}

const CartContext = createContext(null)

export function CartProvider({ children }) {
  const { max_item_quantity: max, delivery_fee: deliveryFee } = useShop()
  // 'local' or 'session', from what the visitor agreed to keep on their device.
  const { storage } = useConsent()
  const [items, dispatch] = useReducer(cartReducer, undefined, loadCart)
  const [promoCode, setPromoCodeState] = useState(loadPromoCode)
  const [fulfillment, setFulfillment] = useState(loadFulfillment)

  useEffect(() => {
    write(storage, STORAGE_KEY, JSON.stringify(items))
    write(storage, FULFILLMENT_STORAGE_KEY, fulfillment)
    write(storage, PROMO_STORAGE_KEY, promoCode || null)
  }, [items, promoCode, fulfillment, storage])

  const addItem = useCallback((product, quantity = 1) => dispatch({ type: 'add', product, quantity, max }), [max])
  const setQuantity = useCallback(
    (productId, quantity) => dispatch({ type: 'setQuantity', productId, quantity, max }),
    [max],
  )
  const removeItem = useCallback((productId) => dispatch({ type: 'remove', productId }), [])

  const clearCart = useCallback(() => {
    dispatch({ type: 'clear' })
    setPromoCodeState(null)
  }, [])

  // Codes are entered on the cart page or at checkout; keeping the applied one
  // here means moving between the two doesn't lose it.
  const setPromoCode = useCallback((code) => {
    setPromoCodeState(code ? code.trim().toUpperCase() : null)
  }, [])

  const value = useMemo(() => {
    const subtotal = sumMoney(items)
    // Collecting in store costs no delivery fee — the server decides this too,
    // this is only the estimate shown before /cart/preview answers.
    const fee = items.length && fulfillment === DELIVERY ? deliveryFee : 0
    return {
      items,
      itemCount: items.reduce((count, item) => count + item.quantity, 0),
      // Local estimate for the cart badge and line rows; the authoritative
      // totals come from the server via useCartPricing.
      subtotal,
      deliveryFee: fee,
      estimatedTotal: items.length ? sumMoney([{ price: subtotal, quantity: 1 }, { price: fee, quantity: 1 }]) : 0,
      maxQuantity: max,
      promoCode,
      setPromoCode,
      fulfillment,
      setFulfillment,
      isPickup: fulfillment === PICKUP,
      addItem,
      setQuantity,
      removeItem,
      clearCart,
    }
  }, [items, promoCode, fulfillment, deliveryFee, max, setPromoCode, addItem, setQuantity, removeItem, clearCart])

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useCart() {
  const context = useContext(CartContext)
  if (!context) throw new Error('useCart must be used inside <CartProvider>')
  return context
}
