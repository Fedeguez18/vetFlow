<?php

namespace Tests;

use App\Models\CashSession;
use App\Models\PosTerminal;
use App\Models\User;
use App\Services\CashSessionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    /**
     * Make the following requests come from an enabled mostrador terminal.
     */
    protected function onPosTerminal(): PosTerminal
    {
        $token = Str::random(40);

        $this->withCookie(PosTerminal::COOKIE, $token);
        Livewire::withCookie(PosTerminal::COOKIE, $token);

        return PosTerminal::factory()->withToken($token)->create();
    }

    /**
     * Work from a terminal with a caja open in the user's name (a new seller by default).
     */
    protected function openCashRegister(?User $user = null, float $openingAmount = 0): CashSession
    {
        return app(CashSessionService::class)->open($this->onPosTerminal(), $user ?? User::factory()->create(), $openingAmount);
    }
}
