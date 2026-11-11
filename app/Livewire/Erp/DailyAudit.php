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

    /**
     * Ranges the transaction log can span, around the audited day.
     */
    public const PERIODS = ['dia' => 'Día', 'semana' => 'Semana', 'mes' => 'Mes'];

    #[Url(except: '')]
    public string $date = '';

    #[Url(except: 'dia')]
    public string $period = 'dia';

    public function updatedPeriod(): void
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'dia';
        }
    }

    /**
     * First and last day of the transaction log: the audited day, its Monday-to-Sunday week or its calendar month.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function periodRange(): array
    {
        return match ($this->period) {
            'semana' => [$this->day->startOfWeek(CarbonImmutable::MONDAY), $this->day->endOfWeek(CarbonImmutable::SUNDAY)],
            'mes' => [$this->day->startOfMonth(), $this->day->endOfMonth()],
            default => [$this->day, $this->day],
        };
    }

    /**
     * Title of the transaction log for the selected period.
     */
    #[Computed]
    public function periodLabel(): string
    {
        [$from, $to] = $this->periodRange;

        return match ($this->period) {
            'semana' => 'Transacciones de la semana del '.$from->format('d/m').' al '.$to->format('d/m/Y'),
            'mes' => 'Transacciones de '.$from->locale('es')->isoFormat('MMMM [de] YYYY'),
            default => 'Transacciones del día',
        };
    }

    #[Computed]
    public function transactions(): Collection
    {
        return $this->period === 'dia' ? $this->entries : app(CashAuditService::class)->entries(...$this->periodRange);
    }

    #[Computed]
    public function transactionsSummary(): array
    {
        return $this->period === 'dia' ? $this->summary : app(CashAuditService::class)->summary($this->transactions);
    }

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
