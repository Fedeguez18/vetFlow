<?php

namespace App\Services;

use App\Exceptions\CashSessionException;
use App\Models\PosTerminal;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class PosTerminalService
{
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
        if ($terminal->openCashSession()->exists()) {
            throw new CashSessionException("La terminal {$terminal->name} tiene una caja abierta. Cerrala antes de deshabilitarla.");
        }

        $terminal->delete();
    }
}
