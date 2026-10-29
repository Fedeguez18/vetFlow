<?php

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled_by_default(): void
    {
        $this->get('/registro')->assertNotFound();
        $this->withoutVite()->get('/login')->assertOk()->assertDontSee('Crear cuenta');
    }

    public function test_a_visitor_can_register_when_enabled(): void
    {
        config(['auth.registration' => true]);
        $this->withoutVite();

        $this->get('/login')->assertOk()->assertSee('Crear cuenta');
        $this->get('/registro')->assertOk();

        Livewire::test(Register::class)
            ->set('name', 'Laura')
            ->set('email', 'laura@example.com')
            ->set('password', 'secreto123')
            ->set('password_confirmation', 'secreto123')
            ->call('register')
            ->assertRedirect(route('pos.desk'));

        $user = User::where('email', 'laura@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::ROLE_SELLER, $user->role);
    }

    public function test_registration_validates_the_input(): void
    {
        config(['auth.registration' => true]);
        User::factory()->create(['email' => 'laura@example.com']);

        Livewire::test(Register::class)
            ->set('name', '')
            ->set('email', 'laura@example.com')
            ->set('password', 'secreto123')
            ->set('password_confirmation', 'otra-cosa')
            ->call('register')
            ->assertHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }
}
