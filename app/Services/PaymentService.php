<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Producer;
use App\Support\Pricing;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Register a full or partial payment from a producer: lower the balance and settle the
     * oldest unpaid remitos first. Amounts are handled in cents to avoid rounding drift.
     *
     * @throws PaymentException
     */
    public function register(int $producerId, float $amount, string $method, ?string $notes = null): Payment
    {
        $cents = (int) round($amount * 100);

        if ($cents <= 0) {
            throw new PaymentException('El monto tiene que ser mayor a cero.');
        }

        return DB::transaction(function () use ($producerId, $cents, $method, $notes) {
            $producer = Producer::query()->lockForUpdate()->findOrFail($producerId);

            if ($cents > (int) round((float) $producer->balance * 100)) {
                throw new PaymentException('El monto supera el saldo deudor ('.Pricing::moneyWithCents($producer->balance).').');
            }

            $payment = $producer->payments()->create([
                'amount' => $cents / 100,
                'payment_method' => $method,
                'notes' => trim((string) $notes) ?: null,
            ]);

            $producer->decrement('balance', $cents / 100);

            $this->settleOldestRemitos($producer, $cents);

            return $payment;
        });
    }

    /**
     * Apply the paid cents to the unpaid remitos, oldest first, marking them "parcial" or "pagada".
     */
    protected function settleOldestRemitos(Producer $producer, int $cents): void
    {
        $remitos = $producer->sales()->unpaidRemitos()->lockForUpdate()->get();

        foreach ($remitos as $sale) {
            if ($cents <= 0) {
                break;
            }

            $totalCents = (int) round((float) $sale->total * 100);
            $paidCents = (int) round((float) $sale->paid * 100);
            $applied = min($totalCents - $paidCents, $cents);
            $paidCents += $applied;
            $cents -= $applied;

            $sale->update([
                'paid' => $paidCents / 100,
                'status' => $paidCents >= $totalCents ? 'pagada' : 'parcial',
            ]);
        }
    }
}
