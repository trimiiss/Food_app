/**
 * − [n] + control. Going below `min` is left to the parent (the cart treats
 * 0 as "remove"), so the minus button is only disabled at `min`.
 */
export default function QuantityStepper({ value, onChange, min = 1, max = 50, label = 'Quantity' }) {
  const handleInput = (event) => {
    const next = Number.parseInt(event.target.value, 10)
    if (!Number.isNaN(next)) onChange(Math.min(max, Math.max(min, next)))
  }

  return (
    <div className="stepper" role="group" aria-label={label}>
      <button
        type="button"
        className="stepper-btn"
        onClick={() => onChange(value - 1)}
        disabled={value <= min}
        aria-label="Decrease quantity"
      >
        −
      </button>
      <input
        className="stepper-input"
        type="number"
        inputMode="numeric"
        min={min}
        max={max}
        value={value}
        onChange={handleInput}
        aria-label={label}
      />
      <button
        type="button"
        className="stepper-btn"
        onClick={() => onChange(value + 1)}
        disabled={value >= max}
        aria-label="Increase quantity"
      >
        +
      </button>
    </div>
  )
}
