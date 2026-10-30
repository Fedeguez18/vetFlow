<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 6),
            'unit_price' => fn (array $attributes) => Product::find($attributes['product_id'])->price,
            'subtotal' => fn (array $attributes) => round((float) $attributes['unit_price'] * $attributes['quantity'], 2),
        ];
    }
}
