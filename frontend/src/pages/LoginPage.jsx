import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ErrorMessage } from '../components/Feedback'
import FormField from '../components/FormField'
import { useAuth } from '../context/AuthContext'
import { useForm } from '../hooks/useForm'

/** The one login page for everyone: customers and admins sign in the same way. */
export default function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const form = useForm({ email: '', password: '' })

  // Return to the page that sent the visitor here (e.g. checkout or an admin
  // page); otherwise admins land on their panel and customers on the menu.
  const from = location.state?.from
  const redirectFor = (account) => (from ? `${from.pathname}${from.search ?? ''}` : account.is_admin ? '/admin' : '/')

  if (user) return <Navigate to={redirectFor(user)} replace />

  const handleSubmit = form.submit(async (values) => {
    const account = await login(values)
    navigate(redirectFor(account), { replace: true })
  })

  return (
    <div className="card auth-card">
      <h1>Welcome back</h1>
      <p className="muted">Log in to place and track orders. Admins go straight to the admin panel.</p>

      <form className="form" onSubmit={handleSubmit} noValidate>
        <ErrorMessage error={form.formError} />
        <FormField
          label="Email"
          type="email"
          autoComplete="email"
          required
          error={form.fieldError('email')}
          {...form.bind('email')}
        />
        <FormField
          label="Password"
          type="password"
          autoComplete="current-password"
          required
          error={form.fieldError('password')}
          {...form.bind('password')}
        />
        <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
          {form.submitting ? 'Logging in…' : 'Log in'}
        </button>
      </form>

      <div className="alert alert-info demo-hint">
        <span>
          Demo customer: <code>customer@leueats.test</code> / <code>password</code>
          <br />
          Demo admin: <code>admin@leueats.test</code> / <code>password</code>
        </span>
      </div>

      <p className="auth-footer">
        New here? <Link to="/register" state={location.state}>Create an account</Link>
        <br />
        Staff? <Link to="/admin/register">Register an admin account</Link>
      </p>
    </div>
  )
}
