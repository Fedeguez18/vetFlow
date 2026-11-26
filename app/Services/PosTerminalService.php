<?php

namespace App\Services;

use App\Exceptions\CashSessionException;
use App\Models\CashSession;
use App\Models\PosTerminal;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class PosTerminalService
{
    public function __construct(protected CashSessionService $cashSessions) {}

    /**
     * The caja open on this terminal, whoever is in front of it. A caja idle for too long closes here.
     */
    public function openCashSession(): ?CashSession
    {
        $cashSession = $this->current()?->openCashSession;

        if (! $cashSession || $this->cashSessions->closeIfIdle($cashSession)) {
            return null;
        }

        return $cashSession->load('user');
    }

    /**
     * The caja open on this terminal, only if this browser unlocked it with its user's code.
     * Closing the browser locks the mostrador again: the caja stays open until someone closes it.
     */
    public function unlockedCashSession(): ?CashSession
    {
        $cashSession = $this->openCashSession();

        return $cashSession && $this->isUnlocked($cashSession) ? $cashSession : null;
    }

    public function isUnlocked(CashSession $cashSession): bool
    {
        return (string) request()->cookie(CashSession::UNLOCK_COOKIE) === (string) $cashSession->id;
    }

    /**
     * Let this browser sell with the caja until it is closed (a cookie without expiry dies with the browser).
     */
    public function unlock(CashSession $cashSession): void
    {
        Cookie::queue(Cookie::make(CashSession::UNLOCK_COOKIE, (string) $cashSession->id, 0));
        $this->cashSessions->recordActivity($cashSession);
    }

    public function lock(): void
    {
        Cookie::queue(Cookie::forget(CashSession::UNLOCK_COOKIE));
    }

    /**
     * The terminal this browser was enabled as, from its cookie.
     */
    public function current(): ?PosTerminal
    {
        $token = request()->cookie(PosTerminal::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return PosTerminal::query()->where('token_hash', PosTerminal::hashToken($token))->first();
    }

    /**
     * Enable the PC making the request as a terminal: the token only lives in its cookie.
     */
    public function enable(string $name, User $admin): PosTerminal
    {
        $token = Str::random(60);

        $terminal = PosTerminal::create([
            'name' => trim($name) ?: 'Mostrador',
            'token_hash' => PosTerminal::hashToken($token),
            'user_id' => $admin->id,
        ]);

        Cookie::queue(Cookie::forever(PosTerminal::COOKIE, $token));

        return $terminal;
    }

    /**
     * Stop accepting the terminal's cookie. A terminal with an open caja has to close it first.
     *
     * @throws CashSessionException
     */
    public function revoke(PosTerminal $terminal): void
    {
        $this->cashSessions->closeIdle();

        if ($terminal->openCashSession()->exists()) {
            throw new CashSessionException("La terminal {$terminal->name} tiene una caja abierta. Cerrala antes de deshabilitarla.");
        }

        $terminal->delete();
    }
}
