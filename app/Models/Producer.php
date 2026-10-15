<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'business_name', 'cuit', 'phone', 'iva_condition', 'discount_percent', 'balance'])]
class Producer extends Model
{
    /**
     * Name of the generic walk-in customer record.
     */
    public const WALK_IN = 'Consumidor Final';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Filter by name, business name or CUIT.
     *
     * @param  Builder<Producer>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        $query->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('name', 'like', "%{$term}%")
            ->orWhere('business_name', 'like', "%{$term}%")
            ->orWhere('cuit', 'like', "%{$term}%")));
    }

    /**
     * Add "unpaid_count": how many remitos still carry debt.
     *
     * @param  Builder<Producer>  $query
     */
    public function scopeWithUnpaidCount(Builder $query): void
    {
        $query->withCount(['sales as unpaid_count' => fn (Builder $sales) => $sales
            ->where('type', 'remito')
            ->whereIn('status', Sale::UNPAID_STATUSES)]);
    }

    /**
     * Get the sales for the producer.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get the payments for the producer.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
