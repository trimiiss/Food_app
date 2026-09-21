import { Link, Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '../context/AuthContext'
import { EmptyState, Loader } from './Feedback'

/*
 * UX-only guards. They decide what to *render*; the real security boundary is
 * the API (auth:sanctum + admin middleware), which rejects the requests anyway.
 */

/** Any signed-in user. Remembers where the visitor was headed. */
export function RequireAuth() {
  const { user, initialising } = useAuth()
  const location = useLocation()

  if (initialising) return <Loader label="Checking your session…" />
  if (!user) return <Navigate to="/login" replace state={{ from: location }} />

  return <Outlet />
}

/** Signed-in admins only. */
export function RequireAdmin() {
  const { user, isAdmin, initialising } = useAuth()
  const location = useLocation()

  if (initialising) return <Loader label="Checking your session…" />
  if (!user) return <Navigate to="/login" replace state={{ from: location }} />

  if (!isAdmin) {
    return (
      <div className="container main">
        <EmptyState icon="🔒" title="Administrators only">
          <p>You are signed in as a customer, so the admin panel is not available.</p>
          <Link to="/" className="btn btn-primary">
            Back to the menu
          </Link>
        </EmptyState>
      </div>
    )
  }

  return <Outlet />
}
