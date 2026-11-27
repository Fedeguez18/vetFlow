<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product waiting on the label sheet, with how many stickers to print for it.
 */
#[Fillable(['product_id', 'copies'])]
class BarcodeLabel extends Model
{
    /**
     * Labels per A4 sheet: 3 columns × 8 rows of 70 × 37 mm.
     */
    public const PER_SHEET = 24;

    public const MAX_COPIES = 500;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'copies' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Put a product on the sheet (or update its copies if it is already there).
     */
    public static function queue(Product $product, int $copies): self
    {
        return self::updateOrCreate(
            ['product_id' => $product->id],
            ['copies' => min(self::MAX_COPIES, max(1, $copies))],
        );
    }
}
