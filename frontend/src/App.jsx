import { Route, Routes } from 'react-router'
import Layout from './components/Layout'
import { RequireAdmin, RequireAuth } from './components/RouteGuards'
import AdminLayout from './pages/admin/AdminLayout'
import AdminLoginPage from './pages/admin/AdminLoginPage'
import AdminRegisterPage from './pages/admin/AdminRegisterPage'
import DashboardPage from './pages/admin/DashboardPage'
import CartPage from './pages/CartPage'
import CheckoutPage from './pages/CheckoutPage'
import LoginPage from './pages/LoginPage'
import NotFoundPage from './pages/NotFoundPage'
import OrderDetailPage from './pages/OrderDetailPage'
import OrdersPage from './pages/OrdersPage'
import ProductDetailPage from './pages/ProductDetailPage'
import ProductListPage from './pages/ProductListPage'
import RegisterPage from './pages/RegisterPage'

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
        <Route index element={<ProductListPage />} />
        <Route path="products/:slug" element={<ProductDetailPage />} />
        <Route path="cart" element={<CartPage />} />
        <Route path="login" element={<LoginPage />} />
        <Route path="register" element={<RegisterPage />} />
        <Route path="admin/login" element={<AdminLoginPage />} />
        <Route path="admin/register" element={<AdminRegisterPage />} />

        <Route element={<RequireAuth />}>
          <Route path="checkout" element={<CheckoutPage />} />
          <Route path="orders" element={<OrdersPage />} />
          <Route path="orders/:id" element={<OrderDetailPage />} />
        </Route>

        <Route path="*" element={<NotFoundPage />} />
      </Route>

      <Route path="admin" element={<RequireAdmin />}>
        <Route element={<AdminLayout />}>
          <Route index element={<DashboardPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
