import { Link, Navigate, useLocation, useNavigate } from 'react-router'
import { ErrorMessage } from '../components/Feedback'
import FormField from '../components/FormField'
import { useAuth } from '../context/AuthContext'
import { useForm } from '../hooks/useForm'

export default function RegisterPage() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

  const from = location.state?.from
  const redirectTo = from ? `${from.pathname}${from.search ?? ''}` : '/'

  if (user) return <Navigate to={redirectTo} replace />

  const handleSubmit = form.submit(async (values) => {
    await register(values)
    navigate(redirectTo, { replace: true })
  })

  return (
    <div className="card auth-card">
      <h1>Create your account</h1>
      <p className="muted">Order in a couple of taps and keep track of every delivery.</p>

      <form className="form" onSubmit={handleSubmit} noValidate>
        <ErrorMessage error={form.formError} />
        <FormField label="Name" autoComplete="name" required error={form.fieldError('name')} {...form.bind('name')} />
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
          autoComplete="new-password"
          hint="At least 8 characters."
          required
          error={form.fieldError('password')}
          {...form.bind('password')}
        />
        <FormField
          label="Confirm password"
          type="password"
          autoComplete="new-password"
          required
          {...form.bind('password_confirmation')}
        />
        <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
          {form.submitting ? 'Creating account…' : 'Create account'}
        </button>
      </form>

      <p className="auth-footer">
        Already have an account?{' '}
        <Link to="/login" state={location.state}>
          Log in
        </Link>
      </p>
    </div>
  )
}
