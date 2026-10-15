<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnsureAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_admin_user(): void
    {
        $this->artisan('app:ensure-admin', ['--email' => 'vet@example.com', '--password' => 'secreto123', '--name' => 'Diego'])
            ->assertSuccessful();

        $user = User::where('email', 'vet@example.com')->sole();
        $this->assertSame('Diego', $user->name);
        $this->assertSame('Médico Veterinario', $user->fresh()->role);
        $this->assertTrue(Hash::check('secreto123', $user->password));
    }

    public function test_it_updates_the_password_of_an_existing_admin(): void
    {
        User::factory()->create(['email' => 'vet@example.com', 'password' => 'vieja1234']);

        $this->artisan('app:ensure-admin', ['--email' => 'vet@example.com', '--password' => 'nueva1234'])
            ->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('nueva1234', User::sole()->password));
    }

    public function test_it_skips_without_failing_when_credentials_are_missing(): void
    {
        $this->artisan('app:ensure-admin', ['--email' => '', '--password' => ''])
            ->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_it_rejects_short_passwords(): void
    {
        $this->artisan('app:ensure-admin', ['--email' => 'vet@example.com', '--password' => 'corta'])
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
