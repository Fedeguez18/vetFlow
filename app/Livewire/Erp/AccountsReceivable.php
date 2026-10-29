<?php

namespace App\Livewire\Erp;

use App\Exceptions\PaymentException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Livewire\Forms\ProducerForm;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use App\Services\AccountStatementService;
use App\Services\PaymentAllocationService;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use App\Support\Pricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts::erp')]
#[Title('Cuentas Corrientes')]
class AccountsReceivable extends Component
{
    use RequiresAdmin;

    public string $search = '';

    #[Url(as: 'productor', except: null)]
    public ?int $producerId = null;

    public bool $showPaymentModal = false;

    public string $paymentAmount = '';

    public string $paymentMethod = 'efectivo';

    public string $paymentNotes = '';

    public bool $showCreditNoteModal = false;

    public string $creditNoteAmount = '';

    public string $creditNoteReason = '';

    /**
     * Statement range (Y-m-d). Empty means no bound.
     */
    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showInactive = false;

    public bool $showProducerModal = false;

    public ProducerForm $form;

    public ?string $alert = null;

    public string $alertType = 'success';

    /**
     * Payment just registered, shown in the success modal with its receipt.
     *
     * @var array{id: int, voucher: string, amount: float, method: string, balance: float, allocations: array<int, array{voucher: string, applied: float, pending: float}>, unapplied: float}|null
     */
    public ?array $paymentSuccess = null;

    public function mount(): void
    {
        $this->dateFrom = today()->subMonths(3)->format('Y-m-d');
        $this->dateTo = today()->format('Y-m-d');
    }

    public function selectProducer(int $producerId): void
    {
        $this->producerId = $producerId;
        $this->alert = null;
        $this->closeModals();
    }

    public function closeModals(): void
    {
        $this->showPaymentModal = false;
        $this->showCreditNoteModal = false;
        $this->showProducerModal = false;
        $this->paymentSuccess = null;
        $this->resetValidation();
    }

    public function openCreateModal(): void
    {
        $this->closeModals();
        $this->form->reset();
        $this->showProducerModal = true;
    }

    public function openEditModal(): void
    {
        if (! $this->selectedProducer) {
            return;
        }

        $this->closeModals();
        $this->form->reset();
        $this->form->setProducer($this->selectedProducer);
        $this->showProducerModal = true;
    }

    /**
     * Create a new account or save the changes to the one being edited.
     */
    public function saveProducer(): void
    {
        $this->form->normalize();
        $this->form->validate();

        if ($this->form->isEditing()) {
            $producer = $this->form->producer;
            $producer->update($this->form->attributes());
            $message = "Datos de {$producer->name} guardados.";
        } else {
            $producer = Producer::create($this->form->attributes());
            $this->search = '';
            $message = "Cuenta de {$producer->name} creada.";
        }

        $this->showProducerModal = false;
        $this->form->reset();
        $this->producerId = $producer->id;
        $this->forgetProducerCaches();

        $this->notify($message);
    }

    /**
     * Give the account a baja: it disappears from the POS and the list, its history is kept.
     * Only accounts with a zero balance can be closed so no debt (or credit) is left hidden.
     */
    public function deactivateProducer(): void
    {
        if (! $this->producerId) {
            return;
        }

        $error = DB::transaction(function () {
            $producer = Producer::query()->lockForUpdate()->findOrFail($this->producerId);

            if ($producer->isWalkIn()) {
                return 'La cuenta de Consumidor Final no se puede dar de baja.';
            }

            if ((float) $producer->balance !== 0.0) {
                return $producer->hasCreditBalance()
                    ? 'No se puede dar de baja una cuenta con saldo a favor ('.Pricing::moneyWithCents(-(float) $producer->balance).').'
                    : 'No se puede dar de baja una cuenta con saldo ('.Pricing::moneyWithCents($producer->balance).'). Cancelá la deuda primero.';
            }

            $producer->update(['is_active' => false]);

            return null;
        });

        if ($error) {
            $this->notify($error, 'error');

            return;
        }

        $this->closeModals();
        $this->forgetProducerCaches();
        $this->notify("Cuenta de {$this->selectedProducer->name} dada de baja.");

        if (! $this->showInactive) {
            $this->producerId = null;
        }
    }

