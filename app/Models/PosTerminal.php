<?php

namespace App\Models;

use Database\Factories\PosTerminalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A PC an administrator enabled as mostrador: its browser keeps the token in a cookie.
 */
#[Fillable(['name', 'token_hash', 'user_id', 'last_used_at'])]
#[Hidden(['token_hash'])]
class PosTerminal extends Model
{
    /** @use HasFactory<PosTerminalFactory> */
    use HasFactory;

    /**
     * Cookie that identifies the terminal.
     */
    public const COOKIE = 'pos_terminal';

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get the administrator who enabled the terminal.
     *
     * @return BelongsTo<User, $this>
     */
    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<CashSession, $this>
     */
    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * The caja currently open on this terminal, if any.
     *
     * @return HasOne<CashSession, $this>
     */
    public function openCashSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->whereNull('closed_at')->latestOfMany();
    }
}
