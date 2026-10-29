<?php

namespace Tests\Feature;

use App\Livewire\Erp\InventoryCatalog;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_each_kpi_is_calculated_from_the_database(): void
    {
        // Total: 6 active + 1 inactive. Critical stock (<= 5): 0, 3 and 5 among the active ones.
        Product::factory()->create(['stock' => 0]);
        Product::factory()->create(['stock' => 3]);
        Product::factory()->create(['stock' => 5]);
        Product::factory()->create(['stock' => 6, 'min_stock' => 10]);
        Product::factory()->create(['stock' => 1, 'is_active' => false]);

        $tracked = Product::factory()->tracksExpiry()->create();
        $tracked->lots()->createMany([
            ['lot_number' => 'A', 'expiration_date' => today()->addDays(10), 'quantity' => 5],   // critical (< 30)
            ['lot_number' => 'B', 'expiration_date' => today()->addDays(29), 'quantity' => 5],   // critical (< 30)
            ['lot_number' => 'C', 'expiration_date' => today()->addDays(45), 'quantity' => 5],   // <= 60
            ['lot_number' => 'D', 'expiration_date' => today()->addDays(60), 'quantity' => 5],   // <= 60
            ['lot_number' => 'E', 'expiration_date' => today()->addDays(61), 'quantity' => 5],   // out
            ['lot_number' => 'F', 'expiration_date' => today()->subDay(), 'quantity' => 5],      // expired: out
            ['lot_number' => 'G', 'expiration_date' => today()->addDays(20), 'quantity' => 0],   // empty: out
        ]);
        $tracked->syncStockFromLots();
        Product::factory()->tracksExpiry()->create(['is_active' => false])
            ->lots()->create(['lot_number' => 'X', 'expiration_date' => today()->addDays(5), 'quantity' => 5]); // inactive: out

        $producer = Producer::create(['name' => 'Estancia']);
        Sale::create(['voucher_number' => 'C-1', 'type' => 'contado', 'payment_method' => 'efectivo', 'total' => 1000, 'paid' => 1000, 'status' => 'pagada']);
        Sale::create(['voucher_number' => 'C-2', 'type' => 'contado', 'payment_method' => 'qr', 'total' => 2500.50, 'paid' => 2500.50, 'status' => 'pagada']);
        Sale::create(['voucher_number' => 'C-3', 'type' => 'contado', 'payment_method' => 'tarjeta', 'total' => 499.50, 'paid' => 499.50, 'status' => 'pagada']);
        Sale::create(['voucher_number' => 'R-4', 'type' => 'remito', 'payment_method' => 'cuenta_corriente', 'producer_id' => $producer->id, 'total' => 9000, 'status' => 'pendiente']);
        Sale::create(['voucher_number' => 'C-5', 'type' => 'contado', 'payment_method' => 'efectivo', 'total' => 7777, 'paid' => 7777, 'status' => 'pagada'])
            ->forceFill(['created_at' => now()->subDay()])->save();

        $kpis = Livewire::test(InventoryCatalog::class)->instance()->kpis;

        $this->assertSame(5, $kpis['total']);
        $this->assertSame(3, $kpis['critical_stock']);
        $this->assertSame(4, $kpis['expiring_lots']);
        $this->assertSame(2, $kpis['critical_lots']);
        $this->assertSame(4000.0, $kpis['cash_today']);
    }

    public function test_the_page_shows_the_kpis_and_paginates_by_ten(): void
    {
        $this->withoutVite();
        Product::factory()->count(23)->create();

        $this->get('/erp/inventario')
            ->assertOk()
            ->assertSee('Nuevo artículo')
            ->assertSee('Total productos')
            ->assertSee('Viendo 1–10 de 23 productos registrados');

        Livewire::test(InventoryCatalog::class)
            ->call('gotoPage', 3)
            ->assertSee('Viendo 21–23 de 23 productos registrados');
    }

    public function test_search_filters_by_barcode_sku_or_name_and_exports_csv(): void
    {
        Product::factory()->create(['name' => 'Ivermectina 3,15%', 'barcode' => '779810101', 'sku' => 'IVM-315']);
        Product::factory()->create(['name' => 'Vacuna Aftosa', 'barcode' => '111', 'sku' => 'VAC-AFT']);

        foreach (['779810101', 'IVM', 'ivermec'] as $term) {
            $this->assertSame(['Ivermectina 3,15%'], Livewire::test(InventoryCatalog::class)->set('search', $term)->instance()->products->pluck('name')->all());
        }

        $csv = $this->get('/erp/inventario/exportar?q=aftosa')->assertOk()->streamedContent();
        $this->assertStringContainsString('Vacuna Aftosa', $csv);
        $this->assertStringNotContainsString('Ivermectina', $csv);
    }
}
