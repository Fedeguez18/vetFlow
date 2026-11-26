<?php

namespace App\Services;

use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\VoucherBlock;
use Illuminate\Support\Facades\DB;

/**
 * Every POS terminal numbers its tickets (C-) and remitos (R-) from blocks of correlative numbers taken
 * from the shared counter in advance. The browser keeps the free numbers, so without internet the paper
 * still comes out with its real number; online sales use the same blocks so the numbers stay in order.
 */
class VoucherBlockService
{
    /**
     * Numbers taken from the shared counter at a time.
     */
    public const BLOCK_SIZE = 50;

    /**
     * Free numbers a terminal keeps for working without internet; below this it takes another block.
     */
    public const OFFLINE_RESERVE = 25;

    /**
     * Take the next number of the terminal's blocks (a new block when they run out). Runs inside the
     * checkout transaction, so a failed sale gives the number back.
     */
    public function take(PosTerminal $terminal, string $type): string
    {
        $prefix = $this->prefix($type);

        return DB::transaction(function () use ($terminal, $prefix) {
            $block = $terminal->voucherBlocks()->where('prefix', $prefix)->available()->lockForUpdate()->first()
                ?? $this->reserveBlock($terminal, $prefix);

            $number = $block->next_number;
            $block->update(['next_number' => $number + 1]);

            return $this->format($prefix, $number);
        });
    }

    /**
     * Number the next sale of the type will get on this terminal, without taking it.
     */
    public function peek(PosTerminal $terminal, string $type): string
    {
        $prefix = $this->prefix($type);
        $block = $terminal->voucherBlocks()->where('prefix', $prefix)->available()->first();

        if ($block) {
            return $this->format($prefix, $block->next_number);
        }

        return $this->format($prefix, (int) DB::table('voucher_sequences')->where('prefix', $prefix)->value('last_number') + 1);
    }

    /**
     * Make sure the terminal keeps OFFLINE_RESERVE free numbers and list them as ranges for the browser.
     *
     * @return list<array{from: int, to: int}>
     */
    public function freeRanges(PosTerminal $terminal, string $type): array
    {
        $prefix = $this->prefix($type);

        DB::transaction(function () use ($terminal, $prefix) {
            $blocks = $terminal->voucherBlocks()->where('prefix', $prefix)->available()->lockForUpdate()->get();

            if ($blocks->sum(fn (VoucherBlock $block) => $block->remaining()) < self::OFFLINE_RESERVE) {
                $this->reserveBlock($terminal, $prefix);
            }
        });

        return $terminal->voucherBlocks()->where('prefix', $prefix)->available()->get()
            ->map(fn (VoucherBlock $block) => ['from' => $block->next_number, 'to' => $block->last_number])
            ->values()
            ->all();
    }

    /**
     * A sale made without internet comes back with the number the browser printed: accept it when it
     * belongs to one of the terminal's blocks and nobody used it, and move the block past it.
     */
    public function claim(PosTerminal $terminal, string $voucherNumber): bool
    {
        if (! preg_match('/^([A-Z]+)-(\d+)$/', $voucherNumber, $matches)) {
            return false;
        }

        [, $prefix, $digits] = $matches;
        $number = (int) $digits;

        return DB::transaction(function () use ($terminal, $prefix, $number, $voucherNumber) {
            $block = $terminal->voucherBlocks()
                ->where('prefix', $prefix)
                ->where('first_number', '<=', $number)
                ->where('last_number', '>=', $number)
                ->lockForUpdate()
                ->first();

            if (! $block || Sale::query()->where('voucher_number', $voucherNumber)->exists()) {
                return false;
            }

            if ($block->next_number <= $number) {
                $block->update(['next_number' => $number + 1]);
            }

            return true;
        });
    }

    public function prefix(string $type): string
    {
        return $type === 'remito' ? 'R' : 'C';
    }

    public function format(string $prefix, int $number): string
    {
        return sprintf('%s-%08d', $prefix, $number);
    }

    /**
     * Take BLOCK_SIZE numbers from the shared counter for the terminal.
     */
    protected function reserveBlock(PosTerminal $terminal, string $prefix): VoucherBlock
    {
        DB::table('voucher_sequences')->insertOrIgnore(['prefix' => $prefix, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $last = (int) DB::table('voucher_sequences')->where('prefix', $prefix)->lockForUpdate()->value('last_number');

        DB::table('voucher_sequences')->where('prefix', $prefix)->update(['last_number' => $last + self::BLOCK_SIZE, 'updated_at' => now()]);

        return $terminal->voucherBlocks()->create([
            'prefix' => $prefix,
            'first_number' => $last + 1,
            'last_number' => $last + self::BLOCK_SIZE,
            'next_number' => $last + 1,
        ]);
    }
}
