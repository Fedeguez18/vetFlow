<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\Producer;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Register a full or partial payment from a producer: lower the balance and settle the
     * oldest unpaid remitos first. Whatever exceeds the debt stays as saldo a favor (negative
     * balance) and is applied to the next remito. Amounts are handled in cents to avoid rounding drift.
     *
     * @throws PaymentException
     */
    public function register(int $producerId, float $amount, string $method, ?string $notes = null): Payment
    {
        $cents = $this->cents($amount);

        return DB::transaction(function () use ($producerId, $cents, $method, $notes) {
            $producer = Producer::query()->lockForUpdate()->findOrFail($producerId);

            $payment = $producer->payments()->create([
                'amount' => $cents / 100,
                'payment_method' => $method,
                'notes' => trim((string) $notes) ?: null,
            ]);

            $this->credit($producer, $cents);

            return $payment;
        });
    }

    /**
     * Issue a credit note: it lowers the debt like a payment, without money coming in.
     *
     * @throws PaymentException
     */
    public function registerCreditNote(int $producerId, float $amount, string $reason): CreditNote
    {
        $cents = $this->cents($amount);
        $reason = trim($reason);

        if ($reason === '') {
            throw new PaymentException('Indicá el motivo de la nota de crédito.');
        }

        return DB::transaction(function () use ($producerId, $cents, $reason) {
            $producer = Producer::query()->lockForUpdate()->findOrFail($producerId);

            $creditNote = $producer->creditNotes()->create([
                'amount' => $cents / 100,
                'reason' => $reason,
            ]);

            $this->credit($producer, $cents);

            return $creditNote;
        });
    }

    /**
     * @throws PaymentException
     */
    protected function cents(float $amount): int
    {
        $cents = (int) round($amount * 100);

        if ($cents <= 0) {
            throw new PaymentException('El monto tiene que ser mayor a cero.');
        }

        return $cents;
    }

    /**
     * Lower the (locked) producer's balance and settle their remitos with the credited cents.
     */
    protected function credit(Producer $producer, int $cents): void
    {
        $producer->decrement('balance', $cents / 100);

        $this->settleOldestRemitos($producer, $cents);
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
