<?php

namespace App\Models;

use Database\Factories\ProducerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'business_name', 'cuit', 'phone', 'iva_condition', 'discount_percent', 'balance', 'credit_limit', 'is_active'])]
class Producer extends Model
{
    /** @use HasFactory<ProducerFactory> */
    use HasFactory;

    /**
     * Name of the generic walk-in customer record.
     */
    public const WALK_IN = 'Consumidor Final';

    public const IVA_CONDITIONS = ['Responsable Inscripto', 'Monotributista', 'Exento', 'Consumidor Final'];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

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
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function isWalkIn(): bool
    {
        return $this->name === self::WALK_IN;
    }

    /**
     * Saldo acreedor: the producer paid more than they owed.
     */
    public function hasCreditBalance(): bool
    {
        return (float) $this->balance < 0;
    }

    /**
     * How much more can be charged before reaching the credit limit (null when there is no limit).
     */
    public function availableCredit(): ?float
    {
        return $this->credit_limit === null ? null : round((float) $this->credit_limit - (float) $this->balance, 2);
    }

    /**
     * Whether charging the amount would take the balance above the credit limit.
     */
    public function exceedsCreditLimit(float $charge): bool
    {
        return $this->credit_limit !== null
            && (int) round(((float) $this->balance + $charge) * 100) > (int) round((float) $this->credit_limit * 100);
    }

    /**
     * Only accounts that were not given a baja.
     *
     * @param  Builder<Producer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
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
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('business_name', "%{$term}%")
            ->orWhereLike('cuit', "%{$term}%")));
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

    /**
     * Get the credit notes issued to the producer.
     *
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }
}
