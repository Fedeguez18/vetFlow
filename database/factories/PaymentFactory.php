<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Payments without allocation: they don't settle remitos nor change the producer's balance
 * (PaymentService does that).
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producer_id' => Producer::factory(),
            'amount' => fake()->randomElement([500, 1000, 2500, 5000, 10000]),
            'payment_method' => fake()->randomElement(array_keys(Payment::METHODS)),
            'notes' => null,
            'user_id' => null,
        ];
    }
}
