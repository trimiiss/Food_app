import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useState } from 'react'
import { sumMoney } from '../utils/format'
import { useShop } from './ShopContext'

const STORAGE_KEY = 'leueats.cart'
const PROMO_STORAGE_KEY = 'leueats.promo'

/*
 * The cart is purely client-side (React state persisted to localStorage).
 *
 * Each line keeps a display snapshot of the product (name, price, image) so the
 * cart renders without extra requests. Those prices are an estimate only: at
 * checkout the API receives just product ids + quantities and re-prices
 * everything from the database.
 */

function loadCart() {
  try {
    const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]')
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

function loadPromoCode() {
  try {
    return localStorage.getItem(PROMO_STORAGE_KEY) || null
  } catch {
    return null
  }
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
  const [items, dispatch] = useReducer(cartReducer, undefined, loadCart)
  const [promoCode, setPromoCodeState] = useState(loadPromoCode)

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
      if (promoCode) {
        localStorage.setItem(PROMO_STORAGE_KEY, promoCode)
      } else {
        localStorage.removeItem(PROMO_STORAGE_KEY)
      }
    } catch {
      /* storage unavailable: cart lasts until reload */
    }
  }, [items, promoCode])

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
    return {
      items,
      itemCount: items.reduce((count, item) => count + item.quantity, 0),
      // Local estimate for the cart badge and line rows; the authoritative
      // totals come from the server via useCartPricing.
      subtotal,
      deliveryFee: items.length ? deliveryFee : 0,
      estimatedTotal: items.length ? sumMoney([{ price: subtotal, quantity: 1 }, { price: deliveryFee, quantity: 1 }]) : 0,
      maxQuantity: max,
      promoCode,
      setPromoCode,
      addItem,
      setQuantity,
      removeItem,
      clearCart,
    }
  }, [items, promoCode, deliveryFee, max, setPromoCode, addItem, setQuantity, removeItem, clearCart])

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useCart() {
  const context = useContext(CartContext)
  if (!context) throw new Error('useCart must be used inside <CartProvider>')
  return context
}
