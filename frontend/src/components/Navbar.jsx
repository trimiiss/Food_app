import { Link, NavLink, useNavigate } from 'react-router'
import { useAuth } from '../context/AuthContext'
import { useCart } from '../context/CartContext'

export default function Navbar() {
  const { user, isAdmin, initialising, logout } = useAuth()
  const { itemCount } = useCart()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/')
  }

  return (
    <header className="navbar">
      <div className="container navbar-inner">
        <Link to="/" className="brand">
          <span aria-hidden="true">🍴</span> FoodApp
        </Link>

        <nav className="nav-links" aria-label="Main">
          <NavLink to="/" end>
            Menu
          </NavLink>
          {user && <NavLink to="/orders">My orders</NavLink>}
          {isAdmin && <NavLink to="/admin">Admin</NavLink>}
        </nav>

        <div className="nav-actions">
          <NavLink to="/cart" className="cart-link" aria-label={`Cart, ${itemCount} items`}>
            <span aria-hidden="true">🛒</span>
            <span className="cart-link-label">Cart</span>
            {itemCount > 0 && <span className="cart-badge">{itemCount}</span>}
          </NavLink>

          {/* While a stored token is being checked, show neither state — avoids a
              "Log in" flash for returning users. */}
          {initialising ? null : user ? (
            <>
              <span className="nav-user" title={user.email}>
                Hi, {user.name.split(' ')[0]}
              </span>
              <button type="button" className="btn btn-sm btn-ghost" onClick={handleLogout}>
                Log out
              </button>
            </>
          ) : (
            <>
              <NavLink to="/login" className="btn btn-sm btn-ghost">
                Log in
              </NavLink>
              <NavLink to="/register" className="btn btn-sm btn-primary">
                Sign up
              </NavLink>
            </>
          )}
        </div>
      </div>
    </header>
  )
}
