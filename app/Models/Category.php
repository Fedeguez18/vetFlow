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
     * Icon keys understood by the POS and inventory views, with their label.
     */
    public const ICONS = [
        'vaccine' => 'Vacuna',
        'pharmacy' => 'Farmacia',
        'nutrition' => 'Nutrición',
        'identification' => 'Identificación',
        'instrument' => 'Instrumental',
        'accessory' => 'Accesorio',
        'other' => 'Otro',
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
