<?php

namespace App\Livewire\Erp;

use App\Exceptions\CatalogException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::erp')]
#[Title('Rubros')]
class Categories extends Component
{
    use RequiresAdmin;

    #[Locked]
    public ?int $categoryId = null;

    public string $categoryName = '';

    public string $categoryColor = 'other';

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

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return Category::withCount('products')->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.erp.categories');
    }
}
