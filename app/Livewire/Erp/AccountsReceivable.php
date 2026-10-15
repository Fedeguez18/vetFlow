<?php

namespace App\Livewire\Erp;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use App\Support\Pricing;
use Illuminate\Support\Collection;
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

    public ?string $alert = null;

    public function selectProducer(int $producerId): void
    {
        $this->producerId = $producerId;
        $this->alert = null;
        $this->closePaymentModal();
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
        unset($this->producers, $this->selectedProducer, $this->unpaidSales, $this->recentPayments, $this->totalDebt);

        $this->alert = 'Pago de '.Pricing::moneyWithCents($payment->amount).' registrado. Nuevo saldo: '.Pricing::moneyWithCents($this->selectedProducer->balance).'.';
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
            ->search($this->search)
            ->withUnpaidCount()
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
        ]);
    }
}
