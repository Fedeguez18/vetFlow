<?php

namespace App\Services;

use App\Exceptions\CashSessionException;
use App\Models\CashSession;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashSessionService
{
    /**
     * Open a caja on the terminal in the name of the user who typed their code.
     * One open caja per terminal and per user.
     *
     * @throws CashSessionException
     */
    public function open(PosTerminal $terminal, User $user, float $openingAmount = 0): CashSession
    {
        $openingCents = (int) round($openingAmount * 100);

        if ($openingCents < 0) {
            throw new CashSessionException('El fondo inicial no puede ser negativo.');
        }

        if (! $user->is_active) {
            throw new CashSessionException("El usuario {$user->name} está dado de baja.");
        }

        return DB::transaction(function () use ($terminal, $user, $openingCents) {
            $terminal = PosTerminal::query()->lockForUpdate()->findOrFail($terminal->id);

            if ($terminal->openCashSession()->exists()) {
                throw new CashSessionException('Ya hay una caja abierta en esta terminal.');
            }

            if (CashSession::query()->open()->where('user_id', $user->id)->exists()) {
                throw new CashSessionException("{$user->name} ya tiene una caja abierta en otra terminal.");
            }

            $terminal->update(['last_used_at' => now()]);

            return $terminal->cashSessions()->create([
                'user_id' => $user->id,
                'opening_amount' => $openingCents / 100,
            ]);
        });
    }

    /**
     * Close the caja with the cash the user counted, keeping what the system expected to compare them.
     *
     * @throws CashSessionException
     */
    public function close(CashSession $cashSession, float $declaredCash): CashSession
    {
        $declaredCents = (int) round($declaredCash * 100);

        if ($declaredCents < 0) {
            throw new CashSessionException('El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($cashSession, $declaredCents) {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if (! $cashSession->isOpen()) {
                throw new CashSessionException('La caja ya estaba cerrada.');
            }

            $cashSession->update([
                'closed_at' => now(),
                'expected_cash' => $this->expectedCash($cashSession),
                'declared_cash' => $declaredCents / 100,
            ]);

            return $cashSession;
        });
    }

    /**
     * Opening amount plus the cash sales of the caja.
     */
    public function expectedCash(CashSession $cashSession): float
    {
        $cashSales = (float) $cashSession->sales()->where('type', 'contado')->where('payment_method', 'efectivo')->sum('total');

        return round((float) $cashSession->opening_amount + $cashSales, 2);
    }

    public function review(CashSession $cashSession, User $admin): void
    {
        if ($cashSession->isOpen()) {
            throw new CashSessionException('La caja todavía está abierta.');
        }

        $cashSession->update(['reviewed_at' => now(), 'reviewed_by' => $admin->id]);
    }

    /**
     * Cajas that were open at some point of the day, with what was sold in each by method
     * (remitos as cuenta corriente: they don't bring money in).
     *
     * @return Collection<int, array{session: CashSession, by_method: array<string, float>, sales: int, expected: float}>
     */
    public function forDay(CarbonImmutable $day): Collection
    {
        $sessions = CashSession::query()
            ->with(['user', 'reviewer', 'posTerminal'])
            ->where('created_at', '<=', $day->endOfDay())
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $day->startOfDay()))
            ->orderBy('created_at')
            ->get();

        $totals = Sale::query()
            ->whereIn('cash_session_id', $sessions->modelKeys())
            ->selectRaw("cash_session_id, case when type = 'remito' then 'cuenta_corriente' else payment_method end as method, sum(total) as amount, count(*) as sales")
            ->groupBy('cash_session_id', 'method')
            ->get()
            ->groupBy('cash_session_id');

        return $sessions->map(function (CashSession $session) use ($totals) {
            $rows = $totals->get($session->id, collect());

            return [
                'session' => $session,
                'by_method' => $rows->mapWithKeys(fn ($row) => [$row->method ?? 'sin_registrar' => round((float) $row->amount, 2)])->sortDesc()->all(),
                'sales' => (int) $rows->sum('sales'),
                'expected' => $session->isOpen() ? $this->expectedCash($session) : (float) $session->expected_cash,
            ];
        });
    }
}
