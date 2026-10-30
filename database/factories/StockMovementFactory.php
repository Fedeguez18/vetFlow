<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Movement records only: they don't change the product's stock nor its lots (StockService does that).
 *
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state: an "ingreso".
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'lot_id' => null,
            'type' => 'ingreso',
            'quantity' => fake()->numberBetween(1, 40),
            'reason' => null,
            'supplier_id' => null,
            'sale_id' => null,
            'user_id' => null,
        ];
    }
}
