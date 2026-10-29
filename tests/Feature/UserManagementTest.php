<?php

namespace Tests\Feature;

use App\Livewire\Erp\UserManagement;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_reachable_from_the_erp_tabs(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/erp/inventario')->assertOk()->assertSee(route('erp.users'));
        $this->get('/erp/usuarios')->assertOk()->assertSee('Usuarios');
    }

    public function test_a_seller_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/erp/usuarios')
            ->assertForbidden();
    }

    public function test_a_seller_is_created_with_only_a_name_and_gets_a_pos_code(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(UserManagement::class)
            ->set('name', '  Laura Gómez ')
            ->set('role', User::ROLE_SELLER)
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSet('newCode.name', 'Laura Gómez');

        $code = $component->get('newCode.code');
        $seller = User::where('name', 'Laura Gómez')->sole();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(User::ROLE_SELLER, $seller->role);
        $this->assertNull($seller->email);
        $this->assertNull($seller->password);
        $this->assertNotSame($code, $seller->pos_code);
        $this->assertTrue($seller->is(app(UserService::class)->findByPosCode($code)));
    }

    public function test_an_admin_needs_email_and_password_for_the_erp(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->set('name', 'Diego')
            ->set('role', User::ROLE_ADMIN)
            ->call('saveUser')
            ->assertHasErrors(['email' => 'required', 'password' => 'required'])
            ->set('email', ' Diego@Veterinaria.com ')
            ->set('password', 'secreto123')
            ->call('saveUser')
            ->assertHasNoErrors()
            ->assertSet('newCode.name', 'Diego');

        $admin = User::where('email', 'diego@veterinaria.com')->sole();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('secreto123', $admin->password));
        $this->assertTrue($admin->hasPosCode());
    }

    public function test_a_new_code_replaces_the_previous_one(): void
    {
        $seller = User::factory()->create();
        $users = app(UserService::class);
        $previous = $users->generatePosCode($seller);
        $this->actingAs(User::factory()->admin()->create());

        $code = Livewire::test(UserManagement::class)
            ->call('regeneratePosCode', $seller->id)
            ->get('newCode.code');

        $this->assertNull($users->findByPosCode($previous));
        $this->assertTrue($seller->is($users->findByPosCode($code)));
    }

    public function test_the_form_validates_the_input(): void
    {
        User::factory()->create(['email' => 'laura@veterinaria.com']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->set('name', '')
            ->set('email', 'laura@veterinaria.com')
            ->set('role', 'Superusuario')
            ->set('password', 'corta')
            ->call('saveUser')
            ->assertHasErrors(['name', 'email', 'role', 'password']);

        $this->assertSame(2, User::count());
    }

    public function test_editing_changes_the_role_and_keeps_the_password_when_left_empty(): void
    {
        $seller = User::factory()->create(['password' => 'vieja1234']);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->call('editUser', $seller->id)
            ->assertSet('email', $seller->email)
            ->set('role', User::ROLE_ADMIN)
            ->call('saveUser')
            ->assertHasNoErrors();

        $seller->refresh();
        $this->assertTrue($seller->isAdmin());
        $this->assertTrue(Hash::check('vieja1234', $seller->password));
    }

    public function test_an_admin_cannot_remove_their_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->call('editUser', $admin->id)
            ->set('role', User::ROLE_SELLER)
            ->call('saveUser')
            ->assertHasErrors(['role' => 'No podés quitarte el rol de administrador a vos mismo.']);

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_a_user_can_be_given_a_baja_and_reactivated(): void
    {
        $seller = User::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(UserManagement::class)->call('deactivateUser', $seller->id);

        $this->assertFalse($seller->fresh()->is_active);

        $component->call('reactivateUser', $seller->id);

        $this->assertTrue($seller->fresh()->is_active);
    }

    public function test_an_admin_cannot_give_themselves_a_baja(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->call('deactivateUser', $admin->id)
            ->assertDispatched('toast', message: 'No podés darte de baja a vos mismo.', type: 'error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_an_admin_given_a_baja_loses_the_erp(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get('/erp/usuarios')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
