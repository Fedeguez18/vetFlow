<?php

namespace Database\Seeders;

use App\Models\Producer;
use Illuminate\Database\Seeder;

/**
 * Customers (producers / estancias). Debts come from the remitos created by SaleSeeder.
 */
class CustomerSeeder extends Seeder
{
    public const CUSTOMERS = [
        'consumidor' => [Producer::WALK_IN, null, null, null, 'Consumidor Final', 0],
        'josefina' => ['Estancia La Josefina', 'Roberto Méndez', '20-28489029-4', '358 412-3344', 'Responsable Inscripto', 5],
        'ombu' => ['Cabaña El Ombú', 'El Ombú S.A.', '30-71002233-5', '2477 456-789', 'Responsable Inscripto', 3],
        'ramon' => ['Establecimiento Don Ramón', 'Ramón Gutiérrez', '20-17654321-9', '3584 123456', 'Monotributista', 0],
        'alamos' => ['Tambo Los Álamos', 'Los Álamos S.R.L.', '30-70888999-1', '3571 420-115', 'Responsable Inscripto', 2],
        'sanjorge' => ['Haras San Jorge', 'Jorge Ferreyra', '20-23456789-0', '3582 401-776', 'Exento', 0],
    ];

    public function run(): void
    {
        foreach (self::CUSTOMERS as [$name, $businessName, $cuit, $phone, $ivaCondition, $discount]) {
            Producer::create([
                'name' => $name,
                'business_name' => $businessName,
                'cuit' => $cuit,
                'phone' => $phone,
                'iva_condition' => $ivaCondition,
                'discount_percent' => $discount,
            ]);
        }
    }
}
