<?php

namespace Database\Factories;

use App\Models\Lot;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->tracksExpiry(),
            'lot_number' => 'L-'.fake()->unique()->numerify('####').strtoupper(fake()->randomLetter()),
            'expiration_date' => today()->addDays(fake()->numberBetween(90, 720)),
            'quantity' => fake()->numberBetween(6, 80),
        ];
    }

    /**
     * Lot expiring in the given number of days (negative = already expired).
     */
    public function expiresIn(int $days): static
    {
        return $this->state(fn () => ['expiration_date' => today()->addDays($days)]);
    }
}
