<?php

namespace App\Models;

use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A caja: opened with a POS code on a terminal, every sale until the close is in the name of its user.
 */
#[Fillable(['pos_terminal_id', 'user_id', 'opening_amount', 'last_activity_at', 'closed_at', 'expected_cash', 'declared_cash', 'closed_automatically', 'closed_by', 'reviewed_at', 'reviewed_by'])]
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use HasFactory;

    /**
     * Browser cookie (gone when the browser closes) that keeps the mostrador unlocked for this caja.
     */
    public const UNLOCK_COOKIE = 'pos_caja';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'declared_cash' => 'decimal:2',
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
            'closed_automatically' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Declared minus expected cash: negative when money is missing. Null while open or when nobody counted it.
     */
    public function difference(): ?float
    {
        return $this->isOpen() || $this->declared_cash === null ? null : round((float) $this->declared_cash - (float) $this->expected_cash, 2);
    }

    /**
     * Last time the mostrador was used with this caja.
     */
    public function lastActivityAt(): Carbon
    {
        return $this->last_activity_at ?? $this->created_at;
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PosTerminal, $this>
     */
    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who closed the caja counting the cash.
     *
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
