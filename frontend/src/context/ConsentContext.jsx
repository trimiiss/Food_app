import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

const STORAGE_KEY = 'leueats.consent'

export const ACCEPTED = 'accepted'
export const ESSENTIAL = 'essential'

const ConsentContext = createContext(null)

/*
 * What the visitor has agreed to store on their device.
 *
 * LeuEats sets no tracking or advertising cookies — there is nothing to sell a
 * visitor's browsing to. What it does keep is local: the cart, a delivery or
 * pickup preference, an applied promo code and, once signed in, an API token.
 *
 * So the choice is a real one rather than a decorative banner:
 *
 *   accepted   remember the cart and preferences between visits (localStorage)
 *   essential  keep them only until the tab is closed (sessionStorage)
 *
 * The API token is not part of the deal either way: a visitor who types their
 * password is asking to be signed in, and signing out clears it.
 */
function loadDecision() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return stored === ACCEPTED || stored === ESSENTIAL ? stored : null
  } catch {
    // Storage blocked entirely: nothing can be remembered, so nothing to ask about.
    return ESSENTIAL
  }
}

export function ConsentProvider({ children }) {
  const [decision, setDecision] = useState(loadDecision)
  // Reopened from the footer, even after a choice has been made.
  const [reviewing, setReviewing] = useState(false)

  const decide = useCallback((next) => {
    setDecision(next)
    setReviewing(false)
    try {
      localStorage.setItem(STORAGE_KEY, next)
      if (next === ESSENTIAL) {
        // Honour the choice immediately: drop anything already persisted.
        Object.keys(localStorage)
          .filter((key) => key.startsWith('leueats.') && key !== STORAGE_KEY && key !== 'leueats.token')
          .forEach((key) => localStorage.removeItem(key))
      }
    } catch {
      /* storage unavailable: the choice lasts for this page view */
    }
  }, [])

  // Other tabs share the decision, so the banner doesn't reappear in each one.
  useEffect(() => {
    const sync = (event) => {
      if (event.key === STORAGE_KEY) setDecision(loadDecision())
    }
    window.addEventListener('storage', sync)
    return () => window.removeEventListener('storage', sync)
  }, [])

  const value = useMemo(
    () => ({
      decision,
      /** Where the cart and preferences may be kept, given the choice. */
      storage: decision === ACCEPTED ? 'local' : 'session',
      isAsking: decision === null || reviewing,
      review: () => setReviewing(true),
      accept: () => decide(ACCEPTED),
      essentialOnly: () => decide(ESSENTIAL),
    }),
    [decision, reviewing, decide],
  )

  return <ConsentContext.Provider value={value}>{children}</ConsentContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useConsent() {
  const context = useContext(ConsentContext)
  if (!context) throw new Error('useConsent must be used inside <ConsentProvider>')
  return context
}
