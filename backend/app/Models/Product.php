<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'category_id', 'name', 'slug', 'description', 'price', 'discount_price',
    'discount_ends_at', 'image_url', 'is_available',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'discount_ends_at' => 'datetime',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Only products customers can currently order.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * Products with a discount that hasn't expired.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function onOffer(Builder $query): void
    {
        $query->whereNotNull('discount_price')
            ->where(fn (Builder $query) => $query
                ->whereNull('discount_ends_at')
                ->orWhere('discount_ends_at', '>', now()));
    }

    /**
     * An offer with no end date runs until an admin removes it; an expired one
     * simply stops applying, so nobody has to switch it off by hand.
     */
    public function isOnOffer(): bool
    {
        return $this->discount_price !== null
            && ($this->discount_ends_at === null || $this->discount_ends_at->isFuture());
    }

    /** What the customer actually pays right now (decimal string). */
    public function effectivePrice(): string
    {
        return $this->isOnOffer() ? $this->discount_price : $this->price;
    }

    /** Rounded saving, e.g. 20 for "-20%". Null when no offer is running. */
    public function discountPercentage(): ?int
    {
        if (! $this->isOnOffer()) {
            return null;
        }

        $fullCents = Money::toCents($this->price);

        return $fullCents > 0
            ? (int) round((1 - Money::toCents($this->discount_price) / $fullCents) * 100)
            : null;
    }
}
