<?php

namespace Tests\Feature;

use App\Livewire\Erp\SupplierDetail;
use App\Livewire\Erp\Suppliers;
use App\Livewire\ProductEditor;
use App\Livewire\RestockModal;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create(['name' => 'Laura Gómez']);
        $this->actingAs($this->admin);

        $this->supplier = Supplier::factory()->create([
            'business_name' => 'BioPampa S.A.',
            'cuit' => '30-71234567-1',
            'contact_name' => 'Martín',
            'phone' => '3584642210',
        ]);
        Supplier::factory()->create(['business_name' => 'Agrovet del Centro S.R.L.', 'cuit' => '30-70987654-3']);
    }

    public function test_a_supplier_is_found_by_name_and_by_cuit_with_or_without_dashes(): void
    {
        $component = Livewire::test(Suppliers::class);

        foreach (['biopampa', 'BioPampa S.A.', '30-71234567-1', '30712345671', '71234567', '1234567-1'] as $search) {
            $component->set('search', $search);

            $this->assertSame(['BioPampa S.A.'], $this->names($component), "Búsqueda: {$search}");
            $component->assertSee('BioPampa S.A.')->assertDontSee('Agrovet del Centro');
        }

        $component->set('search', '  30-71234567-1  ');
        $this->assertSame(['BioPampa S.A.'], $this->names($component));

        $component->set('search', '99999999999');
        $this->assertSame([], $this->names($component));

        $this->withoutVite()->get('/erp/proveedores?q=30712345671')->assertOk()
            ->assertSee('BioPampa S.A.')
            ->assertDontSee('Agrovet del Centro');
    }

    public function test_a_restock_from_inventory_shows_in_the_history_and_updates_the_last_restock(): void
    {
        $product = Product::factory()->create(['name' => 'Ivermectina 1%', 'stock' => 20, 'min_stock' => 10, 'supplier_id' => null]);

        $this->assertNull($this->listRow()->last_restock_at);

        $this->withoutVite()->get('/erp/inventario')->assertOk()->assertSee('product-restock');

        Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->assertSet('supplierId', null)
            ->set('supplierId', $this->supplier->id)
            ->set('quantity', '24')
            ->set('reason', '  Factura A-0003-00012345 ')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('stock-updated');

        $movement = StockMovement::sole();
        $this->assertSame('ingreso', $movement->type);
        $this->assertSame($this->supplier->id, $movement->supplier_id);
        $this->assertSame('Factura A-0003-00012345', $movement->reason);
        $this->assertSame(44, $product->fresh()->stock);

        $detail = Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier]);
        $this->assertSame([$movement->id], $detail->instance()->restocks->pluck('id')->all());
        $this->assertSame('20/10/2026', $detail->instance()->summary['last_restock_at']->format('d/m/Y'));
        $this->assertSame(1, $detail->instance()->summary['month_restocks']);
        $this->assertSame(24, $detail->instance()->summary['month_units']);
        $detail->assertSee('Ivermectina 1%')
            ->assertSee('Factura A-0003-00012345')
            ->assertSee('Laura Gómez')
            ->assertSee('+24')
            ->assertSee('20/10/2026 12:00');

        $this->assertSame('20/10/2026', $this->listRow()->last_restock_at->format('d/m/Y'));
        $this->withoutVite()->get('/erp/proveedores')->assertOk()->assertSee('20/10/2026');

        $this->travel(3)->days();
        Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->set('supplierId', $this->supplier->id)
            ->set('quantity', '6')
            ->set('reason', 'Factura A-0003-00012399')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('23/10/2026', $this->listRow()->last_restock_at->format('d/m/Y'));
        $this->assertSame('23/10/2026', Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier])->instance()->summary['last_restock_at']->format('d/m/Y'));
        $this->assertSame(
            ['Factura A-0003-00012399', 'Factura A-0003-00012345'],
            Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier])->instance()->restocks->pluck('reason')->all(),
        );
    }

    public function test_a_restock_without_supplier_does_not_count_for_any_supplier(): void
    {
        $product = Product::factory()->create(['supplier_id' => null, 'stock' => 20]);

        Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->set('quantity', '5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->listRow()->last_restock_at);
        $this->assertSame(0, Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier])->instance()->restocks->total());
    }

    public function test_a_product_below_its_minimum_raises_the_low_stock_count_and_goes_in_the_whatsapp_order(): void
    {
        $product = Product::factory()->create(['supplier_id' => $this->supplier->id, 'name' => 'Vacuna Aftosa', 'stock' => 15, 'min_stock' => 10]);
        Product::factory()->create(['supplier_id' => $this->supplier->id, 'name' => 'Ivermectina 1%', 'stock' => 40, 'min_stock' => 10]);

        $this->assertSame(0, $this->listRow()->low_stock_count);
        $detail = Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier]);
        $this->assertSame([], $detail->instance()->lowStockProducts->pluck('name')->all());
        $this->assertStringNotContainsString(rawurlencode('Vacuna Aftosa'), $detail->instance()->whatsappUrl);
        $detail->assertDontSee('Sugerencia de pedido');

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('adjustMode', 'subtract')
            ->set('adjustQuantity', '7')
            ->set('adjustReason', 'conteo')
            ->call('adjust')
            ->assertHasNoErrors();

        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame('low', $product->fresh()->stock_status);

        $this->assertSame(1, $this->listRow()->low_stock_count);
        $this->withoutVite()->get('/erp/proveedores')->assertOk()->assertSeeInOrder(['BioPampa S.A.', '1']);

        $detail = Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier]);
        $this->assertSame(['Vacuna Aftosa'], $detail->instance()->lowStockProducts->pluck('name')->all());
        $detail->assertSee('Sugerencia de pedido')->assertSee('1 con stock bajo');

        $whatsapp = $detail->instance()->whatsappUrl;
        $this->assertStringStartsWith('https://wa.me/5493584642210?text=', $whatsapp);
        $this->assertSame(
            "Hola Martín, les escribimos de la veterinaria.\nQueremos hacer el siguiente pedido:\n• 12 u. Vacuna Aftosa\n\n¡Muchas gracias!",
            rawurldecode(substr($whatsapp, strpos($whatsapp, '?text=') + 6)),
        );

        $product->update(['is_active' => false]);
        $this->assertSame(0, $this->listRow()->low_stock_count);
        $this->assertSame([], Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier])->instance()->lowStockProducts->all());
    }

    public function test_the_history_date_filter_includes_both_ends_and_ignores_invalid_dates(): void
    {
        $product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
        $restock = fn (string $at, string $reason) => StockMovement::factory()->create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'reason' => $reason,
            'created_at' => $at,
        ]);

        $restock('2026-10-01 00:00:00', 'Factura 1');
        $restock('2026-10-10 08:30:00', 'Factura 2');
        $restock('2026-10-15 23:59:59', 'Factura 3');
        $restock('2026-10-16 00:00:00', 'Factura 4');
        StockMovement::factory()->create(['product_id' => $product->id, 'supplier_id' => $this->supplier->id, 'type' => 'ajuste', 'created_at' => '2026-10-10 09:00:00']);

        $component = Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier]);
        $reasons = fn () => $component->instance()->restocks->pluck('reason')->all();

        $this->assertSame(['Factura 4', 'Factura 3', 'Factura 2', 'Factura 1'], $reasons());

        $component->set('dateFrom', '2026-10-10')->set('dateTo', '2026-10-15');
        $this->assertSame(['Factura 3', 'Factura 2'], $reasons());
        $component->assertSee('Ver todo')->assertDontSee('Factura 4');

        $component->set('dateFrom', '2026-10-01')->set('dateTo', '2026-10-01');
        $this->assertSame(['Factura 1'], $reasons());

        $component->set('dateFrom', '2026-10-16')->set('dateTo', '');
        $this->assertSame(['Factura 4'], $reasons());

        $component->set('dateFrom', '')->set('dateTo', '2026-10-09');
        $this->assertSame(['Factura 1'], $reasons());

        $component->set('dateFrom', '2026-10-15')->set('dateTo', '2026-10-10');
        $this->assertSame([], $reasons());
        $component->assertSee('No hay reposiciones en el período elegido.');

        $component->set('dateFrom', 'cualquier-cosa')->set('dateTo', '');
        $this->assertCount(4, $reasons());

        $component->set('dateFrom', '2026-10-10')->call('clearDates')
            ->assertSet('dateFrom', '')
            ->assertSet('dateTo', '')
            ->assertDontSee('Ver todo');
        $this->assertCount(4, $reasons());

        Livewire::withQueryParams(['desde' => '2026-10-10', 'hasta' => '2026-10-15'])
            ->test(SupplierDetail::class, ['supplier' => $this->supplier])
            ->assertSet('dateFrom', '2026-10-10')
            ->assertSee('Factura 2')
            ->assertDontSee('Factura 4');
    }

    /**
     * @return list<string>
     */
    protected function names($component): array
    {
        return collect($component->instance()->suppliers->items())->pluck('business_name')->all();
    }

    protected function listRow(): Supplier
    {
        return Livewire::test(Suppliers::class)->instance()->suppliers->firstWhere('id', $this->supplier->id);
    }
}
