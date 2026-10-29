<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts: the administrator logs in to the ERP with email and password; both open the
     * mostrador caja with their code (123456 and 654321).
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Diego',
            'email' => 'admin@demo.test',
            'password' => 'password',
        ])->forceFill(['pos_code' => User::hashPosCode('123456')])->save();

        User::factory()->create([
            'name' => 'Laura',
            'email' => null,
            'password' => null,
        ])->forceFill(['pos_code' => User::hashPosCode('654321')])->save();
    }
}
