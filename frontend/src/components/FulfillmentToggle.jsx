import { PICKUP } from '../context/CartContext'
import { useShop } from '../context/ShopContext'

/**
 * Delivery or pickup, as two radio cards.
 *
 * The options come from GET /shop, so the SPA never keeps its own copy of the
 * API's FulfillmentType enum — and the fee shown next to "Delivery" is the
 * same one the server will charge.
 */
export default function FulfillmentToggle({ value, onChange }) {
  const { fulfillment_types: types, delivery_fee: deliveryFee, pickup_ready_in_minutes: readyIn, money } = useShop()

  return (
    <fieldset className="fulfillment-toggle">
      <legend>How would you like it?</legend>
      <div className="fulfillment-options">
        {types.map((type) => {
          const isPickup = type.value === PICKUP
          return (
            <label key={type.value} className={`fulfillment-option ${value === type.value ? 'is-selected' : ''}`}>
              <input
                type="radio"
                name="fulfillment_type"
                value={type.value}
                checked={value === type.value}
                onChange={() => onChange(type.value)}
              />
              <span className="fulfillment-icon" aria-hidden="true">
                {isPickup ? '🛍️' : '🛵'}
              </span>
              <span className="fulfillment-text">
                <strong>{type.label}</strong>
                <span className="muted small">
                  {isPickup ? `Ready in ~${readyIn} min · no fee` : `+ ${money(deliveryFee)} delivery`}
                </span>
              </span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}
