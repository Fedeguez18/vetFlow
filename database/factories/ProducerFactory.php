<?php

namespace Database\Factories;

use App\Models\Producer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producer>
 */
class ProducerFactory extends Factory
{
    protected const KINDS = ['Estancia', 'Establecimiento', 'Cabaña', 'Haras', 'Tambo'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::KINDS).' '.fake()->lastName(),
            'business_name' => fake()->name(),
            'cuit' => '20-'.fake()->unique()->numerify('########').'-'.fake()->randomDigit(),
            'phone' => fake()->numerify('358 4##-####'),
            'iva_condition' => fake()->randomElement(Producer::IVA_CONDITIONS),
            'discount_percent' => 0,
            'balance' => 0,
            'credit_limit' => null,
            'is_active' => true,
        ];
    }

    /**
     * Account given a baja: it keeps its history but can't take new remitos.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Account with a maximum debt for new remitos.
     */
    public function withCreditLimit(float $limit = 100000): static
    {
        return $this->state(fn () => ['credit_limit' => $limit]);
    }

    /**
     * Account with the given balance (positive = debt, negative = saldo a favor). It doesn't create
     * the remitos or payments behind it.
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn () => ['balance' => $balance]);
    }
}
