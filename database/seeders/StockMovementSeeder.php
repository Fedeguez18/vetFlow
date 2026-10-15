<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Initial "ingreso" movements: one per lot, or one per product for the rest.
 */
class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::value('id');
        $date = now()->subDays(30)->setTime(9, 0);

        Product::with('lots')->each(function (Product $product) use ($userId, $date) {
            $entries = $product->tracks_expiry
                ? $product->lots->map(fn ($lot) => [$lot->id, $lot->quantity])
                : collect($product->stock > 0 ? [[null, $product->stock]] : []);

            foreach ($entries as [$lotId, $quantity]) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'lot_id' => $lotId,
                    'type' => 'ingreso',
                    'quantity' => $quantity,
                    'reason' => 'Stock inicial',
                    'supplier_id' => $product->supplier_id,
                    'user_id' => $userId,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        });
    }
}
