<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correlative voucher numbers (first..last) a terminal took in advance; next_number is the first one it hasn't used.
 */
#[Fillable(['pos_terminal_id', 'prefix', 'first_number', 'last_number', 'next_number'])]
class VoucherBlock extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_number' => 'integer',
            'last_number' => 'integer',
            'next_number' => 'integer',
        ];
    }

    /**
     * Blocks that still have unused numbers, oldest first.
     *
     * @param  Builder<VoucherBlock>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->whereColumn('next_number', '<=', 'last_number')->orderBy('first_number');
    }

    public function remaining(): int
    {
        return max(0, $this->last_number - $this->next_number + 1);
    }

    /**
     * @return BelongsTo<PosTerminal, $this>
     */
    public function posTerminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }
}
