<?php

namespace App\Services;

use App\Exceptions\UserAccountException;
use App\Models\CashSession;
use App\Models\User;

class UserService
{
    /**
     * Create an account, or update the given one. An empty password keeps the current one.
     * The acting administrator can't take the admin role away from themselves, so the ERP
     * always keeps at least one active administrator.
     *
     * @param  array{name: string, role: string, email?: string|null, password?: string|null}  $attributes
     *
     * @throws UserAccountException
     */
    public function save(array $attributes, User $actor, ?User $user = null): User
    {
        if ($user?->is($actor) && $attributes['role'] !== User::ROLE_ADMIN) {
            throw new UserAccountException('No podés quitarte el rol de administrador a vos mismo.');
        }

        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        if ($user) {
            $user->update($attributes);

            return $user;
        }

        return User::create($attributes);
    }

    /**
     * Give the account a baja: it can't log in anymore and any open session is closed on its next request.
     *
     * @throws UserAccountException
     */
    public function deactivate(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new UserAccountException('No podés darte de baja a vos mismo.');
        }

        if (CashSession::query()->open()->where('user_id', $user->id)->exists()) {
            throw new UserAccountException("{$user->name} tiene la caja abierta. Tiene que cerrarla antes de darlo de baja.");
        }

        $user->update(['is_active' => false]);
    }

    public function reactivate(User $user): void
    {
        $user->update(['is_active' => true]);
    }

    /**
     * Give the user a new 6-digit code for the POS, replacing the previous one. Only its hash is
     * stored, so the plain code is returned to show it once.
     */
    public function generatePosCode(User $user): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (User::query()->where('pos_code', User::hashPosCode($code))->exists());

        $user->forceFill(['pos_code' => User::hashPosCode($code)])->save();

        return $code;
    }

    /**
     * The active user the code belongs to.
     */
    public function findByPosCode(string $code): ?User
    {
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        return User::query()->where('pos_code', User::hashPosCode($code))->where('is_active', true)->first();
    }
}
