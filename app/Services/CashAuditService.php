<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CashAuditService
{
    /**
     * Labels of every way money can come in, for the audit breakdown.
     */
    public const METHODS = [
        'efectivo' => 'Efectivo',
        'qr' => 'QR/Transferencia',
        'transferencia' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'cheque' => 'Cheque',
        'cuenta_corriente' => 'Cuenta Corriente',
    ];

    /**
     * Methods that land in the bank account.
     */
    public const BANK_METHODS = ['qr', 'transferencia'];

    /**
     * Every sale and account payment of the day, in chronological order.
     *
     * @return Collection<int, array{key: string, time: CarbonImmutable, kind: string, voucher: string, client: string, method: ?string, amount: float, collected: float}>
     */
    public function entries(CarbonImmutable $day): Collection
    {
        $range = [$day->startOfDay(), $day->endOfDay()];

        $sales = Sale::query()->with('producer')->whereBetween('created_at', $range)->get()
            ->map(fn (Sale $sale) => [
                'key' => "sale-{$sale->id}",
                'time' => $sale->created_at->toImmutable(),
                'kind' => $sale->type,
                'voucher' => $sale->voucher_number,
                'client' => $sale->producer?->name ?? 'Consumidor final',
                'method' => $sale->type === 'remito' ? 'cuenta_corriente' : $sale->payment_method,
                'amount' => (float) $sale->total,
                'collected' => $sale->type === 'remito' ? 0.0 : (float) $sale->total,
            ]);

        $payments = Payment::query()->with('producer')->whereBetween('created_at', $range)->get()
            ->map(fn (Payment $payment) => [
                'key' => "payment-{$payment->id}",
                'time' => $payment->created_at->toImmutable(),
                'kind' => 'cobro',
                'voucher' => sprintf('P-%08d', $payment->id),
                'client' => $payment->producer?->name ?? '—',
                'method' => $payment->payment_method,
                'amount' => (float) $payment->amount,
                'collected' => (float) $payment->amount,
            ]);

        return $sales->concat($payments)->sortBy([['time', 'asc'], ['key', 'asc']])->values();
    }

    /**
     * Totals of the day: collected money split by cash/bank/method, plus voucher counts.
     *
     * @param  Collection<int, array{kind: string, method: ?string, amount: float, collected: float}>  $entries
     * @return array{collected: float, cash: float, bank: float, vouchers: int, tickets: int, remitos: int, remitos_amount: float, payments: int, payments_amount: float, by_method: array<string, float>}
     */
    public function summary(Collection $entries): array
    {
        $collected = $entries->where('collected', '>', 0);
        $remitos = $entries->where('kind', 'remito');
        $payments = $entries->where('kind', 'cobro');

        return [
            'collected' => round($collected->sum('collected'), 2),
            'cash' => round($collected->where('method', 'efectivo')->sum('collected'), 2),
            'bank' => round($collected->whereIn('method', self::BANK_METHODS)->sum('collected'), 2),
            'vouchers' => $entries->whereIn('kind', ['contado', 'remito'])->count(),
            'tickets' => $entries->where('kind', 'contado')->count(),
            'remitos' => $remitos->count(),
            'remitos_amount' => round($remitos->sum('amount'), 2),
            'payments' => $payments->count(),
            'payments_amount' => round($payments->sum('amount'), 2),
            'by_method' => $collected->groupBy(fn ($entry) => $entry['method'] ?? 'sin_registrar')
                ->map(fn ($group) => round($group->sum('collected'), 2))
                ->sortDesc()
                ->all(),
        ];
    }
}
