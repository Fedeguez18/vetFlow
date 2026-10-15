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
