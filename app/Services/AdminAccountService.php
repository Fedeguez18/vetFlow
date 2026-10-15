<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

class AdminAccountService
{
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * Create the admin user, or reset its name and password when the email already exists.
     *
     * @throws InvalidArgumentException when the password is too short.
     */
    public function ensure(string $email, string $password, string $name = 'Administrador'): User
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('ADMIN_PASSWORD debe tener al menos '.self::MIN_PASSWORD_LENGTH.' caracteres.');
        }

        return User::updateOrCreate(
            ['email' => trim($email)],
            ['name' => $name ?: 'Administrador', 'password' => $password],
        );
    }
}
