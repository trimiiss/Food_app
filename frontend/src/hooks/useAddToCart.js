import { useEffect, useRef, useState } from 'react'
import { useCart } from '../context/CartContext'

/**
 * Wraps cart.addItem with a short "Added ✓" confirmation state for the button
 * that triggered it — lightweight feedback without a toast system.
 */
export function useAddToCart() {
  const { addItem } = useCart()
  const [justAdded, setJustAdded] = useState(false)
  const timer = useRef(null)

  useEffect(() => () => clearTimeout(timer.current), [])

  const add = (product, quantity = 1) => {
    addItem(product, quantity)
    setJustAdded(true)
    clearTimeout(timer.current)
    timer.current = setTimeout(() => setJustAdded(false), 1500)
  }

  return { add, justAdded }
}
