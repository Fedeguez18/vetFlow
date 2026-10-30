<?php

namespace Tests\Feature;

use App\Livewire\Erp\SupplierDetail;
use App\Livewire\Erp\Suppliers;
use App\Models\Lot;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create(['name' => 'Laura Gómez']);
        $this->actingAs($this->admin);
    }

    public function test_the_list_searches_by_business_name_or_cuit(): void
    {
        Supplier::factory()->create(['business_name' => 'BioPampa S.A.', 'cuit' => '30-71234567-1']);
        Supplier::factory()->create(['business_name' => 'Agrovet del Centro S.R.L.', 'cuit' => '30-70987654-3']);

        $names = fn ($component) => collect($component->instance()->suppliers->items())->pluck('business_name')->all();

        $component = Livewire::test(Suppliers::class)->set('search', 'pampa');
        $this->assertSame(['BioPampa S.A.'], $names($component));

        $component->set('search', '30709876543');
        $this->assertSame(['Agrovet del Centro S.R.L.'], $names($component));

        $component->set('search', '70987654');
        $this->assertSame(['Agrovet del Centro S.R.L.'], $names($component));

        $component->set('search', '');
        $this->assertCount(2, $names($component));
    }

    public function test_the_list_shows_products_low_stock_and_last_restock(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.']);
        Product::factory()->create(['supplier_id' => $supplier->id, 'stock' => 40, 'min_stock' => 10]);
        $low = Product::factory()->create(['supplier_id' => $supplier->id, 'stock' => 8, 'min_stock' => 10]);
        Product::factory()->create(['supplier_id' => $supplier->id, 'stock' => 2, 'min_stock' => 3]);
        Product::factory()->inactive()->create(['supplier_id' => $supplier->id, 'stock' => 0, 'min_stock' => 3]);

        StockMovement::factory()->create(['product_id' => $low->id, 'supplier_id' => $supplier->id, 'created_at' => now()->subDays(12)]);
        StockMovement::factory()->create(['product_id' => $low->id, 'supplier_id' => $supplier->id, 'created_at' => now()->subDays(3)]);
        StockMovement::factory()->create(['product_id' => $low->id, 'supplier_id' => $supplier->id, 'type' => 'ajuste', 'created_at' => now()->subDay()]);

        $row = Livewire::test(Suppliers::class)->instance()->suppliers->first();

        $this->assertSame(4, $row->products_count);
        $this->assertSame(2, $row->low_stock_count);
        $this->assertSame('17/10/2026', $row->last_restock_at->format('d/m/Y'));

        $this->withoutVite()->get('/erp/proveedores')->assertOk()
            ->assertSee(route('erp.suppliers.show', $supplier))
            ->assertSee('17/10/2026');
    }

    public function test_the_detail_shows_contact_products_and_summary(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.', 'phone' => '(0358) 464-2210', 'email' => 'ventas@biopampa.com']);
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Ivermectina 1%', 'stock' => 40, 'min_stock' => 10]);
        $critical = Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Vacuna Aftosa', 'stock' => 3, 'min_stock' => 10]);
        Product::factory()->create(['name' => 'De otro proveedor']);

        StockMovement::factory()->create(['product_id' => $critical->id, 'supplier_id' => $supplier->id, 'quantity' => 10, 'created_at' => now()->subMonth()]);
        StockMovement::factory()->create(['product_id' => $critical->id, 'supplier_id' => $supplier->id, 'quantity' => 6, 'created_at' => now()->startOfMonth()->addDay()]);
        StockMovement::factory()->create(['product_id' => $critical->id, 'supplier_id' => $supplier->id, 'quantity' => 4, 'created_at' => now()->subDays(2)]);
        StockMovement::factory()->create(['product_id' => $critical->id, 'supplier_id' => null, 'quantity' => 50, 'created_at' => now()->subDay()]);

        $component = Livewire::test(SupplierDetail::class, ['supplier' => $supplier]);

        $this->assertSame(['Vacuna Aftosa', 'Ivermectina 1%'], $component->instance()->products->pluck('name')->all());
        $this->assertSame(['Vacuna Aftosa'], $component->instance()->lowStockProducts->pluck('name')->all());

        $summary = $component->instance()->summary;
        $this->assertSame('18/10/2026', $summary['last_restock_at']->format('d/m/Y'));
        $this->assertSame(2, $summary['month_restocks']);
        $this->assertSame(10, $summary['month_units']);

        $whatsapp = $component->instance()->whatsappUrl;
        $this->assertStringStartsWith('https://wa.me/5493584642210?text=', $whatsapp);
        $this->assertStringContainsString(rawurlencode('• 17 u. Vacuna Aftosa'), $whatsapp);
        $this->assertStringNotContainsString(rawurlencode('Ivermectina'), $whatsapp);

        $this->withoutVite()->get(route('erp.suppliers.show', $supplier))->assertOk()
            ->assertSee('BioPampa S.A.')
            ->assertSee('ventas@biopampa.com')
            ->assertSee('Pedir reposición por WhatsApp')
            ->assertDontSee('De otro proveedor');
    }

    public function test_the_whatsapp_button_needs_a_phone(): void
    {
        $supplier = Supplier::factory()->create(['phone' => null]);

        $this->assertNull(Livewire::test(SupplierDetail::class, ['supplier' => $supplier])->instance()->whatsappUrl);
        $this->withoutVite()->get(route('erp.suppliers.show', $supplier))->assertOk()
            ->assertSee('Cargá un teléfono para contactarlo por WhatsApp.');
    }

    public function test_the_restock_history_is_paginated_and_filtered_by_date(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->tracksExpiry()->create(['supplier_id' => $supplier->id, 'name' => 'Vacuna Aftosa']);
        $lot = Lot::factory()->create(['product_id' => $product->id, 'lot_number' => 'L-0042A']);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'lot_id' => $lot->id,
            'supplier_id' => $supplier->id,
            'quantity' => 24,
            'reason' => 'Factura A-0003-00012345',
            'user_id' => $this->admin->id,
            'created_at' => now()->subDays(5),
        ]);
        StockMovement::factory()->count(SupplierDetail::PER_PAGE)->create(['product_id' => $product->id, 'supplier_id' => $supplier->id, 'created_at' => now()->subMonths(2)]);

        $component = Livewire::test(SupplierDetail::class, ['supplier' => $supplier]);
        $this->assertSame(SupplierDetail::PER_PAGE + 1, $component->instance()->restocks->total());
        $this->assertSame('Factura A-0003-00012345', $component->instance()->restocks->first()->reason);

        $component->set('dateFrom', now()->subDays(7)->format('Y-m-d'))->set('dateTo', now()->format('Y-m-d'));
        $this->assertSame(1, $component->instance()->restocks->total());

        $component->assertSee('L-0042A')->assertSee('Factura A-0003-00012345')->assertSee('Laura Gómez')->assertSee('+24');

        $component->set('dateTo', now()->subDays(6)->format('Y-m-d'));
        $component->assertSee('No hay reposiciones en el período elegido.');

        $component->call('clearDates');
        $this->assertSame(SupplierDetail::PER_PAGE + 1, $component->instance()->restocks->total());
    }

    public function test_edit_from_the_detail_opens_the_form_with_the_supplier(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.']);

        $this->withoutVite()->get(route('erp.suppliers.show', $supplier))
            ->assertSee(route('erp.suppliers', ['editar' => $supplier->id]));

        Livewire::withQueryParams(['editar' => $supplier->id])
            ->test(Suppliers::class)
            ->assertSet('form.supplier.id', $supplier->id)
            ->assertSet('form.business_name', 'BioPampa S.A.');
    }

    public function test_a_seller_cannot_open_the_detail(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('erp.suppliers.show', $supplier))
            ->assertForbidden();
    }
}
