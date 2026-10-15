<?php

namespace App\Services;

use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use Exception;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(protected StockService $stock) {}

    /**
     * Process a checkout: lock each product, validate stock, create the sale with its items,
     * discount stock (FEFO by lot) recording a "venta" movement and, for remitos, charge the
     * total to the producer's balance.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $cartItems
     * @param  array{type: string, producer_id?: int|null, payment_method?: string|null, voucher_number?: string|null, user_id?: int|null}  $checkoutData
     *
     * @throws Exception
     */
    public function execute(array $cartItems, array $checkoutData): Sale
    {
        $type = $checkoutData['type'] ?? 'contado';
        $producerId = $checkoutData['producer_id'] ?? null;

        if (! in_array($type, ['contado', 'remito'], true)) {
            throw new Exception("Tipo de venta inválido: {$type}.");
        }

        if ($type === 'remito' && ! $producerId) {
            throw new Exception('Un remito requiere un productor.');
        }

        $quantities = $this->groupQuantities($cartItems);

        return DB::transaction(function () use ($quantities, $type, $producerId, $checkoutData) {
            $producer = $type === 'remito'
                ? Producer::query()->lockForUpdate()->findOrFail($producerId)
                : null;

            $products = [];
            $items = [];
            $total = 0.0;

            foreach ($quantities as $productId => $qty) {
                $product = Product::query()->lockForUpdate()->find($productId);

                if (! $product || ! $product->is_active) {
                    throw new Exception("El producto #{$productId} no existe o está inactivo.");
                }

                if ($product->stock < $qty) {
                    throw new Exception("Stock insuficiente para {$product->name}: disponible {$product->stock}, solicitado {$qty}.");
                }

                $products[$productId] = $product;

                $subtotal = round((float) $product->price * $qty, 2);
                $total += $subtotal;

                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            $total = round($total, 2);

            $sale = Sale::create([
                'voucher_number' => $checkoutData['voucher_number'] ?? $this->nextVoucherNumber($type),
                'type' => $type,
                'payment_method' => $type === 'remito' ? 'cuenta_corriente' : ($checkoutData['payment_method'] ?? null),
                'producer_id' => $producerId,
                'total' => $total,
                'paid' => $type === 'contado' ? $total : 0,
                'status' => $type === 'contado' ? 'pagada' : 'pendiente',
            ]);

            $sale->saleItems()->createMany($items);

            foreach ($products as $productId => $product) {
                $this->stock->consumeForSale($product, $quantities[$productId], $sale, $checkoutData['user_id'] ?? null);
            }

            $producer?->increment('balance', $total);

            return $sale->load('saleItems');
        });
    }

    /**
     * Merge repeated products and validate quantities, sorted by product id so
     * concurrent checkouts acquire locks in the same order.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $cartItems
     * @return array<int, int>
     *
     * @throws Exception
     */
    protected function groupQuantities(array $cartItems): array
    {
        $quantities = [];

        foreach ($cartItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                throw new Exception('Renglón de carrito inválido.');
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
        }

        if ($quantities === []) {
            throw new Exception('El carrito está vacío.');
        }

        ksort($quantities);

        return $quantities;
    }

    /**
     * Build the next voucher number for the given sale type (C-00000001 / R-00000001).
     */
    public function nextVoucherNumber(string $type): string
    {
        $prefix = $type === 'remito' ? 'R' : 'C';

        return sprintf('%s-%08d', $prefix, (Sale::query()->max('id') ?? 0) + 1);
    }
}
