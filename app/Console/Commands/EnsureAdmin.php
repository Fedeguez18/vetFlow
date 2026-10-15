<?php

namespace App\Console\Commands;

use App\Services\AdminAccountService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Runs on every deploy (see Dockerfile): keeps the admin account in sync with ADMIN_EMAIL / ADMIN_PASSWORD.
 */
class EnsureAdmin extends Command
{
    protected $signature = 'app:ensure-admin {--email=} {--password=} {--name=Administrador}';

    protected $description = 'Create or update the admin user from the given credentials';

    public function handle(AdminAccountService $admins): int
    {
        $email = trim((string) $this->option('email'));
        $password = (string) $this->option('password');

        if ($email === '' || $password === '') {
            $this->warn('ADMIN_EMAIL / ADMIN_PASSWORD no definidos: no se crea el usuario administrador.');

            return self::SUCCESS;
        }

        try {
            $user = $admins->ensure($email, $password, (string) $this->option('name'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($user->wasRecentlyCreated ? 'Administrador creado: ' : 'Administrador actualizado: ').$email);

        return self::SUCCESS;
    }
}
