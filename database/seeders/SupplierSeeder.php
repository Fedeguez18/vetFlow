<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Fictitious suppliers (business name, CUIT, phone, email).
     */
    public const SUPPLIERS = [
        ['BioPampa Laboratorios S.A.', '30-71234567-1', '(011) 4732-5500', 'ventas@biopampa.test'],
        ['Agrovet del Centro S.R.L.', '30-70987654-3', '(0358) 464-2210', 'pedidos@agrovetcentro.test'],
        ['Distribuidora Ganadera Río Cuarto S.A.', '30-71555222-8', '(0358) 462-7788', 'comercial@dgriocuarto.test'],
        ['Nutrición Animal Pergamino S.A.', '30-70444111-6', '(02477) 43-9900', 'ventas@nutripergamino.test'],
        ['Insumos Veterinarios del Sur S.R.L.', '30-71888333-2', '(0291) 455-1020', 'info@insumosdelsur.test'],
    ];

    public function run(): void
    {
        foreach (self::SUPPLIERS as [$name, $cuit, $phone, $email]) {
            Supplier::create(['business_name' => $name, 'cuit' => $cuit, 'phone' => $phone, 'email' => $email]);
        }
    }
}
