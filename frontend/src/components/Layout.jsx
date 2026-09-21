import { Outlet } from 'react-router'
import CookieBanner from './CookieBanner'
import Navbar from './Navbar'

/** Storefront shell: navbar, page content, footer, cookie notice. */
export default function Layout() {
  return (
    <div className="app">
      <Navbar />
      <main className="container main">
        <Outlet />
      </main>
      <footer className="footer">
        <div className="container footer-inner">
          <span>LeuEats demo</span>
        </div>
      </footer>
      <CookieBanner />
    </div>
  )
}
