<?php

namespace Database\Factories;

use App\Models\PosTerminal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PosTerminal>
 */
class PosTerminalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Mostrador',
            'token_hash' => PosTerminal::hashToken(Str::random(40)),
        ];
    }

    /**
     * Use a known token, to send it back in the terminal cookie.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => PosTerminal::hashToken($token),
        ]);
    }
}
