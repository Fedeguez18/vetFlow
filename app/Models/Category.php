<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'icon'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * Badge colors for the inventory view. The key is what gets stored in categories.icon.
     */
    public const COLORS = [
        'vaccine' => 'Verde',
        'pharmacy' => 'Celeste',
        'nutrition' => 'Naranja',
        'identification' => 'Violeta',
        'instrument' => 'Turquesa',
        'accessory' => 'Rosa',
        'other' => 'Gris',
    ];

    /**
     * Get the products in the category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
