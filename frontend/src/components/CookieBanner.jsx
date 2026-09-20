import { useEffect, useRef } from 'react'
import { useConsent } from '../context/ConsentContext'

/**
 * First-visit notice about what LeuEats keeps on the visitor's device.
 *
 * It sits at the bottom and never blocks the page: someone who wants to read
 * the menu before deciding can. The two buttons lead to genuinely different
 * behaviour (see ConsentContext) rather than both meaning "yes".
 */
export default function CookieBanner() {
  const { isAsking, decision, accept, essentialOnly } = useConsent()
  const acceptRef = useRef(null)

  // Reopened from the footer: move focus to the choice, not back to the top.
  useEffect(() => {
    if (isAsking && decision !== null) acceptRef.current?.focus()
  }, [isAsking, decision])

  if (!isAsking) return null

  return (
    <aside className="cookie-banner" role="region" aria-labelledby="cookie-banner-title">
      <div className="cookie-banner-inner">
        <div className="cookie-banner-text">
          <h2 id="cookie-banner-title">🍪 Cookies, briefly</h2>
          <p>
            We keep your cart, your delivery or pickup choice and any promo code on this device so they
            survive a refresh — and a sign-in token once you log in. No tracking, no advertising, nothing
            shared with anyone else.
          </p>
        </div>
        <div className="cookie-banner-actions">
          <button type="button" className="btn btn-primary" onClick={accept} ref={acceptRef}>
            Accept
          </button>
          <button type="button" className="btn btn-secondary" onClick={essentialOnly}>
            Only what’s needed
          </button>
        </div>
      </div>
      <p className="cookie-banner-note">
        “Only what’s needed” keeps the same things, but forgets them when you close the tab.
      </p>
    </aside>
  )
}
