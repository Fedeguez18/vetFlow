<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\Pricing;
use App\Support\Quantity;
use Illuminate\Database\Seeder;

/**
 * Dog food: each food is a closed bag (sold per unit) linked to its loose product (sold by weight, price
 * per kg). The loose one has no stock: selling it opens a bag and the bag's stock goes down. Safe to run on a loaded database:
 * products whose SKU already exists are skipped.
 *
 * php artisan db:seed --class=DogFoodSeeder
 */
class DogFoodSeeder extends Seeder
{
    /**
     * name, SKU stem, kilos per bag, bag cost (s/IVA), closed bags in stock.
     * The loose product has no stock of its own: selling it opens these bags.
     *
     * @var list<array{0: string, 1: string, 2: int|float, 3: int, 4: int}>
     */
    public const FOODS = [
        ['Dog Chow Adulto Raza Mediana y Grande', 'DCH-ADU', 21, 52000, 6],
        ['Dog Chow Cachorro', 'DCH-CAC', 15, 44000, 4],
        ['Pedigree Adulto Carne y Vegetales', 'PED-ADU', 21, 47000, 5],
        ['Old Prince Adulto Cordero y Arroz', 'OLP-ADU', 15, 58000, 3],
        ['Vital Can Balanced Adulto', 'VTC-ADU', 20, 61000, 3],
        ['Sieger Adulto', 'SIE-ADU', 15, 50000, 2],
        ['Agility Gold Cachorro', 'AGI-CAC', 15, 56000, 2],
        ['Excellent Adulto Raza Mediana', 'EXC-ADU', 15, 64000, 2],
    ];

    /**
     * Bags carry the usual markup; loose food sells a bit higher per kg to cover the handling.
     */
    public const BAG_MARKUP = 30;

    public const LOOSE_MARKUP = 45;

    public const VAT = 10.5;

    public function run(ProductService $products): void
    {
        $category = Category::firstOrCreate(['slug' => 'alimentos'], ['name' => 'Alimentos', 'icon' => 'nutrition']);

        foreach (self::FOODS as [$name, $stem, $bagKilos, $bagCost, $bags]) {
            if (Product::where('sku', "{$stem}-SUE")->orWhere('sku', "{$stem}-{$bagKilos}K")->exists()) {
                continue;
            }

            $costPerKg = round($bagCost / $bagKilos, 2);
            $loosePrice = Pricing::finalPrice($costPerKg, self::LOOSE_MARKUP, self::VAT, 10);
            $bagPrice = Pricing::finalPrice($bagCost, self::BAG_MARKUP, self::VAT, 100);

            $loose = $products->create([
                'sku' => "{$stem}-SUE",
                'name' => "{$name} — suelto x kg",
                'description' => 'Alimento balanceado para perros, suelto por peso',
                'category_id' => $category->id,
                'cost_price' => $costPerKg,
                'markup_percent' => Pricing::markupFromFinal($costPerKg, self::VAT, $loosePrice),
                'vat_percent' => self::VAT,
                'price' => $loosePrice,
                'tracks_expiry' => false,
                'sold_by_weight' => true,
                'min_stock' => 0,
                'is_active' => true,
            ]);

            $products->create([
                'sku' => "{$stem}-{$bagKilos}K",
                'name' => "{$name} x {$bagKilos} kg",
                'description' => 'Alimento balanceado para perros, bolsa cerrada',
                'category_id' => $category->id,
                'cost_price' => $bagCost,
                'markup_percent' => Pricing::markupFromFinal($bagCost, self::VAT, $bagPrice),
                'vat_percent' => self::VAT,
                'price' => $bagPrice,
                'tracks_expiry' => false,
                'bulk_product_id' => $loose->id,
                'bag_weight_grams' => (int) round($bagKilos * Quantity::GRAMS_PER_KG),
                'min_stock' => 2,
                'is_active' => true,
            ], initialStock: $bags);
        }
    }
}
