<?php

namespace App\Livewire\Erp;

use App\Exceptions\CatalogException;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\CategoryService;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Rubros & Proveedores')]
class CategoriesSuppliers extends Component
{
    #[Locked]
    public ?int $categoryId = null;

    public string $categoryName = '';

    public string $categoryColor = 'other';

    #[Locked]
    public ?int $supplierId = null;

    public string $businessName = '';

    public string $cuit = '';

    public string $phone = '';

    public string $email = '';

    public function saveCategory(CategoryService $categories): void
    {
        $this->categoryName = trim($this->categoryName);

        $this->validate([
            'categoryName' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->categoryId)],
            'categoryColor' => ['required', Rule::in(array_keys(Category::COLORS))],
        ], [
            'categoryName.required' => 'Ingresá el nombre del rubro.',
            'categoryName.max' => 'El nombre no puede superar los :max caracteres.',
            'categoryName.unique' => 'Ya existe un rubro con ese nombre.',
        ]);

        if ($this->categoryId) {
            $categories->update(Category::findOrFail($this->categoryId), $this->categoryName, $this->categoryColor);
            $message = 'Rubro actualizado.';
        } else {
            $categories->create($this->categoryName, $this->categoryColor);
            $message = 'Rubro creado.';
        }

        $this->cancelCategory();
        unset($this->categories);
        $this->dispatch('toast', message: $message);
    }

    public function editCategory(int $id): void
    {
        $category = Category::findOrFail($id);

        $this->resetValidation();
        $this->categoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categoryColor = array_key_exists($category->icon, Category::COLORS) ? $category->icon : 'other';
    }

    public function cancelCategory(): void
    {
        $this->reset('categoryId', 'categoryName', 'categoryColor');
        $this->resetValidation(['categoryName', 'categoryColor']);
    }

    public function deleteCategory(int $id, CategoryService $categories): void
    {
        try {
            $categories->delete(Category::findOrFail($id));
        } catch (CatalogException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($this->categoryId === $id) {
            $this->cancelCategory();
        }

        unset($this->categories);
        $this->dispatch('toast', message: 'Rubro eliminado.');
    }

    public function saveSupplier(SupplierService $suppliers): void
    {
        $this->businessName = trim($this->businessName);
        $this->phone = trim($this->phone);
        $this->email = Str::lower(trim($this->email));
        $this->cuit = $suppliers->normalizeCuit($this->cuit);

        $this->validate([
            'businessName' => ['required', 'string', 'max:255'],
            'cuit' => ['nullable', 'regex:/^\d{2}-\d{8}-\d$/', Rule::unique('suppliers', 'cuit')->ignore($this->supplierId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [
            'businessName.required' => 'Ingresá la razón social.',
            'cuit.regex' => 'El CUIT debe tener 11 dígitos (ej.: 30-71234567-1).',
            'cuit.unique' => 'Ya existe un proveedor con ese CUIT.',
            'phone.max' => 'El teléfono no puede superar los :max caracteres.',
            'email.email' => 'El email no es válido.',
        ]);

        $suppliers->save([
            'business_name' => $this->businessName,
            'cuit' => $this->cuit ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
        ], $this->supplierId ? Supplier::findOrFail($this->supplierId) : null);

        $message = $this->supplierId ? 'Proveedor actualizado.' : 'Proveedor creado.';

        $this->cancelSupplier();
        unset($this->suppliers);
        $this->dispatch('toast', message: $message);
    }

    public function editSupplier(int $id): void
    {
        $supplier = Supplier::findOrFail($id);

        $this->resetValidation();
        $this->supplierId = $supplier->id;
        $this->businessName = $supplier->business_name;
        $this->cuit = (string) $supplier->cuit;
        $this->phone = (string) $supplier->phone;
        $this->email = (string) $supplier->email;
    }

    public function cancelSupplier(): void
    {
        $this->reset('supplierId', 'businessName', 'cuit', 'phone', 'email');
        $this->resetValidation(['businessName', 'cuit', 'phone', 'email']);
    }

    public function deleteSupplier(int $id, SupplierService $suppliers): void
    {
        try {
            $suppliers->delete(Supplier::findOrFail($id));
        } catch (CatalogException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($this->supplierId === $id) {
            $this->cancelSupplier();
        }

        unset($this->suppliers);
        $this->dispatch('toast', message: 'Proveedor eliminado.');
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::withCount('products')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::withCount('products')->orderBy('business_name')->get();
    }

    public function render()
    {
        return view('livewire.erp.categories-suppliers');
    }
}
