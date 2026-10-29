<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_and_out(): void
    {
        $this->withoutVite();
        User::factory()->create(['email' => 'admin@demo.test', 'password' => 'password']);

        $this->get('/login')->assertOk()->assertSee('Ingresar');

        Livewire::test(Login::class)
            ->set('email', 'admin@demo.test')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('pos.desk'));

        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_an_admin_lands_in_the_erp_after_logging_in(): void
    {
        User::factory()->admin()->create(['email' => 'admin@demo.test', 'password' => 'password']);

        Livewire::test(Login::class)
            ->set('email', 'admin@demo.test')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('erp.inventory'));

        $this->get('/')->assertRedirect(route('erp.inventory'));
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'laura@demo.test', 'password' => 'password']);

        Livewire::test(Login::class)
            ->set('email', 'laura@demo.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email' => 'Tu usuario fue dado de baja. Consultá con el administrador.']);

        $this->assertGuest();
    }

    public function test_a_session_is_closed_when_the_user_is_given_a_baja(): void
    {
        $user = User::factory()->create();
        $this->withoutVite();
        $this->actingAs($user)->get('/perfil')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/perfil')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'admin@demo.test', 'password' => 'password']);

        Livewire::test(Login::class)
            ->set('email', 'admin@demo.test')
            ->set('password', 'otra')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
