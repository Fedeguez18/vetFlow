<?php

namespace App\Livewire\Pos;

use App\Exceptions\CashSessionException;
use App\Models\CashSession;
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
 * Locked mostrador: the user types their POS code and opens a caja in their name. When a caja is
 * still open on the terminal (the browser was closed), its user resumes it with their code and
 * anyone else has to close it counting the cash before opening their own.
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

    /**
     * Cash counted when closing the caja another user left open (they don't see what is expected).
     */
    public string $declaredCash = '';

    public ?string $status = null;

    public function mount(PosTerminalService $terminals)
    {
        if ($terminals->unlockedCashSession()) {
            return $this->redirectRoute('pos.desk');
        }
    }

    public function unlock(UserService $users, PosTerminalService $terminals)
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

            return null;
        }

        RateLimiter::clear($key);
        $openCashSession = $this->openCashSession;

        if ($openCashSession && $openCashSession->user_id === $user->id) {
            $terminals->unlock($openCashSession);

            return $this->redirectRoute('pos.desk');
        }

        $this->userId = $user->id;

        return null;
    }

    public function back(): void
    {
        $this->reset('code', 'userId', 'openingAmount', 'declaredCash', 'status');
        $this->resetValidation();
    }

    /**
     * Close the caja another user left open on the terminal, with the cash counted by the user taking over.
     */
    public function closePreviousCashRegister(CashSessionService $cashSessions): void
    {
        $this->resetErrorBag();
        $previous = $this->openCashSession;

        if (! $this->user || ! $previous || $previous->user_id === $this->user->id) {
            return;
        }

        $amount = Pricing::parse($this->declaredCash);

        if ($amount === null) {
            $this->addError('declaredCash', 'Ingresá el efectivo que contaste (0 si no hay).');

            return;
        }

        try {
            $cashSessions->close($previous, $amount, $this->user);
        } catch (CashSessionException $e) {
            $this->addError('declaredCash', $e->getMessage());

            return;
        }

        $this->reset('declaredCash');
        unset($this->openCashSession);
        $this->status = "Caja de {$previous->user->name} cerrada y enviada a administración.";
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
            $cashSession = $cashSessions->open($terminals->current(), $this->user, $amount);
        } catch (CashSessionException $e) {
            $this->addError('openingAmount', $e->getMessage());

            return null;
        }

        $terminals->unlock($cashSession);

        return $this->redirectRoute('pos.desk');
    }

    #[Computed]
    public function user(): ?User
    {
        return $this->userId ? User::query()->where('is_active', true)->find($this->userId) : null;
    }

    /**
     * The caja still open on the terminal (locked because the browser was closed), if any.
     */
    #[Computed]
    public function openCashSession(): ?CashSession
    {
        return app(PosTerminalService::class)->openCashSession();
    }

    public function render()
    {
        return view('livewire.pos.pos-lock');
    }
}
