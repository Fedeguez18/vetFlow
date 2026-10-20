<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AccountStatementService
{
    /**
     * Order of movements registered at the same second: the charge first, then what lowers it.
     */
    protected const KIND_ORDER = ['remito' => 0, 'pago' => 1, 'nota_credito' => 2];

    /**
     * Account statement of a producer: remitos go to the Debe, payments and credit notes to the
     * Haber, with the running balance. The opening balance sums every movement before $from.
     *
     * @return array{opening: float, rows: Collection<int, array{key: string, id: int, time: CarbonImmutable, kind: string, voucher: string, detail: ?string, debit: float, credit: float, balance: float}>, debit: float, credit: float, closing: float}
     */
    public function statement(Producer $producer, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $from = $from?->startOfDay();
        $to = $to?->endOfDay();

        $opening = $from ? $this->balanceBefore($producer, $from) : 0.0;

        $remitos = $this->remitos($producer)
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->get()
            ->map(fn (Sale $sale) => $this->row('remito', $sale->id, $sale->created_at, $sale->voucher_number, null, (float) $sale->total, 0.0));

        $payments = $producer->payments()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->get()
            ->map(fn (Payment $payment) => $this->row('pago', $payment->id, $payment->created_at, sprintf('P-%08d', $payment->id), $this->paymentDetail($payment), 0.0, (float) $payment->amount));

        $creditNotes = $producer->creditNotes()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->get()
            ->map(fn (CreditNote $note) => $this->row('nota_credito', $note->id, $note->created_at, $note->voucher_number, $note->reason, 0.0, (float) $note->amount));

        $cents = (int) round($opening * 100);

        $rows = $remitos->concat($payments)->concat($creditNotes)
            ->sortBy(fn (array $row) => [$row['time']->getTimestamp(), self::KIND_ORDER[$row['kind']], $row['id']])
            ->values()
            ->map(function (array $row) use (&$cents) {
                $cents += (int) round($row['debit'] * 100) - (int) round($row['credit'] * 100);

                return [...$row, 'balance' => round($cents / 100, 2)];
            });

        return [
            'opening' => round($opening, 2),
            'rows' => $rows,
            'debit' => round($rows->sum('debit'), 2),
            'credit' => round($rows->sum('credit'), 2),
            'closing' => round($cents / 100, 2),
        ];
    }

    protected function balanceBefore(Producer $producer, CarbonImmutable $from): float
    {
        $debit = (float) $this->remitos($producer)->where('created_at', '<', $from)->sum('total');
        $credit = (float) $producer->payments()->where('created_at', '<', $from)->sum('amount')
            + (float) $producer->creditNotes()->where('created_at', '<', $from)->sum('amount');

        return round($debit - $credit, 2);
    }

    /**
     * @return HasMany<Sale, Producer>
     */
    protected function remitos(Producer $producer): HasMany
    {
        return $producer->sales()->where('type', 'remito');
    }

    protected function paymentDetail(Payment $payment): string
    {
        $method = Payment::METHODS[$payment->payment_method] ?? $payment->payment_method;

        return $payment->notes ? "{$method} · {$payment->notes}" : $method;
    }

    /**
     * @return array{key: string, id: int, time: CarbonImmutable, kind: string, voucher: string, detail: ?string, debit: float, credit: float}
     */
    protected function row(string $kind, int $id, mixed $time, string $voucher, ?string $detail, float $debit, float $credit): array
    {
        return [
            'key' => "{$kind}-{$id}",
            'id' => $id,
            'time' => $time->toImmutable(),
            'kind' => $kind,
            'voucher' => $voucher,
            'detail' => $detail,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }
}
