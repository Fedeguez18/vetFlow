<?php

namespace Tests\Feature;

use App\Livewire\Erp\InventoryCatalog;
use App\Models\Lot;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Pricing;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
    }

    public function test_the_demo_data_covers_every_required_case(): void
    {
        $this->seed();

        $this->assertTrue(User::where('email', 'admin@demo.test')->exists());
        $this->assertSame(ProductSeeder::TOTAL, Product::count());
        $this->assertSame(5, Product::where('stock', '<=', 5)->count());
        $this->assertSame(1, Product::where('stock', 0)->count());
        $this->assertGreaterThanOrEqual(3, Product::where('stock', '>', 5)->whereColumn('stock', '<', 'min_stock')->count());
        $this->assertSame(2, Product::whereNull('barcode')->count());
        $this->assertTrue(Product::where('tracks_expiry', false)->exists());

        $lots = Lot::where('quantity', '>', 0);
        $this->assertSame(1, (clone $lots)->whereBetween('expiration_date', [today(), today()->addDays(29)])->count());
        $this->assertSame(2, (clone $lots)->whereBetween('expiration_date', [today()->addDays(30), today()->addDays(60)])->count());
        $this->assertSame(1, (clone $lots)->where('expiration_date', '<', today())->count());
        $this->assertTrue(Product::has('lots', '>', 1)->exists());

        Product::with('lots')->where('tracks_expiry', true)->each(fn ($product) => $this->assertSame((int) $product->lots->sum('quantity'), $product->stock));
        Product::all()->each(fn ($product) => $this->assertEqualsWithDelta(
            Pricing::finalPrice((float) $product->cost_price, (float) $product->markup_percent, (float) $product->vat_percent),
            (float) $product->price,
            0.01,
        ));
        $this->assertSame(['0.00', '10.50', '21.00'], Product::distinct()->orderBy('vat_percent')->pluck('vat_percent')->all());

        $josefina = Producer::where('name', 'Estancia La Josefina')->first();
        $this->assertSame('5.00', $josefina->discount_percent);
        $this->assertGreaterThan(0, (float) $josefina->balance);
        $this->assertTrue(Producer::where('name', Producer::WALK_IN)->exists());

        $this->assertGreaterThanOrEqual(5, Sale::whereDate('created_at', today())->count());
        $this->assertTrue(Sale::whereDate('created_at', '<', today())->exists());

        $this->actingAs(User::first());
        $kpis = Livewire::test(InventoryCatalog::class)->instance()->kpis;
        $this->assertSame(5, $kpis['critical_stock']);
        $this->assertSame(3, $kpis['expiring_lots']);
        $this->assertSame(1, $kpis['critical_lots']);
        $this->assertGreaterThan(0, $kpis['cash_today']);
    }
}
