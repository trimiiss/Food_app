import { Outlet } from 'react-router'
import { useConsent } from '../context/ConsentContext'
import CookieBanner from './CookieBanner'
import Navbar from './Navbar'

/** Storefront shell: navbar, page content, footer, cookie notice. */
export default function Layout() {
  const { review } = useConsent()

  return (
    <div className="app">
      <Navbar />
      <main className="container main">
        <Outlet />
      </main>
      <footer className="footer">
        <div className="container footer-inner">
          <span>LeuEats demo — Laravel REST API + React SPA</span>
          {/* The choice is never final: this reopens the notice. */}
          <button type="button" className="link-button" onClick={review}>
            Cookie choices
          </button>
        </div>
      </footer>
      <CookieBanner />
    </div>
  )
}
