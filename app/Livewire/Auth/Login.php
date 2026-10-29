<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Ingresar')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'El email no es válido.',
            'password.required' => 'Ingresá tu contraseña.',
        ]);

        $key = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Probá de nuevo en '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        if (! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => 'Email o contraseña incorrectos.']);
        }

        /** @var User $user */
        $user = Auth::getLastAttempted();

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Tu usuario fue dado de baja. Consultá con el administrador.']);
        }

        Auth::login($user, $this->remember);
        RateLimiter::clear($key);
        session()->regenerate();

        return $this->redirectIntended($user->homeUrl(), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
