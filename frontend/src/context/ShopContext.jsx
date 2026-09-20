import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { getShopSettings } from '../api/catalog'
import { formatMoney, formatMoneyShort } from '../utils/format'

// Used until /shop responds (and if it fails) so prices still render.
const DEFAULT_SETTINGS = { currency: 'EUR', delivery_fee: 0, max_item_quantity: 50 }

const ShopContext = createContext(null)

/**
 * Storefront settings from GET /shop (currency, delivery fee, quantity limit),
 * so none of them are hardcoded in the SPA.
 */
export function ShopProvider({ children }) {
  const [settings, setSettings] = useState(DEFAULT_SETTINGS)

  useEffect(() => {
    const controller = new AbortController()
    getShopSettings({ signal: controller.signal })
      .then(setSettings)
      .catch(() => {
        /* keep defaults; pages show their own errors if the API is down */
      })
    return () => controller.abort()
  }, [])

  const money = useCallback((amount) => formatMoney(amount, settings.currency), [settings.currency])
  // For headlines, where "€20.00" reads worse than "€20".
  const moneyShort = useCallback((amount) => formatMoneyShort(amount, settings.currency), [settings.currency])

  const value = useMemo(() => ({ ...settings, money, moneyShort }), [settings, money, moneyShort])

  return <ShopContext.Provider value={value}>{children}</ShopContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useShop() {
  const context = useContext(ShopContext)
  if (!context) throw new Error('useShop must be used inside <ShopProvider>')
  return context
}
