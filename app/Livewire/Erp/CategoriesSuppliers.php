<?php

namespace App\Livewire\Erp;

use App\Models\Category;
use App\Models\StockMovement;
use App\Models\Supplier;
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

    public string $categoryIcon = 'other';

    #[Locked]
    public ?int $supplierId = null;

    public string $businessName = '';

    public string $cuit = '';

    public string $phone = '';

    public string $email = '';

    public function saveCategory(): void
    {
        $this->categoryName = trim($this->categoryName);

        $this->validate([
            'categoryName' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->categoryId)],
            'categoryIcon' => ['required', Rule::in(array_keys(Category::ICONS))],
        ], [
            'categoryName.required' => 'Ingresá el nombre del rubro.',
            'categoryName.max' => 'El nombre no puede superar los :max caracteres.',
            'categoryName.unique' => 'Ya existe un rubro con ese nombre.',
        ]);

        if ($this->categoryId) {
            Category::findOrFail($this->categoryId)->update(['name' => $this->categoryName, 'icon' => $this->categoryIcon]);
            $message = 'Rubro actualizado.';
        } else {
            Category::create(['name' => $this->categoryName, 'slug' => $this->uniqueSlug($this->categoryName), 'icon' => $this->categoryIcon]);
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
        $this->categoryIcon = array_key_exists($category->icon, Category::ICONS) ? $category->icon : 'other';
    }

    public function cancelCategory(): void
    {
        $this->reset('categoryId', 'categoryName', 'categoryIcon');
        $this->resetValidation(['categoryName', 'categoryIcon']);
    }

    public function deleteCategory(int $id): void
    {
        $category = Category::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            $this->dispatch('toast', message: "No se puede eliminar «{$category->name}»: tiene {$category->products_count} productos. Cambiales el rubro primero.", type: 'error');

            return;
        }

        $category->delete();

        if ($this->categoryId === $id) {
            $this->cancelCategory();
        }

        unset($this->categories);
        $this->dispatch('toast', message: 'Rubro eliminado.');
    }

    public function saveSupplier(): void
    {
        $this->businessName = trim($this->businessName);
        $this->phone = trim($this->phone);
        $this->email = Str::lower(trim($this->email));
        $this->cuit = $this->normalizeCuit($this->cuit);

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

        $data = [
            'business_name' => $this->businessName,
            'cuit' => $this->cuit ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
        ];

        if ($this->supplierId) {
            Supplier::findOrFail($this->supplierId)->update($data);
            $message = 'Proveedor actualizado.';
        } else {
            Supplier::create($data);
            $message = 'Proveedor creado.';
        }

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

    public function deleteSupplier(int $id): void
    {
        $supplier = Supplier::withCount('products')->findOrFail($id);

        if ($supplier->products_count > 0) {
            $this->dispatch('toast', message: "No se puede eliminar «{$supplier->business_name}»: tiene {$supplier->products_count} productos asignados.", type: 'error');

            return;
        }

        if (StockMovement::where('supplier_id', $supplier->id)->exists()) {
            $this->dispatch('toast', message: "No se puede eliminar «{$supplier->business_name}»: figura en el historial de reposiciones.", type: 'error');

            return;
        }

        $supplier->delete();

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

    /**
     * Accepts "30712345671" or "30-71234567-1" and returns the dashed form.
     */
    protected function normalizeCuit(string $cuit): string
    {
        $digits = preg_replace('/\D/', '', $cuit);

        if (strlen($digits) === 11) {
            return substr($digits, 0, 2).'-'.substr($digits, 2, 8).'-'.substr($digits, 10);
        }

        return trim($cuit);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = substr(Str::slug($name), 0, 95) ?: 'rubro';
        $slug = $base;

        for ($i = 2; Category::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.erp.categories-suppliers');
    }
}
