import { useId } from 'react'

/**
 * Label + control + validation message. `error` is typically
 * apiError.fieldError('email') from a Laravel 422 response.
 *
 * Pass the control as `children` for selects/textareas/checkboxes, or omit
 * it to get a plain <input> with the remaining props.
 */
export default function FormField({ label, error, hint, children, className = '', ...inputProps }) {
  const generatedId = useId()
  const id = inputProps.id ?? generatedId
  const errorId = `${id}-error`

  const control = children ?? (
    <input id={id} aria-invalid={Boolean(error)} aria-describedby={error ? errorId : undefined} {...inputProps} />
  )

  return (
    <div className={`field ${error ? 'has-error' : ''} ${className}`}>
      {label && <label htmlFor={id}>{label}</label>}
      {control}
      {hint && !error && <span className="hint">{hint}</span>}
      {error && (
        <span id={errorId} className="field-error">
          {error}
        </span>
      )}
    </div>
  )
}
