<?php

namespace Database\Factories;

use App\Models\Producer;
use App\Models\Sale;
use App\Services\CheckoutService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Sales without items nor stock movements. The voucher number is taken from the same counter
 * as the checkout, so a later checkout never repeats it. A remito doesn't touch the producer's
 * balance: set it on the producer when the test needs it.
 *
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state: a paid cash sale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'contado',
            'voucher_number' => fn (array $attributes) => app(CheckoutService::class)->reserveVoucherNumber($attributes['type']),
            'payment_method' => 'efectivo',
            'producer_id' => null,
            'total' => fake()->randomElement([1500, 3200, 4850, 9800, 12500]),
            'paid' => fn (array $attributes) => $attributes['total'],
            'status' => 'pagada',
            'user_id' => null,
            'cash_session_id' => null,
        ];
    }

    /**
     * Unpaid remito charged to a producer's current account (a new producer unless one is given with for()).
     */
    public function remito(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'remito',
            'payment_method' => 'cuenta_corriente',
            'producer_id' => $attributes['producer_id'] ?? Producer::factory(),
            'paid' => 0,
            'status' => 'pendiente',
        ]);
    }
}
