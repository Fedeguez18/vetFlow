<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'vacunas' => ['Biológicos/Vacunas', 'vaccine'],
        'farmacia' => ['Farmacia', 'pharmacy'],
        'nutricion' => ['Nutrición & Sales', 'nutrition'],
        'identificacion' => ['Identificación', 'identification'],
        'instrumental' => ['Instrumental', 'instrument'],
        'accesorios' => ['Accesorios', 'accessory'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $slug => [$name, $icon]) {
            Category::create(['name' => $name, 'slug' => $slug, 'icon' => $icon]);
        }
    }
}
