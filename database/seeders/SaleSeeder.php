<?php

namespace Database\Seeders;

use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sales go through CheckoutService so stock, lots (FEFO), movements and balances stay consistent.
 * Only high-stock products are sold, so the inventory alerts seeded before are not altered.
 */
class SaleSeeder extends Seeder
{
    /**
     * [days ago, time (null = today, spread before now), payment method, customer key, [sku => quantity]].
     */
    public const SALES = [
        [6, '10:20', 'efectivo', null, ['AGU-18G-100' => 2, 'TER-DIG-01' => 1]],
        [5, '11:05', 'cuenta_corriente', 'josefina', ['VAC-AFT-050' => 4, 'CAR-SEN-100' => 2]],
        [4, '16:40', 'qr', null, ['COL-ANT-XL' => 1, 'ACC-BAL-XL' => 1]],
        [3, '09:30', 'cuenta_corriente', 'ombu', ['SAL-BOV-25K' => 3]],
        [2, '12:15', 'tarjeta', null, ['CAR-BOT-025' => 2, 'AGU-18G-100' => 1]],
        [1, '17:50', 'cuenta_corriente', 'josefina', ['VAC-AFT-050' => 2, 'SAL-BOV-25K' => 2]],
        [0, null, 'efectivo', null, ['SAL-BOV-25K' => 1]],
        [0, null, 'qr', null, ['CAR-SEN-100' => 1, 'AGU-18G-100' => 1]],
        [0, null, 'tarjeta', null, ['ACC-BAL-XL' => 2]],
        [0, null, 'efectivo', null, ['COL-ANT-XL' => 1]],
        [0, null, 'qr', null, ['VAC-AFT-050' => 1]],
        [0, null, 'cuenta_corriente', 'josefina', ['CAR-BOT-025' => 3]],
    ];

    public function run(CheckoutService $checkout): void
    {
        $userId = User::value('id');
        $products = Product::pluck('id', 'sku');
        $customers = collect(CustomerSeeder::CUSTOMERS)->map(fn ($customer) => Producer::where('name', $customer[0])->value('id'));
        $today = collect(self::SALES)->where(0, 0)->count();
        $todayIndex = 0;

        foreach (self::SALES as [$daysAgo, $time, $method, $customer, $items]) {
            Carbon::setTestNow($daysAgo > 0
                ? now()->subDays($daysAgo)->setTimeFromTimeString($time)
                : $this->todayAt($today - $todayIndex++));

            $checkout->execute(
                collect($items)->map(fn ($quantity, $sku) => ['product_id' => $products[$sku], 'quantity' => $quantity])->values()->all(),
                [
                    'type' => $method === 'cuenta_corriente' ? 'remito' : 'contado',
                    'producer_id' => $customer ? $customers[$customer] : null,
                    'payment_method' => $method,
                    'user_id' => $userId,
                ],
            );

            Carbon::setTestNow();
        }
    }

    /**
     * Today's sales are spread 45 minutes apart, ending a few minutes ago and never before midnight.
     */
    protected function todayAt(int $slotsBack): Carbon
    {
        $time = now()->subMinutes(5 + 45 * ($slotsBack - 1));

        return $time->isToday() ? $time : today()->addMinutes(6 - $slotsBack);
    }
}
