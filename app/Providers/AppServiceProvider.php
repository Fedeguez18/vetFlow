<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
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
        });
    }
}
