<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_name',
    'contact_name',
    'cuit',
    'iva_condition',
    'phone',
    'email',
    'address',
    'city',
    'payment_terms',
    'notes',
    'is_active',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the products supplied.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Stock entries ("ingreso") recorded with this supplier.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function restocks(): HasMany
    {
        return $this->hasMany(StockMovement::class)->where('type', 'ingreso');
    }

    /**
     * Only suppliers that were not given a baja.
     *
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Address and city in one line, or null when neither is loaded.
     */
    public function fullAddress(): ?string
    {
        return collect([$this->address, $this->city])->filter()->join(', ') ?: null;
    }
}
