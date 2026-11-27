<?php

namespace App\Models;

use App\Support\Pricing;
use App\Support\Quantity;
use Database\Factories\SaleItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'product_id', 'quantity', 'sold_by_weight', 'unit_price', 'subtotal'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sold_by_weight' => 'boolean',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * "3" units or "2,5 kg" / "350 g".
     */
    public function formattedQuantity(): string
    {
        return Quantity::format($this->quantity, $this->sold_by_weight);
    }

    /**
     * "$8.000/kg" for weight items, the plain unit price otherwise.
     */
    public function formattedUnitPrice(): string
    {
        return Pricing::money($this->unit_price).($this->sold_by_weight ? '/kg' : '');
    }

    /**
     * Get the sale that owns the item.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product for the item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
