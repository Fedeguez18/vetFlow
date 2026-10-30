<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(protected StockService $stock) {}

    /**
     * Create a product, record its first price and load the initial stock (as lots when it tracks expiry).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{lot_number: string, quantity: int, expiration_date: string}>  $lots
     */
    public function create(array $attributes, array $lots = [], int $initialStock = 0, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($attributes, $lots, $initialStock, $userId) {
            $product = Product::create(['stock' => 0] + $attributes);

            $this->stock->recordPrice($product, $userId);

            if ($product->tracks_expiry) {
                foreach ($lots as $lot) {
                    $this->stock->restock(
                        $product,
                        (int) $lot['quantity'],
                        $lot['lot_number'],
                        $lot['expiration_date'],
                        $product->supplier_id,
                        'Stock inicial',
                        $userId,
                    );
                }
            } elseif ($initialStock > 0) {
                $this->stock->restock($product, $initialStock, supplierId: $product->supplier_id, reason: 'Stock inicial', userId: $userId);
            }

            return $product->fresh();
        });
    }

    /**
     * Update the product data (never the stock) and keep the price history when any price field changes.
     * Expiry tracking can only be switched while the product has no stock: the units already loaded
     * would otherwise stop being sellable (no lots) or lose their expiry control (lots ignored).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws StockException
     */
    public function update(Product $product, array $attributes, ?int $userId = null): Product
    {
        unset($attributes['stock']);

        return DB::transaction(function () use ($product, $attributes, $userId) {
            if (array_key_exists('tracks_expiry', $attributes) && (bool) $attributes['tracks_expiry'] !== (bool) $product->tracks_expiry) {
                $stock = (int) Product::query()->whereKey($product->id)->lockForUpdate()->value('stock');

                if ($stock > 0) {
                    throw new StockException("No se puede cambiar el control de vencimientos de {$product->name} con {$stock} unidades en stock. "
                        .'Ajustá el stock a 0, cambiá la opción y volvé a ingresar las unidades.');
                }
            }

            $product->fill($attributes);
            $priceChanged = $product->isDirty(['cost_price', 'markup_percent', 'vat_percent', 'price']);
            $product->save();

            if ($priceChanged) {
                $this->stock->recordPrice($product, $userId);
            }

            return $product;
        });
    }
}
