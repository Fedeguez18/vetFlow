<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Lot;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Support\Pricing;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Adjustment reasons; the ones that take units out for good are recorded as "egreso".
     */
    public const ADJUSTMENT_REASONS = [
        'rotura' => 'Rotura',
        'vencido' => 'Vencido',
        'conteo' => 'Conteo físico',
        'otro' => 'Otro',
    ];

    /**
     * Add units to a product. Products that track expiry go into a lot (created, or summed when the
     * number already exists). Optionally updates the purchase cost and recalculates the final price.
     *
     * @throws StockException
     */
    public function restock(
        Product $product,
        int $quantity,
        ?string $lotNumber = null,
        ?string $expirationDate = null,
        ?int $supplierId = null,
        ?string $reason = null,
        ?int $userId = null,
        ?float $newCost = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new StockException('La cantidad a ingresar tiene que ser mayor a cero.');
        }

        $lotNumber = trim((string) $lotNumber) ?: null;

        return DB::transaction(function () use ($product, $quantity, $lotNumber, $expirationDate, $supplierId, $reason, $userId, $newCost) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lot = null;

            if ($product->tracks_expiry) {
                if (! $lotNumber || ! $expirationDate) {
                    throw new StockException('El número de lote y la fecha de vencimiento son obligatorios para este producto.');
                }

                $lot = $product->lots()->lockForUpdate()->firstOrNew(
                    ['lot_number' => $lotNumber],
                    ['expiration_date' => $expirationDate, 'quantity' => 0],
                );
                $lot->quantity += $quantity;
                $lot->save();
            } elseif ($lotNumber || $expirationDate) {
                $reason = trim(collect([
                    $lotNumber ? "Lote {$lotNumber}" : null,
                    $expirationDate ? 'vence '.date('d/m/Y', strtotime($expirationDate)) : null,
                ])->filter()->join(', ').($reason ? " — {$reason}" : ''));
            }

            $product->increment('stock', $quantity);

            if ($newCost !== null && round($newCost, 2) !== round((float) $product->cost_price, 2)) {
                $product->update([
                    'cost_price' => round($newCost, 2),
                    'price' => Pricing::finalPrice($newCost, (float) $product->markup_percent, (float) $product->vat_percent),
                ]);

                $this->recordPrice($product, $userId);
            }

            return StockMovement::create([
                'product_id' => $product->id,
                'lot_id' => $lot?->id,
                'type' => 'ingreso',
                'quantity' => $quantity,
                'reason' => $reason ?: null,
                'supplier_id' => $supplierId,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Subtract units ("subtract") or correct to a counted quantity ("set"), on a lot when the product tracks expiry.
     *
     * @throws StockException
     */
    public function adjust(
        Product $product,
        string $mode,
        int $quantity,
        string $reason,
        ?int $lotId = null,
        ?string $note = null,
        ?int $userId = null,
    ): StockMovement {
        if (! array_key_exists($reason, self::ADJUSTMENT_REASONS)) {
            throw new StockException('Elegí el motivo del ajuste.');
        }

        if (! in_array($mode, ['subtract', 'set'], true) || $quantity < 0 || ($mode === 'subtract' && $quantity === 0)) {
            throw new StockException('Cantidad de ajuste inválida.');
        }

        return DB::transaction(function () use ($product, $mode, $quantity, $reason, $lotId, $note, $userId) {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lot = null;

            if ($product->tracks_expiry) {
                $lot = $lotId ? $product->lots()->lockForUpdate()->find($lotId) : null;

                if (! $lot) {
                    throw new StockException('Elegí el lote a ajustar.');
                }
            }

            $current = $lot ? $lot->quantity : $product->stock;
            $delta = $mode === 'subtract' ? -$quantity : $quantity - $current;

            if ($current + $delta < 0) {
                throw new StockException("No se pueden restar {$quantity} unidades: hay {$current}.");
            }

            if ($delta === 0) {
                throw new StockException('La cantidad no cambia.');
            }

            $lot?->increment('quantity', $delta);
            $product->increment('stock', $delta);

            return StockMovement::create([
                'product_id' => $product->id,
                'lot_id' => $lot?->id,
                'type' => $delta < 0 && in_array($reason, ['rotura', 'vencido'], true) ? 'egreso' : 'ajuste',
                'quantity' => $delta,
                'reason' => self::ADJUSTMENT_REASONS[$reason].(trim((string) $note) !== '' ? ': '.trim($note) : ''),
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Take sold units out of stock, first from the lot that expires first (FEFO).
     * Must run inside the checkout transaction with the product already locked.
     *
     * @throws StockException
     */
    public function consumeForSale(Product $product, int $quantity, Sale $sale, ?int $userId = null): void
    {
        if ($product->tracks_expiry) {
            $remaining = $quantity;

            $lots = $product->lots()
                ->sellable()
                ->orderBy('expiration_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining === 0) {
                    break;
                }

                /** @var Lot $lot */
                $taken = min($lot->quantity, $remaining);
                $lot->decrement('quantity', $taken);
                $remaining -= $taken;

                $this->recordSale($product, $sale, -$taken, $lot->id, $userId);
            }

            if ($remaining > 0) {
                throw new StockException("Los lotes vigentes de {$product->name} no alcanzan para {$quantity} unidades (los lotes vencidos no se venden).");
            }
        } else {
            $this->recordSale($product, $sale, -$quantity, null, $userId);
        }

        $product->decrement('stock', $quantity);
    }

    public function recordPrice(Product $product, ?int $userId = null): PriceHistory
    {
        return $product->priceHistories()->create([
            'cost_price' => $product->cost_price,
            'markup_percent' => $product->markup_percent,
            'vat_percent' => $product->vat_percent,
            'price' => $product->price,
            'user_id' => $userId,
        ]);
    }

    protected function recordSale(Product $product, Sale $sale, int $quantity, ?int $lotId, ?int $userId): void
    {
        StockMovement::create([
            'product_id' => $product->id,
            'lot_id' => $lotId,
            'type' => 'venta',
            'quantity' => $quantity,
            'reason' => "Venta {$sale->voucher_number}",
            'sale_id' => $sale->id,
            'user_id' => $userId,
        ]);
    }
}
