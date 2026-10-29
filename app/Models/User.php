<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token', 'pos_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Full access: POS and every ERP screen.
     */
    public const ROLE_ADMIN = 'Administrador';

    /**
     * Mostrador only: can use the POS but not the ERP.
     */
    public const ROLE_SELLER = 'Vendedor';

    /**
     * Roles an administrator can assign from the Usuarios screen.
     */
    public const ROLES = [
        self::ROLE_ADMIN => 'Administrador',
        self::ROLE_SELLER => 'Vendedor',
    ];

    /**
     * New accounts start active and with the least privileged role.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_SELLER,
        'is_active' => true,
    ];

    /**
     * Active administrators only: a baja takes the ERP away even before the session ends.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN && $this->is_active;
    }

    /**
     * The POS code is stored as a keyed hash: unique, so the code alone identifies the user,
     * and searchable without keeping it in plain text.
     */
    public static function hashPosCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    public function hasPosCode(): bool
    {
        return $this->pos_code !== null;
    }

    /**
     * Where the user lands after logging in.
     */
    public function homeUrl(): string
    {
        return $this->isAdmin() ? route('erp.inventory') : route('pos.desk');
    }

    /**
     * Send the (queued, Spanish) password reset email.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
