<?php

namespace App\Services;

use App\Livewire\Pos\PosDesk;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\PosTerminal;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Support\Pricing;
use App\Support\Quantity;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The POS keeps selling without internet: while it is online the browser keeps a copy of the catalog,
 * the producers and the open caja (snapshot), and the sales it makes offline come back here (import)
 * once the connection returns, with the time they really happened.
 */
class OfflineSaleService
{
    /**
     * A PC clock this far ahead of the server is taken as wrong.
     */
    public const CLOCK_TOLERANCE_MINUTES = 5;

    public function __construct(
        protected CheckoutService $checkout,
        protected StockService $stock,
        protected VoucherBlockService $vouchers,
        protected CashSessionService $cashSessions,
    ) {}

    /**
     * What the browser needs to sell without internet: the caja open on the terminal (and whether this
     * browser unlocked it), the terminal's reserved voucher numbers, the catalog and the producers.
     *
     * @return array<string, mixed>
     */
    public function snapshot(PosTerminal $terminal, ?CashSession $cashSession, bool $unlocked): array
    {
        $cashSession?->loadMissing('user');

        return [
            'generated_at' => now()->toIso8601String(),
            'terminal' => ['id' => $terminal->id, 'name' => $terminal->name],
            'cash_session' => $cashSession ? [
                'id' => $cashSession->id,
                'user_id' => $cashSession->user_id,
                'user_name' => $cashSession->user->name,
                'user_role' => $cashSession->user->role,
                'opened_at' => $cashSession->created_at->toIso8601String(),
            ] : null,
            'unlocked' => $cashSession !== null && $unlocked,
            'vouchers' => $cashSession ? [
                'C' => $this->vouchers->freeRanges($terminal, 'contado'),
                'R' => $this->vouchers->freeRanges($terminal, 'remito'),
            ] : null,
            'methods' => PosDesk::PAYMENT_METHODS,
            'categories' => Category::query()
                ->whereHas('products', fn ($query) => $query->where('is_active', true))
                ->orderBy('id')
                ->get(['id', 'name'])
                ->map(fn (Category $category) => ['id' => $category->id, 'name' => $category->name])
                ->all(),
            'products' => Product::query()
                ->with('category')
                ->where('is_active', true)
                ->withSellableStock()
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'price' => (float) $product->price,
                    'stock' => $product->sellableStock(),
                    'sold_by_weight' => (bool) $product->sold_by_weight,
                    'category_id' => $product->category_id,
                    'category' => $product->category?->name,
                ])
                ->all(),
            'producers' => Producer::query()
                ->active()
                ->where('name', '!=', Producer::WALK_IN)
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'cuit', 'balance', 'credit_limit'])
                ->map(fn (Producer $producer) => [
                    'id' => $producer->id,
                    'name' => $producer->name,
                    'business_name' => $producer->business_name,
                    'cuit' => $producer->cuit,
                    'balance' => (float) $producer->balance,
                    'credit_limit' => $producer->credit_limit === null ? null : (float) $producer->credit_limit,
                ])
                ->all(),
        ];
    }

    /**
     * Register a sale the POS made without internet. It already happened at the mostrador, so it is
     * never refused for business reasons: whatever doesn't add up (stock that wasn't there, a voucher
     * number already used, a caja closed meanwhile) is recorded and flagged for administración.
     * Uploading the same sale twice returns the first one.
     *
     * @param  array{uuid: string, sold_at: string, cash_session_id?: int|null, type: string, payment_method: string, producer_id?: int|null, voucher_number?: string|null, provisional_number?: string|null, items: list<array{product_id: int, quantity: int, unit_price: float|int|string}>}  $data
     * @return array{status: string, voucher_number: string, needs_review: bool, notes: list<string>}
     */
    public function import(PosTerminal $terminal, array $data): array
    {
        if ($existing = Sale::query()->where('offline_uuid', $data['uuid'])->first()) {
            return $this->result('duplicate', $existing);
        }

        try {
            $sale = DB::transaction(fn () => $this->create($terminal, $data));
        } catch (UniqueConstraintViolationException $e) {
            // The same sale was uploaded twice at the same time: the other request registered it.
            $sale = Sale::query()->where('offline_uuid', $data['uuid'])->first() ?? throw $e;

            return $this->result('duplicate', $sale);
        }

        return $this->result('created', $sale);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function create(PosTerminal $terminal, array $data): Sale
    {
        $notes = [];
        $review = false;

        $cashSession = isset($data['cash_session_id'])
            ? CashSession::query()->where('pos_terminal_id', $terminal->id)->lockForUpdate()->find($data['cash_session_id'])
            : null;

        if (! $cashSession) {
            $notes[] = 'No se encontró la caja de esta terminal en la que se hizo la venta.';
            $review = true;
        }

        $soldAt = $this->soldAt($data['sold_at'], $notes);

        $type = $data['type'] === 'remito' ? 'remito' : 'contado';
        $producer = $type === 'remito'
            ? Producer::query()->lockForUpdate()->findOrFail($data['producer_id'])
            : null;

        if ($producer && ! $producer->is_active) {
            $notes[] = "La cuenta de {$producer->name} estaba dada de baja.";
            $review = true;
        }

        $voucher = $this->voucherNumber($terminal, $type, $data, $notes, $review);

        $lines = collect($data['items'])
            ->groupBy(fn (array $item) => (int) $item['product_id'])
            ->sortKeys()
            ->map(fn ($group, int $productId) => [
                'product' => Product::query()->lockForUpdate()->findOrFail($productId),
                'quantity' => (int) $group->sum('quantity'),
                'unit_price' => round((float) $group->first()['unit_price'], 2),
            ]);

        $total = round($lines->sum(fn (array $line) => Quantity::lineTotal($line['unit_price'], $line['quantity'], $line['product']->sold_by_weight)), 2);
        $paid = $type === 'contado' ? $total : $this->checkout->creditToApply($producer, $total);

        if ($producer && $producer->exceedsCreditLimit($total)) {
            $notes[] = 'El remito superó el límite de crédito de '.$producer->name.' ('.Pricing::moneyWithCents($producer->credit_limit).').';
        }

        $sale = new Sale([
            'voucher_number' => $voucher,
            'type' => $type,
            'payment_method' => $type === 'remito' ? 'cuenta_corriente' : $data['payment_method'],
            'producer_id' => $producer?->id,
            'total' => $total,
            'paid' => $paid,
            'status' => match (true) {
                $paid >= $total => 'pagada',
                $paid > 0 => 'parcial',
                default => 'pendiente',
            },
            'user_id' => $cashSession?->user_id,
            'cash_session_id' => $cashSession?->id,
            'offline_uuid' => $data['uuid'],
            'synced_at' => now(),
        ]);
        $sale->created_at = $soldAt;
        $sale->updated_at = $soldAt;
        $sale->save();

        $sale->saleItems()->createMany($lines->map(fn (array $line) => [
            'product_id' => $line['product']->id,
            'quantity' => $line['quantity'],
            'sold_by_weight' => $line['product']->sold_by_weight,
            'unit_price' => $line['unit_price'],
            'subtotal' => Quantity::lineTotal($line['unit_price'], $line['quantity'], $line['product']->sold_by_weight),
        ])->values()->all());

        foreach ($lines as $line) {
            $missing = $this->stock->consumeAvailableForSale($line['product'], $line['quantity'], $sale, $cashSession?->user_id);

            if ($missing > 0) {
                $notes[] = "Faltó stock de {$line['product']->name}: se vendieron {$line['product']->formatQuantity($line['quantity'])} y el sistema tenía ".$line['product']->formatQuantity($line['quantity'] - $missing).'.';
                $review = true;
            }
        }

        $producer?->increment('balance', $total);

        if ($cashSession && ! $cashSession->isOpen()) {
            $cashSession->update(['expected_cash' => $this->cashSessions->expectedCash($cashSession)]);
            $notes[] = 'La caja ya estaba cerrada cuando se subió la venta: se recalculó el efectivo esperado.';
            $review = $review || ! $cashSession->closed_automatically;
        }

        $sale->update(['needs_review' => $review, 'sync_notes' => $notes === [] ? null : implode("\n", $notes)]);

        return $sale;
    }

    /**
     * The number printed without internet when it is valid; otherwise a new one from the terminal's blocks.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $notes
     */
    protected function voucherNumber(PosTerminal $terminal, string $type, array $data, array &$notes, bool &$review): string
    {
        $printed = $data['voucher_number'] ?? null;
        $prefix = $this->vouchers->prefix($type);

        if ($printed && str_starts_with($printed, $prefix.'-') && $this->vouchers->claim($terminal, $printed)) {
            return $printed;
        }

        $voucher = $this->vouchers->take($terminal, $type);
        $review = true;
        $notes[] = $printed
            ? "El comprobante salió impreso como {$printed}, que ya estaba usado o no era de esta terminal: se le asignó {$voucher}."
            : 'El comprobante salió impreso como provisorio'.(($data['provisional_number'] ?? null) ? " {$data['provisional_number']}" : '').": se le asignó {$voucher}.";

        return $voucher;
    }

    /**
     * When the sale happened by the PC clock, unless that clock is ahead of the server.
     *
     * @param  list<string>  $notes
     */
    protected function soldAt(string $soldAt, array &$notes): CarbonImmutable
    {
        $time = CarbonImmutable::parse($soldAt)->setTimezone(config('app.timezone'));

        if ($time->gt(now()->addMinutes(self::CLOCK_TOLERANCE_MINUTES))) {
            $notes[] = 'La hora de la PC estaba adelantada ('.$time->format('d/m/Y H:i').'): se registró con la hora de subida.';

            return CarbonImmutable::now();
        }

        return $time;
    }

    /**
     * @return array{status: string, voucher_number: string, needs_review: bool, notes: list<string>}
     */
    protected function result(string $status, Sale $sale): array
    {
        return [
            'status' => $status,
            'voucher_number' => $sale->voucher_number,
            'needs_review' => $sale->needs_review,
            'notes' => $sale->sync_notes ? explode("\n", $sale->sync_notes) : [],
        ];
    }
}
