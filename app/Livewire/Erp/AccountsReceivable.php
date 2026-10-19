<?php

namespace App\Livewire\Erp;

use App\Exceptions\PaymentException;
use App\Livewire\Forms\ProducerForm;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use App\Support\Pricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Cuentas Corrientes')]
class AccountsReceivable extends Component
{
    public string $search = '';

    #[Url(as: 'productor', except: null)]
    public ?int $producerId = null;

    public bool $showPaymentModal = false;

    public string $paymentAmount = '';

    public string $paymentMethod = 'efectivo';

    public string $paymentNotes = '';

    public bool $showInactive = false;

    public bool $showCreateModal = false;

    public ProducerForm $form;

    public ?string $alert = null;

    public string $alertType = 'success';

    public function selectProducer(int $producerId): void
    {
        $this->producerId = $producerId;
        $this->alert = null;
        $this->closePaymentModal();
    }

    public function openCreateModal(): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->showPaymentModal = false;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetValidation();
    }

    public function createProducer(): void
    {
        $this->form->normalize();
        $this->form->validate();

        $producer = Producer::create($this->form->attributes());

        $this->showCreateModal = false;
        $this->form->reset();
        $this->search = '';
        $this->producerId = $producer->id;
        $this->forgetProducerCaches();

        $this->notify("Cuenta de {$producer->name} creada.");
    }

    /**
     * Give the account a baja: it disappears from the POS and the list, its history is kept.
     * Only accounts without balance can be closed so no debt is left hidden.
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
                return 'No se puede dar de baja una cuenta con saldo ('.Pricing::moneyWithCents($producer->balance).'). Cancelá la deuda primero.';
            }

            $producer->update(['is_active' => false]);

            return null;
        });

        if ($error) {
            $this->notify($error, 'error');

            return;
        }

        $this->closePaymentModal();
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

    protected function notify(string $message, string $type = 'success'): void
    {
        $this->alert = $message;
        $this->alertType = $type;
    }

    protected function forgetProducerCaches(): void
    {
        unset($this->producers, $this->selectedProducer, $this->unpaidSales, $this->recentPayments, $this->totalDebt);
    }

    public function openPaymentModal(): void
    {
        if (! $this->selectedProducer || (float) $this->selectedProducer->balance <= 0) {
            return;
        }

        $this->reset('paymentAmount', 'paymentMethod', 'paymentNotes');
        $this->resetValidation();
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->resetValidation();
    }

    public function fillFullBalance(): void
    {
        $this->paymentAmount = number_format((float) $this->selectedProducer?->balance, 2, '.', '');
    }

    public function registerPayment(PaymentService $payments): void
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
            $payment = $payments->register($this->producerId, (float) $this->paymentAmount, $this->paymentMethod, $this->paymentNotes);
        } catch (PaymentException $e) {
            $this->addError('paymentAmount', $e->getMessage());

            return;
        }

        $this->showPaymentModal = false;
        $this->reset('paymentAmount', 'paymentMethod', 'paymentNotes');
        $this->forgetProducerCaches();

        $this->notify('Pago de '.Pricing::moneyWithCents($payment->amount).' registrado. Nuevo saldo: '.Pricing::moneyWithCents($this->selectedProducer->balance).'.');
    }

    /**
     * Balance preview shown in the payment modal while the amount is typed.
     */
    #[Computed]
    public function balanceAfterPayment(): ?float
    {
        $amount = Pricing::parse($this->paymentAmount);

        if (! $this->selectedProducer || $amount === null) {
            return null;
        }

        return round((float) $this->selectedProducer->balance - $amount, 2);
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

    #[Computed]
    public function recentPayments(): Collection
    {
        return $this->selectedProducer?->payments()->latest()->latest('id')->limit(5)->get() ?? collect();
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
}
