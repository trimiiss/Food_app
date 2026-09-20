<?php

namespace App\Enums;

/**
 * Order lifecycle as a small, explicit state machine.
 *
 *   pending -> confirmed -> preparing -> out_for_delivery -> delivered
 *      \___________\____________\______________\_________-> cancelled
 *
 * `delivered` and `cancelled` are terminal. Keeping the allowed transitions
 * here (rather than scattered through controllers) means both the admin
 * status endpoint and the customer cancel endpoint share one source of truth.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [self::OutForDelivery, self::Cancelled],
            self::OutForDelivery => [self::Delivered, self::Cancelled],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * Customers may only back out before the kitchen starts cooking.
     */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * The same state, worded for how the order is being fulfilled: nothing
     * goes "out for delivery" when the customer is collecting it themselves.
     */
    public function labelFor(FulfillmentType $fulfillment): string
    {
        if ($fulfillment->chargesDeliveryFee()) {
            return $this->label();
        }

        return match ($this) {
            self::OutForDelivery => 'Ready for pickup',
            self::Delivered => 'Picked up',
            default => $this->label(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Preparing => 'Preparing',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }
}
