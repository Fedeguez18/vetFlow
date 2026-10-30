<?php

namespace Database\Factories;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceHistory>
 */
class PriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state: the current price of the product.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'cost_price' => fn (array $attributes) => Product::find($attributes['product_id'])->cost_price,
            'markup_percent' => fn (array $attributes) => Product::find($attributes['product_id'])->markup_percent,
            'vat_percent' => fn (array $attributes) => Product::find($attributes['product_id'])->vat_percent,
            'price' => fn (array $attributes) => Product::find($attributes['product_id'])->price,
            'user_id' => null,
        ];
    }
}
