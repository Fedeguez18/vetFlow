<?php

namespace Database\Seeders;

use App\Models\Lot;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Lots only for products that track expiry; their stock becomes the sum of their lots.
 * Every factory product gets a single lot expiring in more than 60 days so the alert
 * counters come only from the hand-written catalog.
 */
class LotSeeder extends Seeder
{
    public function run(): void
    {
        $catalogLots = collect(ProductSeeder::CATALOG)->filter(fn ($item) => isset($item['lots']))->pluck('lots', 'sku');

        Product::where('tracks_expiry', true)->each(function (Product $product) use ($catalogLots) {
            $lots = $catalogLots[$product->sku] ?? [[
                'L-'.str_pad((string) $product->id, 4, '0', STR_PAD_LEFT).fake()->randomLetter(),
                fake()->numberBetween(90, 720),
                fake()->numberBetween(max(12, $product->min_stock + 1), 120),
            ]];

            foreach ($lots as [$number, $days, $quantity]) {
                Lot::create([
                    'product_id' => $product->id,
                    'lot_number' => strtoupper($number),
                    'expiration_date' => today()->addDays($days),
                    'quantity' => $quantity,
                ]);
            }

            $product->syncStockFromLots();
        });
    }
}
