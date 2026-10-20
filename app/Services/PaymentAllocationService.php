<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use Illuminate\Support\Collection;

/**
 * Which remitos each payment settled. Allocations are not stored: they are rebuilt by replaying the
 * producer's movements in order with the same rules PaymentService and CheckoutService apply
 * (payments to the oldest remito first, saldo a favor to the next remito), all in cents.
 */
class PaymentAllocationService
{
    /**
     * @return array{allocations: Collection<int, array{sale: Sale, applied: float, pending: float}>, unapplied: float, balance_before: float, balance_after: float}
     */
    public function forPayment(Payment $payment): array
    {
        return $this->replay($payment->producer)['payments'][$payment->id]
            ?? ['allocations' => collect(), 'unapplied' => (float) $payment->amount, 'balance_before' => 0.0, 'balance_after' => 0.0];
    }

    /**
     * Receipts that paid each remito of the producer, keyed by sale id.
     *
     * @return array<int, Collection<int, array{payment: Payment, applied: float}>>
     */
    public function receiptsBySale(Producer $producer): array
    {
        return $this->replay($producer)['sales'];
    }

    /**
     * @return array{payments: array<int, array<string, mixed>>, sales: array<int, Collection<int, array{payment: Payment, applied: float}>>}
     */
    protected function replay(Producer $producer): array
    {
        $events = $producer->sales()->where('type', 'remito')->get()
            ->map(fn (Sale $sale) => ['order' => 0, 'id' => $sale->id, 'time' => $sale->created_at, 'cents' => $this->cents($sale->total), 'model' => $sale])
            ->concat($producer->payments()->get()->map(fn (Payment $payment) => ['order' => 1, 'id' => $payment->id, 'time' => $payment->created_at, 'cents' => $this->cents($payment->amount), 'model' => $payment]))
            ->concat($producer->creditNotes()->get()->map(fn (CreditNote $note) => ['order' => 2, 'id' => $note->id, 'time' => $note->created_at, 'cents' => $this->cents($note->amount), 'model' => $note]))
            ->sortBy(fn (array $event) => [$event['time']->getTimestamp(), $event['order'], $event['id']]);

        $open = [];
        $paid = [];
        $credit = 0;
        $balance = 0;
        $payments = [];
        $sales = [];

        foreach ($events as $event) {
            if ($event['order'] === 0) {
                $sale = $event['model'];
                $paid[$sale->id] = min($credit, $event['cents']);
                $credit -= $paid[$sale->id];
                $balance += $event['cents'];
                $sales[$sale->id] = collect();

                if ($paid[$sale->id] < $event['cents']) {
                    $open[] = ['sale' => $sale, 'cents' => $event['cents']];
                }

                continue;
            }

            $balanceBefore = $balance;
            $left = $event['cents'];
            $allocations = [];

            foreach ($open as $index => $remito) {
                if ($left <= 0) {
                    break;
                }

                $saleId = $remito['sale']->id;
                $applied = min($remito['cents'] - $paid[$saleId], $left);
                $paid[$saleId] += $applied;
                $left -= $applied;
                $allocations[] = ['sale' => $remito['sale'], 'applied' => $this->pesos($applied), 'pending' => $this->pesos($remito['cents'] - $paid[$saleId])];

                if ($event['order'] === 1) {
                    $sales[$saleId]->push(['payment' => $event['model'], 'applied' => $this->pesos($applied)]);
                }

                if ($paid[$saleId] >= $remito['cents']) {
                    unset($open[$index]);
                }
            }

            $open = array_values($open);
            $credit += $left;
            $balance -= $event['cents'];

            if ($event['order'] === 1) {
                $payments[$event['id']] = [
                    'allocations' => collect($allocations),
                    'unapplied' => $this->pesos($left),
                    'balance_before' => $this->pesos($balanceBefore),
                    'balance_after' => $this->pesos($balance),
                ];
            }
        }

        return ['payments' => $payments, 'sales' => $sales];
    }

    protected function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    protected function pesos(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
