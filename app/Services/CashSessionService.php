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
     * Hours without using the mostrador after which a caja closes by itself.
     */
    public const IDLE_HOURS = 4;

    /**
     * Activity is written at most once per this many seconds, not on every click.
     */
    public const ACTIVITY_THROTTLE_SECONDS = 60;

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

        $this->closeIdle();

        return DB::transaction(function () use ($terminal, $user, $openingCents) {
            $terminal = PosTerminal::query()->lockForUpdate()->findOrFail($terminal->id);

            if ($terminal->openCashSession()->exists()) {
                throw new CashSessionException('Ya hay una caja abierta en esta terminal.');
            }

            $elsewhere = CashSession::query()->open()->with('posTerminal')->where('user_id', $user->id)->first();

            if ($elsewhere) {
                $where = $elsewhere->posTerminal ? "en la terminal {$elsewhere->posTerminal->name}" : 'en otra terminal';

                throw new CashSessionException("{$user->name} ya tiene una caja abierta {$where}. Cerrala ahí para abrir una acá.");
            }

            $terminal->update(['last_used_at' => now()]);

            return $terminal->cashSessions()->create([
                'user_id' => $user->id,
                'opening_amount' => $openingCents / 100,
                'last_activity_at' => now(),
            ]);
        });
    }

    /**
     * Close the caja with the cash counted by its user, or by another user who takes over the terminal,
     * keeping what the system expected to compare them.
     *
     * @throws CashSessionException
     */
    public function close(CashSession $cashSession, float $declaredCash, ?User $closedBy = null): CashSession
    {
        $declaredCents = (int) round($declaredCash * 100);

        if ($declaredCents < 0) {
            throw new CashSessionException('El efectivo contado no puede ser negativo.');
        }

        return DB::transaction(function () use ($cashSession, $declaredCents, $closedBy) {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if (! $cashSession->isOpen()) {
                throw new CashSessionException('La caja ya estaba cerrada.');
            }

            $cashSession->update([
                'closed_at' => now(),
                'expected_cash' => $this->expectedCash($cashSession),
                'declared_cash' => $declaredCents / 100,
                'closed_by' => $closedBy?->id ?? $cashSession->user_id,
            ]);

            return $cashSession;
        });
    }

    /**
     * Close the cajas nobody used for IDLE_HOURS. Nobody counted the cash: it stays without a declared
     * amount for administración, closed at the moment it went idle (not when someone noticed).
     *
     * @return int how many cajas were closed
     */
    public function closeIdle(): int
    {
        $cutoff = now()->subHours(self::IDLE_HOURS);

        return CashSession::query()
            ->open()
            ->where(fn ($query) => $query
                ->where('last_activity_at', '<=', $cutoff)
                ->orWhere(fn ($query) => $query->whereNull('last_activity_at')->where('created_at', '<=', $cutoff)))
            ->pluck('id')
            ->filter(fn (int $id) => $this->closeIfIdle(CashSession::findOrFail($id)))
            ->count();
    }

    /**
     * Close the caja without a cash count when it has been idle for IDLE_HOURS.
     *
     * @return bool whether it was closed
     */
    public function closeIfIdle(CashSession $cashSession): bool
    {
        if (! $this->isIdle($cashSession)) {
            return false;
        }

        return DB::transaction(function () use ($cashSession) {
            $cashSession = CashSession::query()->lockForUpdate()->findOrFail($cashSession->id);

            if (! $cashSession->isOpen() || ! $this->isIdle($cashSession)) {
                return false;
            }

            $cashSession->update([
                'closed_at' => $cashSession->lastActivityAt()->copy()->addHours(self::IDLE_HOURS),
                'expected_cash' => $this->expectedCash($cashSession),
                'closed_automatically' => true,
            ]);

            return true;
        });
    }

    public function isIdle(CashSession $cashSession): bool
    {
        return $cashSession->isOpen() && $cashSession->lastActivityAt()->lte(now()->subHours(self::IDLE_HOURS));
    }

    /**
     * Someone is using the mostrador with this caja: it starts counting the idle hours again.
     */
    public function recordActivity(CashSession $cashSession): void
    {
        if ($cashSession->lastActivityAt()->gt(now()->subSeconds(self::ACTIVITY_THROTTLE_SECONDS))) {
            return;
        }

        $cashSession->update(['last_activity_at' => now()]);
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
            ->with(['user', 'reviewer', 'closer', 'posTerminal'])
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
