import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '../../context/AuthContext'

const NAV = [
  { to: '/admin', label: 'Dashboard', icon: '📊', end: true },
  { to: '/admin/analytics', label: 'Analytics', icon: '📈' },
  { to: '/admin/orders', label: 'Orders', icon: '🧾' },
  { to: '/admin/products', label: 'Products', icon: '🍕' },
  { to: '/admin/categories', label: 'Categories', icon: '🗂️' },
  { to: '/admin/promo-codes', label: 'Promo codes', icon: '🏷️' },
]

/** Admin shell: sidebar navigation + content area. */
export default function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="admin">
      <aside className="admin-sidebar">
        <Link to="/admin" className="brand">
          <span aria-hidden="true">🍴</span> LeuEats <span className="admin-tag">Admin</span>
        </Link>

        <nav className="admin-nav" aria-label="Admin">
          {NAV.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end}>
              <span aria-hidden="true">{item.icon}</span> {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="admin-sidebar-footer">
          <Link to="/" className="admin-store-link">
            ↗ View storefront
          </Link>
          <div className="admin-user">
            <span title={user.email}>{user.name}</span>
            <button type="button" className="btn btn-sm btn-ghost" onClick={handleLogout}>
              Log out
            </button>
          </div>
        </div>
      </aside>

      <main className="admin-main">
        <Outlet />
      </main>
    </div>
  )
}
