<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Demo catalog, customers, stock and sales. Does not create users: sales and
 * stock movements are attributed to the first existing user.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the demo data.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            SupplierSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            LotSeeder::class,
            StockMovementSeeder::class,
            SaleSeeder::class,
        ]);
    }
}
