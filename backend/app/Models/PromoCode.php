<?php

namespace App\Models;

use App\Enums\PromoCodeType;
use App\Support\Money;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A discount code applied at checkout.
 *
 * The rules (is it live? does the order qualify? how much off?) live here so
 * CartPricer, the checkout and the admin panel all agree.
 */
#[Fillable([
    'code', 'description', 'type', 'value', 'min_subtotal',
    'starts_at', 'ends_at', 'max_uses', 'is_active', 'is_public',
])]
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    /**
     * The database default only applies to the stored row; without this the
     * model returned straight after create() would report uses_count as null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'uses_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'value' => 'decimal:2',
            'min_subtotal' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /** Codes are stored and compared upper-cased, so "welcome10" works too. */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', Str::upper(trim($code)))->first();
    }

    /**
     * Codes advertised on the storefront.
     *
     * @param  Builder<PromoCode>  $query
     */
    #[Scope]
    protected function publiclyListed(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('is_public', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $query) => $query->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'));
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function isFullyRedeemed(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    /**
     * Why this code can't be used for a cart of `$subtotalCents`, or null if it can.
     * Returning the reason (instead of a bare bool) lets the UI explain itself.
     */
    public function rejectionReason(int $subtotalCents): ?string
    {
        return match (true) {
            ! $this->is_active => 'This promo code is no longer active.',
            ! $this->hasStarted() => 'This promo code is not available yet.',
            $this->hasExpired() => 'This promo code has expired.',
            $this->isFullyRedeemed() => 'This promo code has been fully redeemed.',
            $subtotalCents < Money::toCents($this->min_subtotal) => sprintf(
                'This code needs a minimum order of %s %s.',
                config('shop.currency'),
                $this->min_subtotal,
            ),
            default => null,
        };
    }

    /**
     * Discount in cents. Percent and fixed never exceed the subtotal (a code
     * can't make an order cost less than nothing); free delivery waives the fee.
     */
    public function discountCentsFor(int $subtotalCents, int $deliveryFeeCents): int
    {
        return match ($this->type) {
            PromoCodeType::Percent => min(Money::percentOf($subtotalCents, $this->value), $subtotalCents),
            PromoCodeType::Fixed => min(Money::toCents($this->value), $subtotalCents),
            PromoCodeType::FreeDelivery => $deliveryFeeCents,
        };
    }

    /** Short human summary, e.g. "10% off" or "EUR 5.00 off". */
    public function summary(): string
    {
        return match ($this->type) {
            PromoCodeType::Percent => rtrim(rtrim((string) $this->value, '0'), '.').'% off',
            PromoCodeType::Fixed => config('shop.currency').' '.$this->value.' off',
            PromoCodeType::FreeDelivery => 'Free delivery',
        };
    }
}
