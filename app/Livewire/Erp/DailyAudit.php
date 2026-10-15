<?php

namespace App\Livewire\Erp;

use App\Services\CashAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts::erp')]
#[Title('Auditoría & Caja')]
class DailyAudit extends Component
{
    #[Url(except: '')]
    public string $date = '';

    /**
     * The audited day: the one in the URL, or today when it is missing, invalid or in the future.
     */
    #[Computed]
    public function day(): CarbonImmutable
    {
        try {
            $day = $this->date !== '' ? CarbonImmutable::createFromFormat('!Y-m-d', $this->date) : null;
        } catch (Throwable) {
            $day = null;
        }

        return $day && $day->lte(today()) ? $day : CarbonImmutable::today();
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

    public function render()
    {
        return view('livewire.erp.daily-audit', [
            'methods' => CashAuditService::METHODS + ['sin_registrar' => 'Sin registrar'],
        ]);
    }
}
