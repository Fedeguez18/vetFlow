<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sku',
    'barcode',
    'name',
    'description',
    'category_id',
    'supplier_id',
    'cost_price',
    'markup_percent',
    'vat_percent',
    'price',
    'tracks_expiry',
    'stock',
    'min_stock',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Stock at or below this level is critical (red alert, "Reposición urgente").
     */
    public const CRITICAL_STOCK = 5;

    /**
     * Inventory sort options.
     */
    public const SORTS = [
        'name' => 'Nombre (A–Z)',
        'stock' => 'Existencias (menor primero)',
        'expiry' => 'Vencimiento más próximo',
        'price_desc' => 'Precio (mayor primero)',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'markup_percent' => 'decimal:2',
            'vat_percent' => 'decimal:2',
            'price' => 'decimal:2',
            'tracks_expiry' => 'boolean',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<Lot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * The lot with stock that expires first (FEFO).
     *
     * @return HasOne<Lot, $this>
     */
    public function nextLot(): HasOne
    {
        return $this->hasOne(Lot::class)->ofMany(
            ['expiration_date' => 'min', 'id' => 'min'],
            fn (Builder $query) => $query->where('quantity', '>', 0),
        );
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<PriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    /**
     * Get the sale items for the product.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Map days until expiration to a status: expired, critical (< 30), warning (< 60) or ok.
     */
    public static function expiryStatusFor(?int $daysLeft): string
    {
        return match (true) {
            $daysLeft === null => 'ok',
            $daysLeft < 0 => 'expired',
            $daysLeft < 30 => 'critical',
            $daysLeft < 60 => 'warning',
            default => 'ok',
        };
    }

    /**
     * Days until the next lot expires, or null when the product doesn't track expiry.
     */
    public function getExpiryDaysAttribute(): ?int
    {
        return $this->tracks_expiry ? $this->nextLot?->days_left : null;
    }

    /**
     * Expiry status of the lot that expires first: expired, critical, warning or ok.
     */
    public function getExpiryStatusAttribute(): string
    {
        return self::expiryStatusFor($this->expiry_days);
    }

    /**
     * Stock status: critical (<= 5, including out of stock), low (under the minimum) or ok.
     */
    public function getStockStatusAttribute(): string
    {
        return match (true) {
            $this->stock <= self::CRITICAL_STOCK => 'critical',
            $this->stock < $this->min_stock => 'low',
            default => 'ok',
        };
    }

    /**
     * Units that can be sold: every unit for products without expiry, only the non-expired lots otherwise.
     * Uses the aggregate loaded by withSellableStock() when present.
     */
    public function sellableStock(): int
    {
        if (! $this->tracks_expiry) {
            return (int) $this->stock;
        }

        if (array_key_exists('sellable_lots_quantity', $this->attributes)) {
            return (int) $this->attributes['sellable_lots_quantity'];
        }

        return (int) $this->lots()->sellable()->sum('quantity');
    }

    /**
     * Units in stock that cannot be sold because their lot already expired.
     */
    public function expiredStock(): int
    {
        return max(0, (int) $this->stock - $this->sellableStock());
    }

    /**
     * Eager load the sellable lot quantity used by sellableStock().
     *
     * @param  Builder<Product>  $query
     */
    public function scopeWithSellableStock(Builder $query): void
    {
        $query->withSum(['lots as sellable_lots_quantity' => fn (Builder $lots) => $lots->sellable()], 'quantity');
    }

    /**
     * Recompute the stock as the sum of the lots for products that track expiry.
     */
    public function syncStockFromLots(): void
    {
        if ($this->tracks_expiry) {
            $this->update(['stock' => (int) $this->lots()->sum('quantity')]);
        }
    }

    /**
     * Inventory listing filters shared by the catalog view and the CSV export.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeInventory(Builder $query, string $search = '', ?int $categoryId = null, string $sort = 'name'): void
    {
        $term = trim($search);

        $query
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('barcode', "{$term}%")
                ->orWhereLike('sku', "{$term}%")))
            ->when($categoryId, fn (Builder $query) => $query->where('category_id', $categoryId));

        match ($sort) {
            'stock' => $query->orderBy('stock')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'expiry' => $query
                ->addSelect(['next_expiration' => Lot::query()
                    ->select('expiration_date')
                    ->whereColumn('lots.product_id', 'products.id')
                    ->where('quantity', '>', 0)
                    ->orderBy('expiration_date')
                    ->limit(1)])
                // Not "next_expiration is null": PostgreSQL rejects an alias inside an ORDER BY expression.
                ->orderByRaw('next_expiration asc nulls last')
                ->orderBy('name'),
            default => $query->orderBy('name'),
        };
    }
}
