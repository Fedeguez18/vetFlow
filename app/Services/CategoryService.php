<?php

namespace App\Services;

use App\Exceptions\CatalogException;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    /**
     * Create a category with a unique slug derived from its name.
     */
    public function create(string $name, string $color): Category
    {
        return Category::create(['name' => $name, 'slug' => $this->uniqueSlug($name), 'icon' => $color]);
    }

    /**
     * Rename or recolor a category. The slug is kept so existing links and filters don't change.
     */
    public function update(Category $category, string $name, string $color): Category
    {
        $category->update(['name' => $name, 'icon' => $color]);

        return $category;
    }

    /**
     * @throws CatalogException when products still use the category.
     */
    public function delete(Category $category): void
    {
        $products = $category->products()->count();

        if ($products > 0) {
            throw new CatalogException("No se puede eliminar «{$category->name}»: tiene {$products} productos. Cambiales el rubro primero.");
        }

        $category->delete();
    }

    /**
     * "Farmacia General" → "farmacia-general", or "farmacia-general-2" when taken.
     */
    public function uniqueSlug(string $name): string
    {
        $base = substr(Str::slug($name), 0, 95) ?: 'rubro';
        $slug = $base;

        for ($i = 2; Category::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
