<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Crear cuenta')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        abort_unless(config('auth.registration'), 404);
    }

    public function register()
    {
        abort_unless(config('auth.registration'), 404);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Ingresá tu nombre.',
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'El email no es válido.',
            'email.lowercase' => 'El email tiene que estar en minúsculas.',
            'email.unique' => 'Ya existe una cuenta con ese email.',
            'password.required' => 'Ingresá una contraseña.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = User::create($validated);

        Auth::login($user);
        session()->regenerate();

        return $this->redirect(route('pos.desk'));
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
