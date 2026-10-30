<?php

namespace App\Livewire;

use App\Exceptions\StockException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\StockService;
use App\Support\Pricing;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Reponer": manual stock entry, by lot when the product tracks expiry, with an optional new purchase cost.
 */
class RestockModal extends Component
{
    use RequiresAdmin;

    public ?int $productId = null;

    public bool $open = false;

    public string $quantity = '';

    public string $lotNumber = '';

    public string $expirationDate = '';

    public string $newCost = '';

    public ?int $supplierId = null;

    public string $reason = '';

    #[On('product-restock')]
    public function start(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $this->reset('quantity', 'lotNumber', 'expirationDate', 'newCost', 'reason');
        $this->resetValidation();
        $this->productId = $product->id;
        $this->supplierId = $product->supplier?->is_active ? $product->supplier_id : null;
        unset($this->product, $this->existingLots);
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetValidation();
    }

    /**
     * Picking an existing lot number pre-fills its expiration date (the units are added to that lot).
     */
    public function updatedLotNumber(): void
    {
        $lot = $this->existingLots->first(fn ($lot) => strcasecmp($lot->lot_number, trim($this->lotNumber)) === 0);

        if ($lot) {
            $this->expirationDate = $lot->expiration_date->toDateString();
        }
    }

    public function save(StockService $stock): void
    {
        $product = $this->product;
        $this->newCost = trim($this->newCost);
        $cost = $this->newCost !== '' ? Pricing::parse($this->newCost) : null;

        $this->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'lotNumber' => [$product->tracks_expiry ? 'required' : 'nullable', 'string', 'max:60'],
            'expirationDate' => [$product->tracks_expiry ? 'required' : 'nullable', 'date'],
            'newCost' => ['nullable', function ($attribute, $value, $fail) use ($cost) {
                if ($value !== '' && ($cost === null || $cost < 0)) {
                    $fail('El precio de compra tiene que ser un número mayor o igual a cero.');
                }
            }],
            'supplierId' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'quantity.required' => 'Ingresá la cantidad.',
            'quantity.integer' => 'Tiene que ser un número entero.',
            'quantity.min' => 'Tiene que ser mayor a cero.',
            'lotNumber.required' => 'El número de lote es obligatorio para este producto.',
            'expirationDate.required' => 'La fecha de vencimiento es obligatoria para este producto.',
            'expirationDate.date' => 'Fecha inválida.',
            'supplierId.exists' => 'Ese proveedor está dado de baja.',
        ]);

        try {
            $stock->restock(
                $product,
                (int) $this->quantity,
                strtoupper(trim($this->lotNumber)) ?: null,
                $this->expirationDate ?: null,
                $this->supplierId,
                trim($this->reason) ?: null,
                auth()->id(),
                $cost,
            );
        } catch (StockException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->open = false;
        $this->dispatch('stock-updated');
        $this->dispatch('toast', message: "Se ingresaron {$this->quantity} unidades de {$product->name}");
    }

    #[Computed]
    public function product(): ?Product
    {
        return $this->productId ? Product::with('supplier')->find($this->productId) : null;
    }

    #[Computed]
    public function existingLots(): Collection
    {
        return $this->product?->lots()->orderBy('expiration_date')->get() ?? collect();
    }

    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::active()->orderBy('business_name')->get(['id', 'business_name']);
    }

    /**
     * Price preview when a new purchase cost is typed: same markup and VAT, new final price.
     *
     * @return array{old: float, new: float}|null
     */
    #[Computed]
    public function pricePreview(): ?array
    {
        $cost = trim($this->newCost) !== '' ? Pricing::parse($this->newCost) : null;
        $product = $this->product;

        if ($cost === null || $cost < 0 || ! $product) {
            return null;
        }

        return [
            'old' => (float) $product->price,
            'new' => Pricing::finalPrice($cost, (float) $product->markup_percent, (float) $product->vat_percent),
        ];
    }

    public function render()
    {
        return view('livewire.restock-modal');
    }
}
