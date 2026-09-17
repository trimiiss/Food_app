import { Route, Routes } from 'react-router'
import Layout from './components/Layout'
import NotFoundPage from './pages/NotFoundPage'

/**
 * Route table.
 *
 *   Storefront (Layout)   public pages, plus RequireAuth for checkout/orders
 *   /admin (AdminLayout)  RequireAdmin
 */
export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
