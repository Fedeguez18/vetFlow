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
     * What is left of an open bag below this share of the new bag is taken as waste when the next one
     * is opened: the scale is not exact and the last grams spill, so the old bag is really finished.
     */
    public const BAG_WASTE_TOLERANCE_PERCENT = 5;

    /**
     * Open a closed bag: one bag leaves its stock (from the lot that expires first when it tracks expiry)
     * and its kilos go into the loose product sold by weight, both as traceable movements. A small rest of
     * the previous open bag (under BAG_WASTE_TOLERANCE_PERCENT) is written off as waste first.
     *
     * @return array{bag: StockMovement, bulk: StockMovement, waste: StockMovement|null}
     *
     * @throws StockException
     */
    public function openBag(Product $bag, ?int $userId = null): array
    {
        return DB::transaction(function () use ($bag, $userId) {
            $ids = [$bag->id, (int) $bag->bulk_product_id];
            sort($ids);
            $locked = Product::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $bag = $locked->get($bag->id);
            $bulk = $locked->get((int) $bag?->bulk_product_id);

            if (! $bag || ! $bag->canBeOpened() || ! $bulk || ! $bulk->sold_by_weight) {
                throw new StockException('Este artículo no tiene configurado un suelto por kilo al que abrir la bolsa.');
            }

            $lot = null;

            if ($bag->tracks_expiry) {
                $lot = $bag->lots()->sellable()->orderBy('expiration_date')->orderBy('id')->lockForUpdate()->first();

                if (! $lot) {
                    throw new StockException("No hay bolsas vigentes de {$bag->name} para abrir.");
                }

                $lot->decrement('quantity');
                $bag->syncStockFromLots();
            } else {
                if ($bag->stock < 1) {
                    throw new StockException("No hay bolsas de {$bag->name} en stock.");
                }

                $bag->decrement('stock');
            }

            $rest = (int) $bulk->stock;
            $waste = $rest > 0 && $rest * 100 < $bag->bag_weight_grams * self::BAG_WASTE_TOLERANCE_PERCENT
                ? $this->writeOffOpenBag($bulk, "Merma de bolsa: resto de {$bulk->formatQuantity($rest)} al abrir otra", $userId)
                : null;

            $bulk->increment('stock', $bag->bag_weight_grams);

            return [
                'waste' => $waste,
                'bag' => StockMovement::create([
                    'product_id' => $bag->id,
                    'lot_id' => $lot?->id,
                    'type' => 'egreso',
                    'quantity' => -1,
                    'reason' => "Bolsa abierta: pasó a {$bulk->name}",
                    'user_id' => $userId,
                ]),
                'bulk' => StockMovement::create([
                    'product_id' => $bulk->id,
                    'type' => 'ingreso',
                    'quantity' => $bag->bag_weight_grams,
                    'reason' => "Bolsa abierta de {$bag->name}",
                    'user_id' => $userId,
                ]),
            ];
        });
    }

    /**
     * The open bag of a loose product ran out although the system still shows some grams (or it was
     * thrown away): the rest goes out as waste and the next sale opens a new bag.
     *
     * @throws StockException
     */
    public function finishOpenBag(Product $loose, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($loose, $userId) {
            $loose = Product::query()->lockForUpdate()->findOrFail($loose->id);

            if (! $loose->sold_by_weight || $loose->stock <= 0) {
                throw new StockException("{$loose->name} no tiene una bolsa abierta.");
            }

            return $this->writeOffOpenBag($loose, "Merma de bolsa: se terminó con {$loose->formatQuantity($loose->stock)} sin vender", $userId);
        });
    }

    /**
     * Take the whole rest of the open bag out of a (locked) loose product as an "egreso".
     */
    protected function writeOffOpenBag(Product $loose, string $reason, ?int $userId): StockMovement
    {
        $rest = (int) $loose->stock;
        $loose->decrement('stock', $rest);

        return StockMovement::create([
            'product_id' => $loose->id,
            'type' => 'egreso',
            'quantity' => -$rest,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
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
                throw new StockException("No se pueden restar {$product->formatQuantity($quantity)}: hay {$product->formatQuantity($current)}.");
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
        $this->openBagsFor($product, $quantity, $userId);

        if ($product->sold_by_weight && $product->stock < $quantity) {
            throw new StockException("Las bolsas de {$product->name} no alcanzan para {$product->formatQuantity($quantity)}.");
        }

        if ($product->tracks_expiry) {
            if ($this->takeFromLots($product, $quantity, $sale, $userId) > 0) {
                throw new StockException("Los lotes vigentes de {$product->name} no alcanzan para {$product->formatQuantity($quantity)} (los lotes vencidos no se venden).");
            }
        } else {
            $this->recordSale($product, $sale, -$quantity, null, $userId);
        }

        $product->decrement('stock', $quantity);
    }

    /**
     * Discount a sale made without internet. It already happened at the mostrador, so instead of
     * failing it takes what there is (sellable lots first to expire) and returns the missing units.
     */
    public function consumeAvailableForSale(Product $product, int $quantity, Sale $sale, ?int $userId = null): int
    {
        $this->openBagsFor($product, $quantity, $userId);

        if ($product->tracks_expiry) {
            $taken = $quantity - $this->takeFromLots($product, $quantity, $sale, $userId);
        } else {
            $taken = min($quantity, max(0, (int) $product->stock));

            if ($taken > 0) {
                $this->recordSale($product, $sale, -$taken, null, $userId);
            }
        }

        if ($taken > 0) {
            $product->decrement('stock', $taken);
        }

        return $quantity - $taken;
    }

    /**
     * Loose food is sold from the open bag: when what is left doesn't cover the sale, closed bags are
     * opened (one at a time, each one a traceable movement) until it does or there are no more bags.
     */
    protected function openBagsFor(Product $product, int $quantity, ?int $userId): void
    {
        if (! $product->sold_by_weight) {
            return;
        }

        while ((int) $product->stock < $quantity) {
            $bag = $product->bags()
                ->where('bag_weight_grams', '>', 0)
                ->orderBy('id')
                ->get()
                ->first(fn (Product $bag) => $bag->sellableStock() > 0);

            if (! $bag) {
                return;
            }

            $this->openBag($bag, $userId);
            $product->refresh();
        }
    }

    /**
     * Take units from the sellable lots, the first to expire first.
     *
     * @return int units the lots could not cover
     */
    protected function takeFromLots(Product $product, int $quantity, Sale $sale, ?int $userId): int
    {
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

        return $remaining;
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
