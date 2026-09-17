import { useState } from 'react'
import { ApiError } from '../api/client'

/**
 * Tiny form helper: field values, submitting flag and the last ApiError.
 *
 *   const form = useForm({ email: '' })
 *   <input {...form.bind('email')} />
 *   form.submit((values) => api.call(values))
 *
 * Validation lives on the server (Laravel Form Requests); this just shows
 * whatever 422 errors come back next to the matching fields.
 */
export function useForm(initialValues) {
  const [values, setValues] = useState(initialValues)
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const setValue = (name, value) => {
    setValues((previous) => ({ ...previous, [name]: value }))
    // Editing a field clears its stale server error; other fields keep theirs.
    setError((previous) => {
      if (!previous?.errors?.[name]) return previous
      const { [name]: _cleared, ...remaining } = previous.errors
      return new ApiError(previous.message, previous.status, remaining)
    })
  }

  const bind = (name) => ({
    name,
    value: values[name] ?? '',
    onChange: (event) => setValue(name, event.target.type === 'checkbox' ? event.target.checked : event.target.value),
  })

  const submit = (handler) => async (event) => {
    event?.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      return await handler(values)
    } catch (caught) {
      setError(caught instanceof ApiError ? caught : new ApiError(caught?.message ?? 'Something went wrong.'))
      return undefined
    } finally {
      setSubmitting(false)
    }
  }

  // Field-specific message, if the server sent one.
  const fieldError = (name) => error?.fieldError(name)

  // Top-level message only when it isn't just a summary of field errors.
  const formError = error && Object.keys(error.errors ?? {}).length === 0 ? error : null

  return { values, setValues, setValue, bind, submit, submitting, error, fieldError, formError }
}
