<?php

namespace Tests\Feature;

use App\Livewire\Auth\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_see_the_profile(): void
    {
        $this->get('/perfil')->assertRedirect('/login');
    }

    public function test_the_profile_is_linked_from_both_terminals(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['name' => 'Diego']));

        $this->get('/perfil')->assertOk()->assertSee('Mi perfil')->assertSee('Diego');
        $this->get('/pos')->assertOk()->assertSee(route('profile'));
        $this->get('/erp/inventario')->assertOk()->assertSee(route('profile'));
    }

    public function test_the_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('name', 'Diego Pérez')
            ->set('email', 'diego@example.com')
            ->call('updateProfile')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('Diego Pérez', $user->fresh()->name);
        $this->assertSame('diego@example.com', $user->fresh()->email);
    }

    public function test_the_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'otro@example.com']);
        $this->actingAs(User::factory()->create());

        Livewire::test(Profile::class)
            ->set('email', 'otro@example.com')
            ->call('updateProfile')
            ->assertHasErrors('email');
    }

    public function test_the_password_changes_only_with_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'vieja1234']);
        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->set('current_password', 'incorrecta')
            ->set('password', 'nueva1234')
            ->set('password_confirmation', 'nueva1234')
            ->call('updatePassword')
            ->assertHasErrors('current_password');

        Livewire::test(Profile::class)
            ->set('current_password', 'vieja1234')
            ->set('password', 'nueva1234')
            ->set('password_confirmation', 'nueva1234')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '');

        $this->assertTrue(Hash::check('nueva1234', $user->fresh()->password));
    }
}
