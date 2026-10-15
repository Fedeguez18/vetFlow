<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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

// Runs on deploy when SEED_DEMO=true (see Dockerfile). Only seeds an empty catalog, so redeploys never duplicate data.
Artisan::command('app:seed-demo', function () {
    if (Product::exists()) {
        $this->info('La base ya tiene productos: no se cargan datos de demo.');

        return 0;
    }

    if (! User::exists()) {
        $this->error('No hay usuarios: creá el administrador antes de cargar los datos de demo.');

        return 1;
    }

    DB::transaction(fn () => $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]));

    $this->info('Datos de demo cargados: '.Product::count().' productos.');

    return 0;
})->purpose('Load the demo catalog, customers and sales into an empty database');
