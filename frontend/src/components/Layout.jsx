import { Outlet } from 'react-router'
import Navbar from './Navbar'

/** Storefront shell: navbar, page content, footer. */
export default function Layout() {
  return (
    <div className="app">
      <Navbar />
      <main className="container main">
        <Outlet />
      </main>
      <footer className="footer">
        <div className="container">LeuEats demo — Laravel REST API + React SPA</div>
      </footer>
    </div>
  )
}
