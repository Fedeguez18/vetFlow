<?php

namespace Database\Factories;

use App\Models\Product;
use App\Support\Pricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected const DRUGS = [
        'Enrofloxacina 10%', 'Florfenicol 30%', 'Doramectina 1%', 'Levamisol 22%', 'Albendazol 15%', 'Closantel 10%',
        'Penicilina Estreptomicina', 'Meloxicam 2%', 'Dexametasona 0,2%', 'Oxitocina 10 UI', 'Vitamina B12',
        'Gentamicina 10%', 'Tilosina 20%', 'Cipermetrina 5%', 'Fipronil 1%', 'Moxidectina 1%', 'Ketoprofeno 10%',
        'Selenio + Vitamina E', 'Cobre Inyectable', 'Fósforo Orgánico', 'Ceftiofur 5%', 'Amoxicilina L.A.',
        'Sulfa Trimetoprima', 'Hierro Dextrano', 'Ácido Tolfenámico', 'Toltrazuril 5%', 'Praziquantel Equino',
    ];

    protected const PRESENTATIONS = [
        'Frasco 50 ml', 'Frasco 100 ml', 'Frasco 250 ml', 'Frasco 500 ml', 'Bidón 1 L', 'Bidón 5 L', 'Pomo x 20 g', 'Sobre x 100 g',
    ];

    protected const DESCRIPTIONS = [
        'Uso bovino', 'Uso bovino y ovino', 'Uso equino', 'Pequeños animales', 'Inyectable subcutáneo',
        'Inyectable intramuscular', 'Pour-on', 'Uso oral', 'Amplio espectro', 'Larga acción',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomElement([850, 1200, 1900, 2600, 3400, 4800, 6200, 7900, 9800, 12500, 16400, 21000]);
        $markup = fake()->randomElement([25, 30, 35, 40, 45, 50, 60]);
        $vat = fake()->randomElement([21, 21, 10.5, 10.5, 0]);
        $stock = fake()->numberBetween(12, 140);

        return [
            'sku' => strtoupper(fake()->unique()->bothify('VET-???-####')),
            'barcode' => fake()->unique()->ean13(),
            'name' => fake()->randomElement(self::DRUGS).' '.fake()->randomElement(self::PRESENTATIONS),
            'description' => fake()->randomElement(self::DESCRIPTIONS),
            'cost_price' => $cost,
            'markup_percent' => $markup,
            'vat_percent' => $vat,
            'price' => Pricing::finalPrice($cost, $markup, $vat),
            'tracks_expiry' => false,
            'stock' => $stock,
            'min_stock' => fake()->numberBetween(3, intdiv($stock, 2)),
            'is_active' => true,
        ];
    }

    /**
     * Product whose stock is handled by lots with expiration dates.
     */
    public function tracksExpiry(): static
    {
        return $this->state(fn () => ['tracks_expiry' => true, 'stock' => 0]);
    }
}
