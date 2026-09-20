<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'order_number', 'status', 'subtotal', 'delivery_fee', 'promo_code',
    'discount_total', 'total', 'delivery_address', 'contact_phone', 'notes',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Human-friendly reference shown to customers, e.g. ORD-20260917-K3F9QX.
     * Not guessable/enumerable like the numeric id, and the unique index on the
     * column is the final guard against an (astronomically unlikely) collision.
     *
     * @param  CarbonInterface|null  $placedAt  defaults to now; lets seeders back-date consistently
     */
    public static function generateOrderNumber(?CarbonInterface $placedAt = null): string
    {
        return 'ORD-'.($placedAt ?? now())->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
