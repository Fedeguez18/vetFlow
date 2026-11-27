<?php

namespace App\Models;

use App\Support\Quantity;
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
    'sold_by_weight',
    'bulk_product_id',
    'bag_weight_grams',
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
            'sold_by_weight' => 'boolean',
            'bag_weight_grams' => 'integer',
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
     * The loose (by weight) product a closed bag is poured into when it is opened.
     *
     * @return BelongsTo<Product, $this>
     */
    public function bulkProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bulk_product_id');
    }

    /**
     * Closed bags that are opened into this loose product when it is sold.
     *
     * @return HasMany<Product, $this>
     */
    public function bags(): HasMany
    {
        return $this->hasMany(Product::class, 'bulk_product_id');
    }

    /**
     * A closed bag that can be opened into its loose product.
     */
    public function canBeOpened(): bool
    {
        return ! $this->sold_by_weight && $this->bulk_product_id !== null && $this->bag_weight_grams > 0;
    }

    /**
     * Amount of a sale line: price per unit, or per kg for products sold by weight (quantity in grams).
     */
    public function lineTotal(int $quantity): float
    {
        return Quantity::lineTotal((float) $this->price, $quantity, $this->sold_by_weight);
    }

    /**
     * "3" units or "2,5 kg" / "350 g".
     */
    public function formatQuantity(int $quantity): string
    {
        return Quantity::format($quantity, (bool) $this->sold_by_weight);
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
        // Loose food has no stock of its own: the alert belongs to its closed bags.
        if ($this->sold_by_weight) {
            return $this->sellableStock() > 0 ? 'ok' : 'critical';
        }

        return match (true) {
            $this->stock <= self::CRITICAL_STOCK => 'critical',
            $this->stock < $this->min_stock => 'low',
            default => 'ok',
        };
    }

    /**
     * Units to order so the stock reaches twice the minimum, and at least gets out of critical.
     */
    public function suggestedRestock(): int
    {
        if ($this->sold_by_weight) {
            return 0;
        }

        return max(0, max(2 * $this->min_stock, self::CRITICAL_STOCK + 1) - $this->stock);
    }

    /**
     * Units that can be sold: every unit for products without expiry, only the non-expired lots otherwise.
     * Uses the aggregate loaded by withSellableStock() when present.
     */
    public function sellableStock(): int
    {
        if ($this->sold_by_weight) {
            return $this->openBagStock() + $this->bags->sum(fn (Product $bag) => $bag->sellableStock() * (int) $bag->bag_weight_grams);
        }

        if (! $this->tracks_expiry) {
            return (int) $this->stock;
        }

        if (array_key_exists('sellable_lots_quantity', $this->attributes)) {
            return (int) $this->attributes['sellable_lots_quantity'];
        }

        return (int) $this->lots()->sellable()->sum('quantity');
    }

    /**
     * Grams left in the open bag of a loose product (its "stock" column).
     */
    public function openBagStock(): int
    {
        return $this->sold_by_weight ? max(0, (int) $this->stock) : 0;
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
     * Products whose stock_status is not "ok": critical or under the minimum.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('sold_by_weight', false)->where(fn (Builder $query) => $query
            ->where('stock', '<=', self::CRITICAL_STOCK)
            ->orWhereColumn('stock', '<', 'min_stock'));
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
