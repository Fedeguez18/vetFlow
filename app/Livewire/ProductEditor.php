<?php

namespace App\Livewire;

use App\Exceptions\StockException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Livewire\Forms\ProductForm;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductService;
use App\Services\StockService;
use App\Support\Pricing;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal to create and edit products; in edit mode also shows lots, movements, price history
 * and the stock adjustment form.
 */
class ProductEditor extends Component
{
    use RequiresAdmin;

    public const TABS = [
        'datos' => 'Datos',
        'lotes' => 'Lotes',
        'movimientos' => 'Movimientos',
        'precios' => 'Historial de precios',
        'ajuste' => 'Ajuste de stock',
    ];

    public ProductForm $form;

    public bool $open = false;

    public string $tab = 'datos';

    public ?int $adjustLotId = null;

    public string $adjustMode = 'subtract';

    public string $adjustQuantity = '';

    public string $adjustReason = '';

    public string $adjustNote = '';

    /**
     * Shown when a scanned barcode already belonged to a product and that product was opened instead.
     */
    public ?string $notice = null;

    #[On('product-create')]
    public function create(): void
    {
        $this->form->reset();
        $this->form->product = null;
        $this->resetAdjustment();
        $this->resetValidation();
        $this->notice = null;
        $this->tab = 'datos';
        $this->open = true;
    }

    #[On('product-edit')]
    public function edit(int $productId): void
    {
        $this->form->reset();
        $this->form->setProduct(Product::findOrFail($productId));
        $this->resetAdjustment();
        $this->resetValidation();
        $this->notice = null;
        $this->tab = 'datos';
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->notice = null;
        $this->resetValidation();
    }

    /**
     * Live, bidirectional price calculation.
     */
    public function updated(string $property): void
    {
        match ($property) {
            'form.cost_price', 'form.markup_percent', 'form.vat_percent', 'form.round', 'form.round_to' => $this->form->recalculatePrice(),
            'form.price' => $this->form->recalculateMarkup(),
            default => null,
        };

        if ($property === 'form.tracks_expiry' && $this->form->tracks_expiry && $this->form->lots === []) {
            $this->form->addLot();
        }

        if ($property === 'form.barcode' && ! $this->form->isEditing()) {
            $this->openExistingBarcode();
        }
    }

    /**
     * When creating, a barcode that is already loaded opens that product (even if it was given a baja)
     * instead of letting the whole form be filled to fail on save. An unknown barcode changes nothing.
     */
    protected function openExistingBarcode(): void
    {
        $barcode = trim($this->form->barcode);

        if ($barcode === '') {
            return;
        }

        $product = Product::query()->where('barcode', $barcode)->first();

        if (! $product) {
            return;
        }

        $this->edit($product->id);
        $this->notice = $product->is_active
            ? "El código {$barcode} ya estaba cargado: se abrió {$product->name} para editarlo."
            : "El código {$barcode} ya estaba cargado en {$product->name}, que está dado de baja. Marcá \"Artículo activo\" y guardá para volver a venderlo.";
    }

    public function addLot(): void
    {
        $this->form->addLot();
    }

    public function removeLot(int $index): void
    {
        $this->form->removeLot($index);
    }

    public function save(ProductService $products): void
    {
        $this->form->normalize();
        $this->form->validate();

        $editing = $this->form->isEditing();

        if ($editing) {
            try {
                $product = $products->update($this->form->product, $this->form->attributes(), auth()->id());
            } catch (StockException $e) {
                $this->addError('form.tracks_expiry', $e->getMessage());

                return;
            }
        } else {
            $product = $products->create(
                $this->form->attributes(),
                $this->form->tracks_expiry ? $this->form->lots : [],
                $this->form->tracks_expiry ? 0 : (int) $this->form->initial_stock,
                auth()->id(),
            );
        }

        $this->open = false;
        $this->dispatch('product-saved');
        $this->dispatch('toast', message: ($editing ? 'Se actualizó ' : 'Se creó ').$product->name.'.');
    }

    /**
     * Subtract or correct stock with a mandatory reason (traceable movement).
     */
    public function adjust(StockService $stock): void
    {
        $product = $this->form->product;

        if (! $product) {
            return;
        }

        $this->validate([
            'adjustMode' => ['required', Rule::in(['subtract', 'set'])],
            'adjustQuantity' => ['required', 'integer', $this->adjustMode === 'subtract' ? 'min:1' : 'min:0'],
            'adjustReason' => ['required', Rule::in(array_keys(StockService::ADJUSTMENT_REASONS))],
            'adjustLotId' => $product->tracks_expiry ? ['required', 'integer'] : ['nullable'],
            'adjustNote' => [$this->adjustReason === 'otro' ? 'required' : 'nullable', 'string', 'max:255'],
        ], [
            'adjustQuantity.required' => 'Ingresá la cantidad.',
            'adjustQuantity.integer' => 'Tiene que ser un número entero.',
            'adjustQuantity.min' => $this->adjustMode === 'subtract' ? 'Tiene que ser mayor a cero.' : 'No puede ser negativo.',
            'adjustReason.required' => 'El motivo es obligatorio.',
            'adjustReason.in' => 'El motivo es obligatorio.',
            'adjustLotId.required' => 'Elegí el lote.',
            'adjustNote.required' => 'Detallá el motivo.',
        ]);

        try {
            $movement = $stock->adjust($product, $this->adjustMode, (int) $this->adjustQuantity, $this->adjustReason, $this->adjustLotId, $this->adjustNote, auth()->id());
        } catch (StockException $e) {
            $this->addError('adjustQuantity', $e->getMessage());

            return;
        }

        $this->form->product->refresh();
        $this->resetAdjustment();
        unset($this->lots, $this->movements);
        $this->tab = 'movimientos';

        $this->dispatch('stock-updated');
        $this->dispatch('toast', message: 'Ajuste registrado: '.($movement->quantity > 0 ? '+' : '').$movement->quantity.' unidades de '.$product->name.'.');
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::orderBy('business_name')->get(['id', 'business_name']);
    }

    /**
     * @return array{cost: float, profit: float, vat: float, final: float}|null
     */
    #[Computed]
    public function breakdown(): ?array
    {
        $cost = Pricing::parse($this->form->cost_price);
        $vat = Pricing::parse($this->form->vat_percent);
        $final = Pricing::parse($this->form->price);

        return $cost === null || $vat === null || $final === null ? null : Pricing::breakdown($cost, $vat, $final);
    }

    #[Computed]
    public function belowCost(): bool
    {
        $cost = Pricing::parse($this->form->cost_price);
        $final = Pricing::parse($this->form->price);

        return $cost !== null && $final !== null && $final < $cost;
    }

    #[Computed]
    public function lots(): Collection
    {
        return $this->form->product?->lots()->orderBy('expiration_date')->get() ?? collect();
    }

    #[Computed]
    public function movements(): Collection
    {
        return $this->form->product?->stockMovements()->with(['lot', 'user', 'supplier'])->latest()->latest('id')->limit(15)->get() ?? collect();
    }

    #[Computed]
    public function priceHistory(): Collection
    {
        return $this->form->product?->priceHistories()->with('user')->latest()->latest('id')->limit(15)->get() ?? collect();
    }

    public function render()
    {
        return view('livewire.product-editor', [
            'reasons' => StockService::ADJUSTMENT_REASONS,
        ]);
    }

    protected function resetAdjustment(): void
    {
        $this->reset('adjustLotId', 'adjustMode', 'adjustQuantity', 'adjustReason', 'adjustNote');
    }
}
