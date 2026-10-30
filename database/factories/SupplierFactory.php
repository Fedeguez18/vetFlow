<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_name' => fake()->company().' S.A.',
            'cuit' => '30-'.fake()->unique()->numerify('########').'-'.fake()->randomDigit(),
            'phone' => fake()->numerify('(0358) 46#-####'),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }

    /**
     * A supplier that was given a baja.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
