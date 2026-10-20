<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\AmountInWords;
use App\Support\Pricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Data for the printable documents (remito and account statement PDFs). In the remito, sale prices
 * include VAT, so each line is shown net of VAT and the totals split exempt / net / total with VAT.
 */
class AccountDocumentService
{
    public function __construct(
        protected AccountStatementService $statements,
        protected PaymentAllocationService $allocations,
    ) {}

    /**
     * @return array{sale: Sale, lines: Collection<int, array<string, mixed>>, totals: array{exempt: float, net: float, total: float}}
     */
    public function remito(Sale $sale): array
    {
        $sale->loadMissing(['saleItems.product', 'producer']);

        $lines = $this->lines(collect([$sale]));

        return [
            'sale' => $sale,
            'lines' => $lines,
            'totals' => $this->totals($lines),
        ];
    }

    /**
     * Account statement by voucher, like a bank statement: each remito with its total and current
     * payment status, each receipt / credit note in the Haber, and the running balance. The items
     * stay in each remito's own PDF.
     *
     * @return array{producer: Producer, from: ?CarbonImmutable, to: ?CarbonImmutable, statement: array<string, mixed>, rows: Collection<int, array<string, mixed>>, openRemitos: Collection<int, Sale>}
     */
    public function statement(Producer $producer, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $statement = $this->statements->statement($producer, $from, $to);

        $remitos = $producer->sales()
            ->whereIn('id', $statement['rows']->where('kind', 'remito')->pluck('id'))
            ->get()
            ->keyBy('id');

        $rows = $statement['rows']->map(fn (array $row) => [
            ...$row,
            'status' => $row['kind'] === 'remito' ? $this->remitoStatus($remitos[$row['id']]) : null,
        ]);

        return [
            'producer' => $producer,
            'from' => $from,
            'to' => $to,
            'statement' => $statement,
            'rows' => $rows,
            'openRemitos' => $producer->sales()->unpaidRemitos()->get(),
        ];
    }

    /**
     * Payment receipt: amount (also in words), method, the remitos it settled and the balances.
     *
     * @return array{payment: Payment, producer: Producer, amountInWords: string, allocations: Collection<int, array<string, mixed>>, unapplied: float, balance_before: float, balance_after: float}
     */
    public function receipt(Payment $payment): array
    {
        $payment->loadMissing('producer');

        return [
            'payment' => $payment,
            'producer' => $payment->producer,
            'amountInWords' => AmountInWords::money((float) $payment->amount),
            ...$this->allocations->forPayment($payment),
        ];
    }

    /**
     * Every product of the producer's remitos in the range, in the paper remito format.
     *
     * @return array{producer: Producer, from: ?CarbonImmutable, to: ?CarbonImmutable, remitos: Collection<int, Sale>, lines: Collection<int, array<string, mixed>>, totals: array{exempt: float, net: float, total: float}}
     */
    public function remitosDetail(Producer $producer, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $remitos = $producer->sales()
            ->with('saleItems.product')
            ->where('type', 'remito')
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $lines = $this->lines($remitos);

        return [
            'producer' => $producer,
            'from' => $from,
            'to' => $to,
            'remitos' => $remitos,
            'lines' => $lines,
            'totals' => $this->totals($lines),
        ];
    }

    /**
     * "Pagado total", "Pago parcial: saldo $ 201,00" or "Pendiente", as of today.
     */
    protected function remitoStatus(Sale $sale): string
    {
        return match ($sale->status) {
            'pagada' => 'Pagado total',
            'parcial' => 'Pago parcial: saldo '.Pricing::moneyWithCents((float) $sale->total - (float) $sale->paid),
            default => 'Pendiente',
        };
    }

    /**
     * One printable line per sale item: code, date, detail, quantity, net unit price and net subtotal.
     *
     * @param  Collection<int, Sale>  $sales
     * @return Collection<int, array{code: string, date: string, voucher: string, detail: string, quantity: int, vat: float, price: float, subtotal: float, gross: float}>
     */
    protected function lines(Collection $sales): Collection
    {
        return $sales->flatMap(fn (Sale $sale) => $sale->saleItems->map(function (SaleItem $item) use ($sale) {
            $vat = (float) ($item->product?->vat_percent ?? 0);
            // The line subtotal is quantity x the rounded net price, so every printed line adds up
            // exactly; the total with VAT is always the real amount of the remito.
            $price = round((float) $item->unit_price / (1 + $vat / 100), 2);

            return [
                'code' => (string) ($item->product?->sku ?? $item->product_id),
                'date' => $sale->created_at->format('d/m/Y'),
                'voucher' => $sale->voucher_number,
                'detail' => $item->product?->name ?? 'Producto eliminado',
                'quantity' => (int) $item->quantity,
                'vat' => $vat,
                'price' => $price,
                'subtotal' => round($price * $item->quantity, 2),
                'gross' => (float) $item->subtotal,
            ];
        }))->values();
    }

    /**
     * @param  Collection<int, array{vat: float, subtotal: float, gross: float}>  $lines
     * @return array{exempt: float, net: float, total: float}
     */
    protected function totals(Collection $lines): array
    {
        return [
            'exempt' => round($lines->where('vat', 0.0)->sum('gross'), 2),
            'net' => round($lines->where('vat', '>', 0)->sum('subtotal'), 2),
            'total' => round($lines->sum('gross'), 2),
        ];
    }
}
