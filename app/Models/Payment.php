<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['producer_id', 'amount', 'payment_method', 'notes', 'user_id'])]
class Payment extends Model
{
    /**
     * Ways a producer can pay their account.
     */
    public const METHODS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'cheque' => 'Cheque',
        'tarjeta' => 'Tarjeta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Receipt number: P-00000001.
     *
     * @return Attribute<string, never>
     */
    protected function voucherNumber(): Attribute
    {
        return Attribute::get(fn () => sprintf('P-%08d', $this->id));
    }

    /**
     * Get the producer that made the payment.
     *
     * @return BelongsTo<Producer, $this>
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    /**
     * Get the user who registered the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