    public function reactivateProducer(): void
    {
        $producer = $this->selectedProducer;

        if (! $producer || $producer->is_active) {
            return;
        }

        $producer->update(['is_active' => true]);
        $this->forgetProducerCaches();
        $this->notify("Cuenta de {$producer->name} reactivada.");
    }

    public function openPaymentModal(): void
    {
        if (! $this->selectedProducer?->is_active) {
            return;
        }

        $this->closeModals();
        $this->reset('paymentAmount', 'paymentMethod', 'paymentNotes');
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->resetValidation();
    }

    public function fillFullBalance(): void
    {
        $this->paymentAmount = number_format(max(0, (float) $this->selectedProducer?->balance), 2, '.', '');
    }

    public function registerPayment(PaymentService $payments, PaymentAllocationService $allocations): void
    {
        if (! $this->producerId) {
            return;
        }

        $amount = Pricing::parse($this->paymentAmount);
        $this->paymentAmount = $amount === null ? trim($this->paymentAmount) : (string) $amount;

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paymentNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'paymentAmount.required' => 'Ingresá el monto.',
            'paymentAmount.numeric' => 'El monto tiene que ser un número.',
            'paymentAmount.min' => 'El monto tiene que ser mayor a cero.',
            'paymentMethod.*' => 'Elegí un medio de pago válido.',
            'paymentNotes.max' => 'La nota no puede superar los 500 caracteres.',
        ]);

        try {
            $payment = $payments->register($this->producerId, (float) $this->paymentAmount, $this->paymentMethod, $this->paymentNotes, auth()->id());
        } catch (PaymentException $e) {
            $this->addError('paymentAmount', $e->getMessage());

            return;
        }

        $this->showPaymentModal = false;
        $this->reset('paymentAmount', 'paymentMethod', 'paymentNotes');
        $this->forgetProducerCaches();
        $this->alert = null;

        $receipt = $allocations->forPayment($payment);

        $this->paymentSuccess = [
            'id' => $payment->id,
            'voucher' => $payment->voucher_number,
            'amount' => (float) $payment->amount,
            'method' => Payment::METHODS[$payment->payment_method] ?? $payment->payment_method,
            'balance' => (float) $this->selectedProducer->balance,
            'allocations' => $receipt['allocations']->map(fn (array $allocation) => [
                'voucher' => $allocation['sale']->voucher_number,
                'applied' => $allocation['applied'],
                'pending' => $allocation['pending'],
            ])->all(),
            'unapplied' => $receipt['unapplied'],
        ];
    }

    public function closePaymentSuccess(): void
    {
        $this->paymentSuccess = null;
    }

    public function openCreditNoteModal(): void
    {
        if (! $this->selectedProducer?->is_active) {
            return;
        }

        $this->closeModals();
        $this->reset('creditNoteAmount', 'creditNoteReason');
        $this->showCreditNoteModal = true;
    }

    public function registerCreditNote(PaymentService $payments): void
    {
        if (! $this->producerId) {
            return;
        }

        $amount = Pricing::parse($this->creditNoteAmount);
        $this->creditNoteAmount = $amount === null ? trim($this->creditNoteAmount) : (string) $amount;
        $this->creditNoteReason = trim($this->creditNoteReason);

        $this->validate([
            'creditNoteAmount' => ['required', 'numeric', 'min:0.01'],
            'creditNoteReason' => ['required', 'string', 'max:255'],
        ], [
            'creditNoteAmount.required' => 'Ingresá el monto.',
            'creditNoteAmount.numeric' => 'El monto tiene que ser un número.',
            'creditNoteAmount.min' => 'El monto tiene que ser mayor a cero.',
            'creditNoteReason.required' => 'Indicá el motivo.',
            'creditNoteReason.max' => 'El motivo no puede superar los 255 caracteres.',
        ]);

        try {
            $creditNote = $payments->registerCreditNote($this->producerId, (float) $this->creditNoteAmount, $this->creditNoteReason);
        } catch (PaymentException $e) {
            $this->addError('creditNoteAmount', $e->getMessage());

            return;
        }

        $this->showCreditNoteModal = false;
        $this->reset('creditNoteAmount', 'creditNoteReason');
        $this->forgetProducerCaches();

        $this->notify("Nota de crédito {$creditNote->voucher_number} por ".Pricing::moneyWithCents($creditNote->amount).' emitida. '.$this->balanceSentence());
    }

    /**
     * Balance preview shown in the payment modal while the amount is typed (negative = saldo a favor).
     */
    #[Computed]
    public function balanceAfterPayment(): ?float
    {
        return $this->balanceAfter($this->paymentAmount);
    }

    #[Computed]
    public function balanceAfterCreditNote(): ?float
    {
        return $this->balanceAfter($this->creditNoteAmount);
    }

    #[Computed]
    public function producers(): Collection
    {
        return Producer::query()
            ->when(! $this->showInactive, fn ($query) => $query->active())
            ->search($this->search)
            ->withUnpaidCount()
            ->orderByDesc('is_active')
            ->orderByDesc('balance')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function totalDebt(): float
    {
        return (float) Producer::query()->where('balance', '>', 0)->sum('balance');
    }

    #[Computed]
    public function selectedProducer(): ?Producer
    {
        return $this->producerId ? Producer::find($this->producerId) : null;
    }

    /**
     * Receipts that paid each remito of the selected producer, to reprint them from the remito.
     *
     * @return array<int, Collection<int, array{payment: Payment, applied: float}>>
     */
    #[Computed]
    public function receiptsBySale(): array
    {
        return $this->selectedProducer ? app(PaymentAllocationService::class)->receiptsBySale($this->selectedProducer) : [];
    }

    /**
     * @return Collection<int, Sale>
     */
    #[Computed]
    public function unpaidSales(): Collection
    {
        if (! $this->producerId) {
            return collect();
        }

        return Sale::query()
            ->with('saleItems.product')
            ->where('producer_id', $this->producerId)
            ->unpaidRemitos()
            ->get();
    }

    /**
     * Debe / Haber / Saldo of the selected producer within the chosen dates.
     *
     * @return array{opening: float, rows: Collection<int, array<string, mixed>>, debit: float, credit: float, closing: float}|null
     */
    #[Computed]
    public function statement(): ?array
    {
        if (! $this->selectedProducer) {
            return null;
        }

        return app(AccountStatementService::class)->statement($this->selectedProducer, $this->parseDate($this->dateFrom), $this->parseDate($this->dateTo));
    }

    #[Computed]
    public function whatsappUrl(): ?string
    {
        return $this->selectedProducer
            ? app(WhatsAppService::class)->balanceReminderUrl($this->selectedProducer, $this->unpaidSales)
            : null;
    }

    public function render()
    {
        return view('livewire.erp.accounts-receivable', [
            'methods' => Payment::METHODS,
            'ivaConditions' => Producer::IVA_CONDITIONS,
        ]);
    }

    protected function balanceAfter(string $typed): ?float
    {
        $amount = Pricing::parse($typed);

        if (! $this->selectedProducer || $amount === null) {
            return null;
        }

        return round((float) $this->selectedProducer->balance - $amount, 2);
    }

    /**
     * "Nuevo saldo: $ 1.500,00." or "Saldo a favor: $ 200,00." for the alerts.
     */
    protected function balanceSentence(): string
    {
        $balance = (float) $this->selectedProducer->balance;

        return $balance < 0
            ? 'Saldo a favor: '.Pricing::moneyWithCents(-$balance).'.'
            : 'Nuevo saldo: '.Pricing::moneyWithCents($balance).'.';
    }

    protected function parseDate(string $date): ?CarbonImmutable
    {
        if (trim($date) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function notify(string $message, string $type = 'success'): void
    {
        $this->alert = $message;
        $this->alertType = $type;
    }

    protected function forgetProducerCaches(): void
    {
        unset($this->producers, $this->selectedProducer, $this->unpaidSales, $this->receiptsBySale, $this->statement, $this->totalDebt);
    }
}
