import { Link, Navigate, useNavigate } from 'react-router'
import { ErrorMessage } from '../../components/Feedback'
import FormField from '../../components/FormField'
import { useAuth } from '../../context/AuthContext'
import { useForm } from '../../hooks/useForm'

/**
 * Admin sign-up. The API only accepts it with the shared ADMIN_REGISTRATION_CODE
 * (backend .env) — otherwise anyone could make themselves an administrator.
 */
export default function AdminRegisterPage() {
  const { isAdmin, registerAdmin } = useAuth()
  const navigate = useNavigate()
  const form = useForm({ name: '', email: '', password: '', password_confirmation: '', registration_code: '' })

  if (isAdmin) return <Navigate to="/admin" replace />

  const handleSubmit = form.submit(async (values) => {
    await registerAdmin(values)
    navigate('/admin', { replace: true })
  })

  return (
    <div className="card auth-card">
      <span className="admin-tag">Admin</span>
      <h1>Register as staff</h1>
      <p className="muted">You’ll need the admin registration code.</p>

      <form className="form" onSubmit={handleSubmit} noValidate>
        {/* 403 when admin registration is disabled on the server */}
        <ErrorMessage error={form.formError} />
        <FormField label="Name" autoComplete="name" error={form.fieldError('name')} {...form.bind('name')} />
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
          autoComplete="new-password"
          hint="At least 8 characters."
          error={form.fieldError('password')}
          {...form.bind('password')}
        />
        <FormField
          label="Confirm password"
          type="password"
          autoComplete="new-password"
          {...form.bind('password_confirmation')}
        />
        <FormField
          label="Registration code"
          type="password"
          autoComplete="off"
          hint="Set by ADMIN_REGISTRATION_CODE in the backend .env."
          error={form.fieldError('registration_code')}
          {...form.bind('registration_code')}
        />
        <button type="submit" className="btn btn-primary btn-block" disabled={form.submitting}>
          {form.submitting ? 'Creating account…' : 'Create admin account'}
        </button>
      </form>

      <p className="auth-footer">
        Already have an account? <Link to="/admin/login">Log in</Link>
      </p>
    </div>
  )
}
