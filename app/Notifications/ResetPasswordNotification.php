<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset email in Spanish, sent through the queue so the request doesn't wait for the mail server.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Restablecer contraseña · veteFlow')
            ->greeting('¡Hola!')
            ->line('Recibimos un pedido para restablecer la contraseña de tu cuenta.')
            ->action('Elegir contraseña nueva', $url)
            ->line('El enlace vence en '.config('auth.passwords.users.expire').' minutos.')
            ->line('Si no lo pediste vos, ignorá este email.')
            ->salutation('veteFlow');
    }
}
