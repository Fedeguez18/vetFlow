<?php

namespace App\Livewire\Erp;

use App\Exceptions\CashSessionException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\CashSession;
use App\Services\CashAuditService;
use App\Services\CashSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Auditoría & Caja')]
class DailyAudit extends Component
{
    use RequiresAdmin;

    #[Url(except: '')]
    public string $date = '';

    /**
     * The audited day: the one in the URL, or today when it is missing, invalid or in the future.
     */
    #[Computed]
    public function day(): CarbonImmutable
    {
        return app(CashAuditService::class)->day($this->date);
    }

    #[Computed]
    public function entries(): Collection
    {
        return app(CashAuditService::class)->entries($this->day);
    }

    #[Computed]
    public function summary(): array
    {
        return app(CashAuditService::class)->summary($this->entries);
    }

    /**
     * @return Collection<int, array{session: CashSession, by_method: array<string, float>, sales: int, expected: float}>
     */
    #[Computed]
    public function cashSessions(): Collection
    {
        return app(CashSessionService::class)->forDay($this->day);
    }

    public function reviewCashSession(int $id, CashSessionService $cashSessions): void
    {
        try {
            $cashSessions->review(CashSession::findOrFail($id), Auth::user());
        } catch (CashSessionException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        unset($this->cashSessions);
    }

    public function render()
    {
        return view('livewire.erp.daily-audit', [
            'methods' => CashAuditService::METHODS + ['sin_registrar' => 'Sin registrar'],
        ]);
    }
}
