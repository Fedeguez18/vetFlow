<?php

namespace App\Livewire\Erp;

use App\Exceptions\CatalogException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Livewire\Forms\SupplierForm;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::erp')]
#[Title('Proveedores')]
class Suppliers extends Component
{
    use RequiresAdmin, WithPagination;

    public const PER_PAGE = 15;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'inactivos', except: false)]
    public bool $showInactive = false;

    public SupplierForm $form;

    /**
     * "Editar datos" in the supplier detail links here with ?editar={id}.
     */
    public function mount(): void
    {
        $id = request()->integer('editar');

        if ($id && Supplier::whereKey($id)->exists()) {
            $this->editSupplier($id);
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'showInactive'], true)) {
            $this->resetPage();
        }
    }

    public function saveSupplier(SupplierService $suppliers): void
    {
        $this->form->normalize($suppliers);
        $this->form->validate();

        $editing = $this->form->isEditing();
        $suppliers->save($this->form->attributes(), $this->form->supplier);

        $this->cancelSupplier();
        unset($this->suppliers);
        $this->dispatch('toast', message: $editing ? 'Proveedor actualizado.' : 'Proveedor creado.');
    }

    public function editSupplier(int $id): void
    {
        $this->resetValidation();
        $this->form->setSupplier(Supplier::findOrFail($id));
    }

    public function cancelSupplier(): void
    {
        $this->form->reset();
        $this->resetValidation();
    }

    public function deleteSupplier(int $id, SupplierService $suppliers): void
    {
        try {
            $suppliers->delete(Supplier::findOrFail($id));
        } catch (CatalogException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($this->form->supplier?->id === $id) {
            $this->cancelSupplier();
        }

        unset($this->suppliers);
        $this->dispatch('toast', message: 'Proveedor eliminado.');
    }

    /**
     * Baja lógica: the supplier keeps its products and restock history but is no longer offered
     * when creating or editing products or restocking.
     */
    public function deactivateSupplier(int $id, SupplierService $suppliers): void
    {
        $supplier = Supplier::findOrFail($id);
        $suppliers->setActive($supplier, false);

        unset($this->suppliers);
        $this->dispatch('toast', message: "{$supplier->business_name} dado de baja.");
    }

    public function reactivateSupplier(int $id, SupplierService $suppliers): void
    {
        $supplier = Supplier::findOrFail($id);
        $suppliers->setActive($supplier, true);

        unset($this->suppliers);
        $this->dispatch('toast', message: "{$supplier->business_name} reactivado.");
    }

    /**
     * Suppliers matching the search by business name or CUIT (with or without dashes), with their
     * product count, active products with low or critical stock and the date of the last restock.
     *
     * @return LengthAwarePaginator<int, Supplier>
     */
    #[Computed]
    public function suppliers(): LengthAwarePaginator
    {
        $term = trim($this->search);
        $digits = preg_replace('/\D/', '', $term);

        return Supplier::query()
            ->withCount([
                'products',
                'products as low_stock_count' => fn (Builder $query) => $query->where('is_active', true)->lowStock(),
            ])
            ->withMax('restocks as last_restock_at', 'created_at')
            ->withCasts(['last_restock_at' => 'datetime'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('business_name', "%{$term}%")
                ->orWhereLike('cuit', "%{$term}%")
                ->when($digits !== '', fn (Builder $query) => $query->orWhereRaw("replace(cuit, '-', '') like ?", ["%{$digits}%"]))))
            ->when(! $this->showInactive, fn (Builder $query) => $query->active())
            ->orderByDesc('is_active')
            ->orderBy('business_name')
            ->paginate(self::PER_PAGE);
    }

    public function render()
    {
        return view('livewire.erp.suppliers');
    }
}
