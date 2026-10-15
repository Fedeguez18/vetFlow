<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs on every deploy (see Dockerfile): keeps the admin account in sync with ADMIN_EMAIL / ADMIN_PASSWORD.
Artisan::command('app:ensure-admin {--email=} {--password=} {--name=Administrador}', function () {
    $email = trim((string) $this->option('email'));
    $password = (string) $this->option('password');

    if ($email === '' || $password === '') {
        $this->warn('ADMIN_EMAIL / ADMIN_PASSWORD no definidos: no se crea el usuario administrador.');

        return 0;
    }

    if (strlen($password) < 8) {
        $this->error('ADMIN_PASSWORD debe tener al menos 8 caracteres.');

        return 1;
    }

    $user = User::updateOrCreate(
        ['email' => $email],
        ['name' => $this->option('name') ?: 'Administrador', 'password' => $password],
    );

    $this->info(($user->wasRecentlyCreated ? 'Administrador creado: ' : 'Administrador actualizado: ').$email);

    return 0;
})->purpose('Create or update the admin user from the given credentials');
