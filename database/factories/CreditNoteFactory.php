<?php

namespace Database\Factories;

use App\Models\CreditNote;
use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Credit notes without allocation: they don't settle remitos nor change the producer's balance
 * (PaymentService does that).
 *
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
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
            'amount' => fake()->randomElement([500, 1000, 2500]),
            'reason' => fake()->randomElement(['Devolución de mercadería', 'Bonificación comercial', 'Error de facturación']),
        ];
    }
}
