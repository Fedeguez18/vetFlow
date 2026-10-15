<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['voucher_number', 'type', 'payment_method', 'producer_id', 'total', 'paid', 'status'])]
class Sale extends Model
{
    /**
     * Remito statuses that still carry debt.
     */
    public const UNPAID_STATUSES = ['pendiente', 'parcial'];

    /**
     * Remitos with debt, oldest first (the order payments are applied in).
     *
     * @param  Builder<Sale>  $query
     */
    public function scopeUnpaidRemitos(Builder $query): void
    {
        $query->where('type', 'remito')
            ->whereIn('status', self::UNPAID_STATUSES)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
        ];
    }

    /**
     * Get the producer for the sale.
     *
     * @return BelongsTo<Producer, $this>
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * Get the items for the sale.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
