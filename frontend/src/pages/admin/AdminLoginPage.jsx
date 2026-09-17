import { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ErrorMessage } from '../../components/Feedback'
import FormField from '../../components/FormField'
import { useAuth } from '../../context/AuthContext'
import { useForm } from '../../hooks/useForm'

export default function AdminLoginPage() {
  const { isAdmin, login, logout } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const form = useForm({ email: '', password: '' })
  const [notAdmin, setNotAdmin] = useState(false)

  const from = location.state?.from
  const redirectTo = from ? `${from.pathname}${from.search ?? ''}` : '/admin'

  if (isAdmin) return <Navigate to={redirectTo} replace />

  const handleSubmit = form.submit(async (values) => {
    setNotAdmin(false)
    const user = await login(values)

    if (!user.is_admin) {
      // Don't leave a customer session behind from the admin login form;
      // logout() also revokes the token that was just issued.
      await logout()
      setNotAdmin(true)
      return
    }

    navigate(redirectTo, { replace: true })
  })

  return (
    <div className="card auth-card">
      <span className="admin-tag">Admin</span>
      <h1>Staff login</h1>
      <p className="muted">Manage the menu and incoming orders.</p>

      <form className="form" onSubmit={handleSubmit} noValidate>
        {notAdmin && (
          <div className="alert alert-error" role="alert">
            This account doesn’t have administrator access.
          </div>
        )}
        <ErrorMessage error={form.formError} />
        <FormField
          label="Email"
          type="email"
          autoComplete="email"
          error={form.fieldError('email')}
          {...form.bind('email')}
        />
        <FormField
          label="Password"
          type="password"
          autoComplete="current-password"
          error={form.fieldError('password')}
          {...form.bind('password')}
        />
        <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
          {form.submitting ? 'Logging in…' : 'Log in to admin'}
        </button>
      </form>

      <div className="alert alert-info demo-hint">
        <span>
          Demo admin: <code>admin@foodapp.test</code> / <code>password</code>
        </span>
      </div>

      <p className="auth-footer">
        Need an admin account? <Link to="/admin/register">Register with an invite code</Link>
        <br />
        <Link to="/">← Back to the store</Link>
      </p>
    </div>
  )
}
