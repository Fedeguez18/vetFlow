<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_links_to_the_recovery_screen(): void
    {
        $this->withoutVite();

        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/recuperar-contrasena')->assertOk()->assertSee('Recuperar contraseña');
    }

    public function test_a_reset_link_is_emailed_and_resets_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'vet@example.com', 'password' => 'vieja1234']);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'vet@example.com')
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('status', fn ($status) => $status !== null);

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token, $user) {
            $token = $notification->token;

            return str_contains($notification->toMail($user)->actionUrl, '/restablecer-contrasena/'.$token);
        });

        $this->withoutVite()->get(route('password.reset', ['token' => $token, 'email' => 'vet@example.com']))->assertOk();

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'vet@example.com')
            ->set('password', 'nueva1234')
            ->set('password_confirmation', 'nueva1234')
            ->call('resetPassword')
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('nueva1234', $user->fresh()->password));
    }

    public function test_unknown_emails_get_the_same_answer(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nadie@example.com')
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('status', fn ($status) => $status !== null);

        Notification::assertNothingSent();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'vet@example.com', 'password' => 'vieja1234']);

        Livewire::test(ResetPassword::class, ['token' => 'token-invalido'])
            ->set('email', 'vet@example.com')
            ->set('password', 'nueva1234')
            ->set('password_confirmation', 'nueva1234')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertTrue(Hash::check('vieja1234', $user->fresh()->password));
    }
}
