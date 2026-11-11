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
     * Position of each movement ("remito-1", "pago-3", "nota_credito-2") when the account is read by
     * what settled what: every remito right above the payment or credit note that put the most money
     * into it (or settled it), remitos paid with saldo a favor where they were issued, and unpaid
     * remitos last, by date.
     *
     * @return array<string, int>
     */
    public function movementOrder(Producer $producer): array
    {
        return array_flip($this->replay($producer)['order']);
    }

    /**
     * @return array{payments: array<int, array<string, mixed>>, sales: array<int, Collection<int, array{payment: Payment, applied: float}>>, order: list<string>}
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
        $order = [];
        $waiting = [];
        $kinds = ['remito', 'pago', 'nota_credito'];

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

                if ($paid[$sale->id] > 0) {
                    $order[] = "remito-{$sale->id}";
                } else {
                    $waiting[$sale->id] = "remito-{$sale->id}";
                }

                continue;
            }

            $balanceBefore = $balance;
            $left = $event['cents'];
            $allocations = [];
            $touched = [];

            foreach ($open as $index => $remito) {
                if ($left <= 0) {
                    break;
                }

                $saleId = $remito['sale']->id;
                $applied = min($remito['cents'] - $paid[$saleId], $left);
                $paid[$saleId] += $applied;
                $left -= $applied;
                $allocations[] = ['sale' => $remito['sale'], 'applied' => $this->pesos($applied), 'pending' => $this->pesos($remito['cents'] - $paid[$saleId])];
                $touched[] = ['sale_id' => $saleId, 'cents' => $applied, 'settled' => $paid[$saleId] >= $remito['cents']];

                if ($event['order'] === 1) {
                    $sales[$saleId]->push(['payment' => $event['model'], 'applied' => $this->pesos($applied)]);
                }

                if ($paid[$saleId] >= $remito['cents']) {
                    unset($open[$index]);
                }
            }

            // A remito goes above the payment that put the most money into it, or the one that settled it:
            // the cents left over from paying one remito don't drag the next one up.
            $main = collect($touched)->sortByDesc('cents')->first()['sale_id'] ?? null;

            foreach ($touched as $touch) {
                if (isset($waiting[$touch['sale_id']]) && ($touch['sale_id'] === $main || $touch['settled'])) {
                    $order[] = $waiting[$touch['sale_id']];
                    unset($waiting[$touch['sale_id']]);
                }
            }

            $order[] = $kinds[$event['order']].'-'.$event['id'];
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

        return ['payments' => $payments, 'sales' => $sales, 'order' => [...$order, ...array_values($waiting)]];
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
