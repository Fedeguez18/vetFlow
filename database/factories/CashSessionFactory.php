<?php

namespace Database\Factories;

use App\Models\CashSession;
use App\Models\PosTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_terminal_id' => PosTerminal::factory(),
            'user_id' => User::factory(),
            'opening_amount' => 0,
        ];
    }
}
