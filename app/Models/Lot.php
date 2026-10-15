<?php

namespace App\Models;

use Database\Factories\LotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'lot_number', 'expiration_date', 'quantity'])]
class Lot extends Model
{
    /** @use HasFactory<LotFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiration_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the product the lot belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Lots with units that can still be sold: a lot expiring today is sellable, from tomorrow it is expired.
     *
     * @param  Builder<Lot>  $query
     */
    public function scopeSellable(Builder $query): void
    {
        $query->where('quantity', '>', 0)->whereDate('expiration_date', '>=', today());
    }

    /**
     * Get the stock movements of the lot.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Days until expiration (negative when already expired).
     */
    public function getDaysLeftAttribute(): int
    {
        return (int) today()->diffInDays($this->expiration_date, false);
    }

    /**
     * Expiry status: expired, critical (< 30 days), warning (< 60 days) or ok.
     */
    public function getExpiryStatusAttribute(): string
    {
        return Product::expiryStatusFor($this->days_left);
    }
}
