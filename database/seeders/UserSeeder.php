<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Diego',
            'email' => 'admin@demo.test',
            'role' => 'Médico Veterinario',
            'password' => 'password',
        ]);
    }
}
