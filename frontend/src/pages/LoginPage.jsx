import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ErrorMessage } from '../components/Feedback'
import FormField from '../components/FormField'
import { useAuth } from '../context/AuthContext'
import { useForm } from '../hooks/useForm'

export default function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const form = useForm({ email: '', password: '' })

  // Return to the page that sent the visitor here (e.g. checkout).
  const from = location.state?.from
  const redirectTo = from ? `${from.pathname}${from.search ?? ''}` : '/'

  if (user) return <Navigate to={redirectTo} replace />

  const handleSubmit = form.submit(async (values) => {
    await login(values)
    navigate(redirectTo, { replace: true })
  })

  return (
    <div className="card auth-card">
      <h1>Welcome back</h1>
      <p className="muted">Log in to place orders and track them.</p>

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
        </span>
      </div>

      <p className="auth-footer">
        New here? <Link to="/register" state={location.state}>Create an account</Link>
        <br />
        Staff? <Link to="/admin/login">Admin login</Link>
      </p>
    </div>
  )
}
