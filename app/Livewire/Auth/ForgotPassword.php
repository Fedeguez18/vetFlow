<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest')]
#[Title('Recuperar contraseña')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'El email no es válido.',
        ]);

        $result = Password::sendResetLink(['email' => $this->email]);

        if ($result === Password::RESET_THROTTLED) {
            $this->addError('email', 'Ya te enviamos un enlace. Esperá un minuto antes de pedir otro.');

            return;
        }

        // Same message whether or not the account exists, so emails cannot be probed.
        $this->status = 'Si el email está registrado, te enviamos un enlace para restablecer la contraseña.';
        $this->reset('email');
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
