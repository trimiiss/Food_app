<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use App\Support\Sql;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id', 'order_number', 'status', 'fulfillment_type', 'subtotal', 'delivery_fee',
    'promo_code', 'discount_total', 'total', 'delivery_address', 'contact_phone', 'notes',
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
            'fulfillment_type' => FulfillmentType::class,
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
     * How this order is fulfilled, never null.
     *
     * Orders placed before pickup existed were all deliveries, and so is a row
     * read from a database where the fulfillment_type migration hasn't run yet
     * — reading an order must not be what tells you that.
     */
    public function fulfillment(): FulfillmentType
    {
        return $this->fulfillment_type ?? FulfillmentType::Delivery;
    }

    /**
     * The admin list filters, shared by the orders table and its CSV export so
     * that "export" always means "what I am looking at".
     *
     * @param  Builder<Order>  $query
     * @param  array{status?: string|null, fulfillment_type?: string|null, search?: string|null}  $filters
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters): void
    {
        $like = Sql::likeOperator();

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(
                $filters['fulfillment_type'] ?? null,
                fn (Builder $query, string $type) => $query->where('fulfillment_type', $type),
            )
            ->when($filters['search'] ?? null, function (Builder $query, string $search) use ($like) {
                $query->where(function (Builder $query) use ($search, $like) {
                    $query->where('order_number', $like, "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q
                            ->where('name', $like, "%{$search}%")
                            ->orWhere('email', $like, "%{$search}%"));
                });
            });
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
