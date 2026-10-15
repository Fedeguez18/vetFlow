<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Mi perfil')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfile(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'name.required' => 'Ingresá tu nombre.',
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'El email no es válido.',
            'email.lowercase' => 'El email tiene que estar en minúsculas.',
            'email.unique' => 'Ya existe una cuenta con ese email.',
        ]);

        $user->update($validated);

        $this->dispatch('toast', message: 'Datos actualizados.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Ingresá tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresá la contraseña nueva.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        Auth::user()->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->dispatch('toast', message: 'Contraseña actualizada.');
    }

    public function render()
    {
        return view('livewire.auth.profile');
    }
}
