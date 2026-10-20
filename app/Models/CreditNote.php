<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Credit to a producer's account for a reason other than a payment (bonificación, price error...).
 * It lowers the debt like a payment but no money comes in, so the cash audit ignores it.
 */
#[Fillable(['producer_id', 'amount', 'reason'])]
class CreditNote extends Model
{
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
     * Voucher number shown in the statement: NC-00000001.
     *
     * @return Attribute<string, never>
     */
    protected function voucherNumber(): Attribute
    {
        return Attribute::get(fn () => sprintf('NC-%08d', $this->id));
    }

    /**
     * Get the producer the credit note was issued to.
     *
     * @return BelongsTo<Producer, $this>
     */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }
}
