<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Pricing;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Total products in the catalog (hand-written + factory).
     */
    public const TOTAL = 128;

    /**
     * Hand-written catalog. "lots" (lot number, days until expiration, quantity) is read by LotSeeder;
     * products without lots carry their stock directly.
     *
     * Required cases: stock <= 5 (Pipeta, Mancha y Gangrena, Bloque Salino, Lápiz) and 0 (Jeringa);
     * under the minimum but > 5 (Albendazol, Flunixin, Pinza, Guantes); no barcode (Pelota, Lápiz);
     * lots expiring < 30 days (Anticarbunclosa), 30–60 days (Ivermectina, Oxitetraciclina), expired (ADE).
     */
    public const CATALOG = [
        ['sku' => 'VET-VAC-850', 'barcode' => '7798101000011', 'name' => 'Vacuna Anticarbunclosa Bovina', 'description' => 'Frasco 50 dosis — control preventivo', 'category' => 'vacunas', 'supplier' => 0, 'cost' => 19500, 'markup' => 40, 'vat' => 10.5, 'min' => 10, 'lots' => [['L-2409A', 20, 10], ['L-2503C', 210, 14]]],
        ['sku' => 'IVM-315-500', 'barcode' => '779810101', 'name' => 'Ivermectina 3,15% Frasco 500 ml', 'description' => 'Antiparasitario de amplio espectro — inyectable', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 11000, 'markup' => 50, 'vat' => 10.5, 'min' => 10, 'lots' => [['IV-1187', 45, 12]]],
        ['sku' => 'SAL-BOV-25K', 'barcode' => '7798101000035', 'name' => 'Sales Minerales Bovinas x 25 kg', 'description' => 'Suplemento mineral de engorde — bolsa', 'category' => 'nutricion', 'supplier' => 3, 'cost' => 5800, 'markup' => 45, 'vat' => 10.5, 'min' => 10, 'stock' => 31],
        ['sku' => 'CAR-SEN-100', 'barcode' => '7798101000042', 'name' => 'Caravanas Oficiales Senasa x 100', 'description' => 'Bolsa x 100 — tarjeta y botón', 'category' => 'identificacion', 'supplier' => 2, 'cost' => 11400, 'markup' => 30, 'vat' => 0, 'min' => 10, 'stock' => 31],
        ['sku' => 'OXI-LA-200', 'barcode' => '7798101000059', 'name' => 'Oxitetraciclina L.A. 200 ml', 'description' => 'Antibiótico inyectable de larga acción', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 6900, 'markup' => 45, 'vat' => 10.5, 'min' => 12, 'lots' => [['OX-5521', 50, 48]]],
        ['sku' => 'VIT-ADE-500', 'barcode' => '7798101000066', 'name' => 'Complejo Vitamínico ADE 500 ml', 'description' => 'Suplemento vitamínico inyectable', 'category' => 'nutricion', 'supplier' => 0, 'cost' => 5100, 'markup' => 50, 'vat' => 10.5, 'min' => 8, 'lots' => [['ADE-0931', -10, 3], ['ADE-1204', 230, 6]]],
        ['sku' => 'AGU-18G-100', 'barcode' => '7798101000073', 'name' => 'Aguja Descartable 18G x 1½ x 100', 'description' => 'Caja x 100 unidades', 'category' => 'instrumental', 'supplier' => 4, 'cost' => 2700, 'markup' => 30, 'vat' => 21, 'min' => 20, 'stock' => 67],
        ['sku' => 'PIP-CAN-20', 'barcode' => '7798101000080', 'name' => 'Pipeta Canina 20 a 40 kg', 'description' => 'Antipulgas y garrapatas — pequeños animales', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 5200, 'markup' => 60, 'vat' => 21, 'min' => 6, 'lots' => [['PC-7710', 410, 3]]],
        ['sku' => 'ACC-BAL-XL', 'barcode' => null, 'name' => 'Pelota Resistente XL', 'description' => 'Caucho macizo — perros grandes', 'category' => 'accesorios', 'supplier' => 4, 'cost' => 6800, 'markup' => 70, 'vat' => 21, 'min' => 10, 'stock' => 82],
        ['sku' => 'FAR-GOT-10', 'barcode' => '7798101000097', 'name' => 'Gotas Oculares Antibióticas', 'description' => 'Gotero 10 ml — queratoconjuntivitis', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 3400, 'markup' => 50, 'vat' => 10.5, 'min' => 10, 'lots' => [['GO-2231', 480, 40]]],
        ['sku' => 'VAC-AFT-050', 'barcode' => '7798101000103', 'name' => 'Vacuna Antiaftosa Bivalente x 50 dosis', 'description' => 'Oleosa — campaña oficial', 'category' => 'vacunas', 'supplier' => 0, 'cost' => 26000, 'markup' => 25, 'vat' => 10.5, 'min' => 20, 'lots' => [['AF-2611', 150, 60]]],
        ['sku' => 'VAC-MYG-050', 'barcode' => '7798101000110', 'name' => 'Vacuna Mancha y Gangrena x 50 dosis', 'description' => 'Clostridiales — bovinos y ovinos', 'category' => 'vacunas', 'supplier' => 0, 'cost' => 14200, 'markup' => 40, 'vat' => 10.5, 'min' => 10, 'lots' => [['MG-0418', 200, 2]]],
        ['sku' => 'VAC-QUE-025', 'barcode' => '7798101000127', 'name' => 'Vacuna Queratoconjuntivitis x 25 dosis', 'description' => 'Moraxella bovis — inyectable', 'category' => 'vacunas', 'supplier' => 0, 'cost' => 12600, 'markup' => 40, 'vat' => 10.5, 'min' => 8, 'lots' => [['QC-3302', 260, 15], ['QC-3390', 380, 10]]],
        ['sku' => 'DOR-1-500', 'barcode' => '7798101000134', 'name' => 'Doramectina 1% Frasco 500 ml', 'description' => 'Endectocida inyectable', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 23500, 'markup' => 35, 'vat' => 10.5, 'min' => 6, 'lots' => [['DO-8812', 320, 18]]],
        ['sku' => 'ALB-15-1L', 'barcode' => '7798101000141', 'name' => 'Albendazol 15% Bidón 1 L', 'description' => 'Antiparasitario oral', 'category' => 'farmacia', 'supplier' => 2, 'cost' => 9600, 'markup' => 40, 'vat' => 10.5, 'min' => 12, 'lots' => [['AL-1290', 380, 7]]],
        ['sku' => 'PEN-BEN-6M', 'barcode' => '7798101000158', 'name' => 'Penicilina Benzatínica 6.000.000 UI', 'description' => 'Frasco ampolla + diluyente', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 4300, 'markup' => 50, 'vat' => 10.5, 'min' => 10, 'lots' => [['PB-4410', 270, 22]]],
        ['sku' => 'MEL-2-100', 'barcode' => '7798101000165', 'name' => 'Meloxicam 2% Frasco 100 ml', 'description' => 'Antiinflamatorio inyectable', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 8700, 'markup' => 45, 'vat' => 10.5, 'min' => 6, 'lots' => [['ME-7718', 210, 14]]],
        ['sku' => 'FLU-5-50', 'barcode' => '7798101000172', 'name' => 'Flunixin Meglumine 5% Frasco 50 ml', 'description' => 'Analgésico y antipirético', 'category' => 'farmacia', 'supplier' => 1, 'cost' => 7400, 'markup' => 45, 'vat' => 10.5, 'min' => 10, 'lots' => [['FL-3305', 180, 9]]],
        ['sku' => 'CAL-INY-500', 'barcode' => '7798101000189', 'name' => 'Calcio Inyectable 500 ml', 'description' => 'Borogluconato de calcio', 'category' => 'nutricion', 'supplier' => 0, 'cost' => 4600, 'markup' => 45, 'vat' => 10.5, 'min' => 10, 'lots' => [['CA-6620', 260, 25]]],
        ['sku' => 'NUC-TER-20K', 'barcode' => '7798101000196', 'name' => 'Núcleo Vitamínico Mineral Terneros x 20 kg', 'description' => 'Premezcla para recría', 'category' => 'nutricion', 'supplier' => 3, 'cost' => 15800, 'markup' => 35, 'vat' => 10.5, 'min' => 5, 'stock' => 15],
        ['sku' => 'BLO-SAL-10K', 'barcode' => '7798101000202', 'name' => 'Bloque Salino Mineralizado x 10 kg', 'description' => 'Lamedero — campo natural', 'category' => 'nutricion', 'supplier' => 3, 'cost' => 3900, 'markup' => 40, 'vat' => 10.5, 'min' => 8, 'stock' => 4],
        ['sku' => 'CAR-BOT-025', 'barcode' => '7798101000219', 'name' => 'Caravanas Botón Hembra x 25', 'description' => 'Identificación interna del rodeo', 'category' => 'identificacion', 'supplier' => 2, 'cost' => 4100, 'markup' => 40, 'vat' => 21, 'min' => 10, 'stock' => 40],
        ['sku' => 'PIN-CAR-01', 'barcode' => '7798101000226', 'name' => 'Pinza Aplicadora de Caravanas', 'description' => 'Universal — acero reforzado', 'category' => 'instrumental', 'supplier' => 2, 'cost' => 18900, 'markup' => 35, 'vat' => 21, 'min' => 8, 'stock' => 6],
        ['sku' => 'LAP-MAR-AZ', 'barcode' => null, 'name' => 'Lápiz Marcador Ganadero Azul', 'description' => 'Crayón para marcación temporal', 'category' => 'identificacion', 'supplier' => 2, 'cost' => 1600, 'markup' => 60, 'vat' => 21, 'min' => 10, 'stock' => 5],
        ['sku' => 'TER-DIG-01', 'barcode' => '7798101000233', 'name' => 'Termómetro Digital Veterinario', 'description' => 'Punta flexible — lectura en 10 s', 'category' => 'instrumental', 'supplier' => 4, 'cost' => 7300, 'markup' => 50, 'vat' => 21, 'min' => 4, 'stock' => 12],
        ['sku' => 'GUA-PAL-100', 'barcode' => '7798101000240', 'name' => 'Guantes de Palpación x 100', 'description' => 'Manga larga — polietileno', 'category' => 'instrumental', 'supplier' => 4, 'cost' => 6100, 'markup' => 40, 'vat' => 21, 'min' => 20, 'stock' => 9],
        ['sku' => 'COL-ANT-XL', 'barcode' => '7798101000257', 'name' => 'Collar Antipulgas Perro Grande', 'description' => 'Protección 8 meses', 'category' => 'accesorios', 'supplier' => 1, 'cost' => 5900, 'markup' => 60, 'vat' => 21, 'min' => 6, 'stock' => 18],
        ['sku' => 'BOZ-REG-3', 'barcode' => '7798101000264', 'name' => 'Bozal Regulable Talle 3', 'description' => 'Nylon reforzado', 'category' => 'accesorios', 'supplier' => 4, 'cost' => 3300, 'markup' => 70, 'vat' => 21, 'min' => 4, 'stock' => 11],
        ['sku' => 'SAL-REH-1K', 'barcode' => '7798101000271', 'name' => 'Sales Rehidratantes Terneros x 1 kg', 'description' => 'Diarreas neonatales — uso oral', 'category' => 'nutricion', 'supplier' => 3, 'cost' => 4800, 'markup' => 45, 'vat' => 0, 'min' => 10, 'lots' => [['SR-9014', 600, 30]]],
        ['sku' => 'JER-AUT-50', 'barcode' => '7798101000288', 'name' => 'Jeringa Automática 50 ml', 'description' => 'Dosificadora con regulación', 'category' => 'instrumental', 'supplier' => 4, 'cost' => 32000, 'markup' => 35, 'vat' => 21, 'min' => 2, 'stock' => 0],
    ];

    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');
        $suppliers = Supplier::orderBy('id')->pluck('id')->all();
        $since = now()->subDays(30)->setTime(9, 0);

        foreach (self::CATALOG as $item) {
            Product::create([
                'sku' => $item['sku'],
                'barcode' => $item['barcode'],
                'name' => $item['name'],
                'description' => $item['description'],
                'category_id' => $categories[$item['category']],
                'supplier_id' => $suppliers[$item['supplier']],
                'cost_price' => $item['cost'],
                'markup_percent' => $item['markup'],
                'vat_percent' => $item['vat'],
                'price' => Pricing::finalPrice($item['cost'], $item['markup'], $item['vat']),
                'tracks_expiry' => isset($item['lots']),
                'stock' => $item['stock'] ?? 0,
                'min_stock' => $item['min'],
                'created_at' => $since,
                'updated_at' => $since,
            ]);
        }

        Product::factory()
            ->count(self::TOTAL - count(self::CATALOG))
            ->state(fn () => [
                'category_id' => $categories[fake()->randomElement(['farmacia', 'farmacia', 'farmacia', 'nutricion'])],
                'supplier_id' => fake()->randomElement($suppliers),
                'tracks_expiry' => fake()->boolean(35),
                'created_at' => $since,
                'updated_at' => $since,
            ])
            ->create();

        // Products tracking expiry start at 0: LotSeeder sets their stock as the sum of the lots.
        Product::where('tracks_expiry', true)->update(['stock' => 0]);

        Product::query()->each(fn (Product $product) => PriceHistory::create([
            'product_id' => $product->id,
            'cost_price' => $product->cost_price,
            'markup_percent' => $product->markup_percent,
            'vat_percent' => $product->vat_percent,
            'price' => $product->price,
            'created_at' => $since,
            'updated_at' => $since,
        ]));

        // A previous price for Ivermectina so the history isn't a single row.
        $ivermectina = Product::where('sku', 'IVM-315-500')->first();
        PriceHistory::create([
            'product_id' => $ivermectina->id,
            'cost_price' => 9800,
            'markup_percent' => 50,
            'vat_percent' => 10.5,
            'price' => Pricing::finalPrice(9800, 50, 10.5),
            'created_at' => now()->subDays(75),
            'updated_at' => now()->subDays(75),
        ]);
    }
}
