<?php

namespace App\Livewire\Pos;

use App\Exceptions\CashSessionException;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\PosTerminalService;
use App\Services\UserService;
use App\Support\Pricing;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Locked mostrador: the user types their POS code and opens a caja in their name.
 */
#[Layout('layouts::guest')]
#[Title('Abrir caja')]
class PosLock extends Component
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 300;

    public string $code = '';

    /**
     * Set once the code matched: the second step asks for the opening amount.
     */
    #[Locked]
    public ?int $userId = null;

    public string $openingAmount = '';

    public function mount(PosTerminalService $terminals)
    {
        if ($terminals->current()?->openCashSession) {
            return $this->redirectRoute('pos.desk');
        }
    }

    public function unlock(UserService $users, PosTerminalService $terminals): void
    {
        $this->resetErrorBag();
        $key = 'pos-code:'.$terminals->current()->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->addError('code', 'Demasiados intentos. Probá de nuevo en '.RateLimiter::availableIn($key).' segundos.');

            return;
        }

        $user = $users->findByPosCode($this->code);
        $this->code = '';

        if (! $user) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            $this->addError('code', 'Código incorrecto.');

            return;
        }

        RateLimiter::clear($key);
        $this->userId = $user->id;
    }

    public function back(): void
    {
        $this->reset('code', 'userId', 'openingAmount');
        $this->resetValidation();
    }

    public function openCashRegister(CashSessionService $cashSessions, PosTerminalService $terminals)
    {
        if (! $this->user) {
            $this->back();

            return null;
        }

        $amount = trim($this->openingAmount) === '' ? 0.0 : Pricing::parse($this->openingAmount);

        if ($amount === null) {
            $this->addError('openingAmount', 'El fondo inicial tiene que ser un número.');

            return null;
        }

        try {
            $cashSessions->open($terminals->current(), $this->user, $amount);
        } catch (CashSessionException $e) {
            $this->addError('openingAmount', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('pos.desk');
    }

    #[Computed]
    public function user(): ?User
    {
        return $this->userId ? User::query()->where('is_active', true)->find($this->userId) : null;
    }

    public function render()
    {
        return view('livewire.pos.pos-lock');
    }
}
