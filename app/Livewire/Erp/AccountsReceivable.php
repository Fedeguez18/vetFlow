<?php

namespace App\Livewire\Erp;

use App\Models\Producer;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Cuentas Corrientes')]
class AccountsReceivable extends Component
{
    public const PAYMENT_METHODS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'cheque' => 'Cheque',
        'tarjeta' => 'Tarjeta',
    ];

    /**
     * Remito statuses that still carry debt.
     */
    public const UNPAID_STATUSES = ['pendiente', 'parcial'];

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

    /**
     * Register a full or partial payment, lower the producer balance and settle the oldest remitos first.
     */
    public function registerPayment(): void
    {
        if (! $this->producerId) {
            return;
        }

        $this->paymentAmount = self::normalizeAmount($this->paymentAmount);

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', Rule::in(array_keys(self::PAYMENT_METHODS))],
            'paymentNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'paymentAmount.required' => 'Ingresá el monto.',
            'paymentAmount.numeric' => 'El monto tiene que ser un número.',
            'paymentAmount.min' => 'El monto tiene que ser mayor a cero.',
            'paymentMethod.*' => 'Elegí un medio de pago válido.',
            'paymentNotes.max' => 'La nota no puede superar los 500 caracteres.',
        ]);

        $cents = (int) round((float) $this->paymentAmount * 100);

        DB::transaction(function () use ($cents) {
            $producer = Producer::query()->lockForUpdate()->findOrFail($this->producerId);
            $balanceCents = (int) round((float) $producer->balance * 100);

            if ($cents > $balanceCents) {
                throw ValidationException::withMessages([
                    'paymentAmount' => 'El monto supera el saldo deudor ('.self::money($producer->balance).').',
                ]);
            }

            $producer->payments()->create([
                'amount' => $cents / 100,
                'payment_method' => $this->paymentMethod,
                'notes' => trim($this->paymentNotes) ?: null,
            ]);

            $producer->decrement('balance', $cents / 100);

            $remaining = $cents;

            $sales = $producer->sales()
                ->where('type', 'remito')
                ->whereIn('status', self::UNPAID_STATUSES)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($sales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $totalCents = (int) round((float) $sale->total * 100);
                $paidCents = (int) round((float) $sale->paid * 100);
                $applied = min($totalCents - $paidCents, $remaining);
                $paidCents += $applied;
                $remaining -= $applied;

                $sale->update([
                    'paid' => $paidCents / 100,
                    'status' => $paidCents >= $totalCents ? 'pagada' : 'parcial',
                ]);
            }
        });

        $amount = self::money($cents / 100);

        $this->showPaymentModal = false;
        $this->reset('paymentAmount', 'paymentMethod', 'paymentNotes');
        unset($this->producers, $this->selectedProducer, $this->unpaidSales, $this->recentPayments, $this->totalDebt);

        $this->alert = "Pago de {$amount} registrado. Nuevo saldo: ".self::money($this->selectedProducer->balance).'.';
    }

    /**
     * Balance preview shown in the payment modal while the amount is typed.
     */
    #[Computed]
    public function balanceAfterPayment(): ?float
    {
        $amount = self::normalizeAmount($this->paymentAmount);

        if (! $this->selectedProducer || ! is_numeric($amount)) {
            return null;
        }

        return round((float) $this->selectedProducer->balance - (float) $amount, 2);
    }

    #[Computed]
    public function producers(): Collection
    {
        $term = trim($this->search);

        return Producer::query()
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('business_name', 'like', "%{$term}%")
                ->orWhere('cuit', 'like', "%{$term}%")))
            ->withCount(['sales as unpaid_count' => fn ($query) => $query
                ->where('type', 'remito')
                ->whereIn('status', self::UNPAID_STATUSES)])
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
            ->where('type', 'remito')
            ->whereIn('status', self::UNPAID_STATUSES)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function recentPayments(): Collection
    {
        return $this->selectedProducer?->payments()->latest()->latest('id')->limit(5)->get() ?? collect();
    }

    /**
     * WhatsApp link with the balance and unpaid remitos, addressed to the producer's phone when available.
     */
    #[Computed]
    public function whatsappUrl(): ?string
    {
        $producer = $this->selectedProducer;

        if (! $producer) {
            return null;
        }

        $lines = [
            "Hola {$producer->name}, le escribimos de la veterinaria.",
            'Su saldo en cuenta corriente al '.today()->format('d/m/Y').' es de *'.self::money($producer->balance).'*.',
        ];

        if ($this->unpaidSales->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Remitos impagos:';

            foreach ($this->unpaidSales as $sale) {
                $due = (float) $sale->total - (float) $sale->paid;
                $line = "• {$sale->voucher_number} del {$sale->created_at->format('d/m/Y')}: ".self::money($due);

                if ($sale->status === 'parcial') {
                    $line .= ' (pendiente de un total de '.self::money($sale->total).')';
                }

                $lines[] = $line;
            }
        }

        $lines[] = '';
        $lines[] = 'Quedamos a disposición por cualquier consulta. ¡Muchas gracias!';

        return 'https://wa.me/'.($this->whatsappPhone($producer->phone) ?? '').'?text='.rawurlencode(implode("\n", $lines));
    }

    public function render()
    {
        return view('livewire.erp.accounts-receivable', [
            'methods' => self::PAYMENT_METHODS,
        ]);
    }

    /**
     * Normalize an Argentine phone number to the international format WhatsApp expects (549 + area + number).
     */
    protected function whatsappPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '54') ? $digits : '549'.ltrim($digits, '0');
    }

    /**
     * Accept "1500.50" as well as the local "1.500,50" and "25.000" notations.
     */
    protected static function normalizeAmount(string $amount): string
    {
        $amount = str_replace([' ', '$'], '', trim($amount));

        return str_contains($amount, ',') || preg_match('/^\d{1,3}(\.\d{3})+$/', $amount)
            ? str_replace(',', '.', str_replace('.', '', $amount))
            : $amount;
    }

    protected static function money(mixed $amount): string
    {
        return '$ '.number_format((float) $amount, 2, ',', '.');
    }
}
