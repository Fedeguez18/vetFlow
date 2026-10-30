<?php

namespace Tests\Feature;

use App\Livewire\Erp\SupplierDetail;
use App\Livewire\Erp\Suppliers;
use App\Livewire\ProductEditor;
use App\Livewire\RestockModal;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_a_supplier_in_use_can_be_given_a_baja_and_reactivated_from_the_list(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.']);
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);
        StockMovement::factory()->create(['product_id' => $product->id, 'supplier_id' => $supplier->id]);
        Supplier::factory()->create(['business_name' => 'Agrovet S.R.L.']);

        $names = fn ($component) => collect($component->instance()->suppliers->items())->pluck('business_name')->all();

        $component = Livewire::test(Suppliers::class)
            ->call('deactivateSupplier', $supplier->id)
            ->assertDispatched('toast', message: 'BioPampa S.A. dado de baja.');

        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertSame($supplier->id, $product->fresh()->supplier_id);
        $this->assertSame(1, $supplier->restocks()->count());
        $this->assertSame(['Agrovet S.R.L.'], $names($component));

        $component->set('showInactive', true);
        $this->assertSame(['Agrovet S.R.L.', 'BioPampa S.A.'], $names($component));
        $component->assertSee('De baja')->assertSee('Reactivar');

        $component->call('reactivateSupplier', $supplier->id);
        $this->assertTrue($supplier->fresh()->is_active);
    }

    public function test_a_supplier_can_be_given_a_baja_and_reactivated_from_the_detail(): void
    {
        $supplier = Supplier::factory()->create();

        $component = Livewire::test(SupplierDetail::class, ['supplier' => $supplier])
            ->call('deactivate')
            ->assertSee('Este proveedor está dado de baja')
            ->assertSee('Reactivar');

        $this->assertFalse($supplier->fresh()->is_active);

        $component->call('reactivate')->assertDontSee('Este proveedor está dado de baja');
        $this->assertTrue($supplier->fresh()->is_active);
    }

    public function test_the_delete_error_suggests_the_baja(): void
    {
        $supplier = Supplier::factory()->create();
        Product::factory()->create(['supplier_id' => $supplier->id]);

        Livewire::test(Suppliers::class)
            ->call('deleteSupplier', $supplier->id)
            ->assertDispatched('toast', fn (string $event, array $params) => str_contains($params['message'], 'Podés darlo de baja'));

        $this->assertModelExists($supplier);
    }

    public function test_inactive_suppliers_are_not_offered_when_creating_a_product(): void
    {
        $active = Supplier::factory()->create();
        $inactive = Supplier::factory()->inactive()->create();

        $component = Livewire::test(ProductEditor::class)->call('create');
        $this->assertSame([$active->id], $component->instance()->suppliers->pluck('id')->all());

        $component
            ->set('form.sku', 'ivm-1')
            ->set('form.name', 'Ivermectina 1%')
            ->set('form.category_id', Category::factory()->create()->id)
            ->set('form.supplier_id', $inactive->id)
            ->set('form.cost_price', '1000')
            ->set('form.markup_percent', '50')
            ->set('form.vat_percent', '21')
            ->set('form.initial_stock', '0')
            ->set('form.min_stock', '4')
            ->call('save')
            ->assertHasErrors(['form.supplier_id' => 'exists']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_product_keeps_its_supplier_after_the_baja(): void
    {
        $inactive = Supplier::factory()->inactive()->create(['business_name' => 'BioPampa S.A.']);
        $other = Supplier::factory()->inactive()->create();
        $product = Product::factory()->create(['supplier_id' => $inactive->id, 'name' => 'Ivermectina 1%', 'category_id' => Category::factory()->create()->id]);

        $component = Livewire::test(ProductEditor::class)->call('edit', $product->id);
        $this->assertSame([$inactive->id], $component->instance()->suppliers->pluck('id')->all());
        $component->assertSee('BioPampa S.A. (dado de baja)');

        $component->set('form.name', 'Ivermectina 1% x 50 ml')->call('save')->assertHasNoErrors();
        $this->assertSame($inactive->id, $product->fresh()->supplier_id);

        $component->call('edit', $product->id)
            ->set('form.supplier_id', $other->id)
            ->call('save')
            ->assertHasErrors(['form.supplier_id' => 'exists']);
    }

    public function test_inactive_suppliers_are_not_offered_when_restocking(): void
    {
        $active = Supplier::factory()->create();
        $inactive = Supplier::factory()->inactive()->create();
        $product = Product::factory()->create(['supplier_id' => $inactive->id, 'stock' => 10]);

        $component = Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->assertSet('supplierId', null);

        $this->assertSame([$active->id], $component->instance()->suppliers->pluck('id')->all());

        $component->set('quantity', '5')
            ->set('supplierId', $inactive->id)
            ->call('save')
            ->assertHasErrors(['supplierId' => 'exists']);

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_the_extra_fields_are_saved_and_shown_in_the_detail(): void
    {
        Livewire::test(Suppliers::class)
            ->set('form.business_name', 'BioPampa S.A.')
            ->set('form.contact_name', '  Martín Pérez ')
            ->set('form.iva_condition', 'Responsable Inscripto')
            ->set('form.address', 'Av. Sabattini 1450')
            ->set('form.city', 'Río Cuarto')
            ->set('form.payment_terms', '30 días')
            ->set('form.notes', 'Entrega martes y jueves.')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $supplier = Supplier::sole();
        $this->assertSame('Martín Pérez', $supplier->contact_name);
        $this->assertSame('Av. Sabattini 1450, Río Cuarto', $supplier->fullAddress());
        $this->assertTrue($supplier->is_active);

        $this->withoutVite()->get(route('erp.suppliers.show', $supplier))->assertOk()
            ->assertSee('Martín Pérez')
            ->assertSee('Responsable Inscripto')
            ->assertSee('Av. Sabattini 1450, Río Cuarto')
            ->assertSee('30 días')
            ->assertSee('Entrega martes y jueves.');

        Livewire::test(Suppliers::class)
            ->call('editSupplier', $supplier->id)
            ->assertSet('form.payment_terms', '30 días')
            ->set('form.iva_condition', 'Otra cosa')
            ->call('saveSupplier')
            ->assertHasErrors(['form.iva_condition' => 'in'])
            ->set('form.iva_condition', '')
            ->set('form.notes', '')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $this->assertNull($supplier->fresh()->iva_condition);
        $this->assertNull($supplier->fresh()->notes);
    }

    public function test_the_order_suggestion_lists_products_to_restock_with_quantities(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.', 'contact_name' => 'Martín', 'phone' => '3584642210']);
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Ivermectina 1%', 'stock' => 40, 'min_stock' => 10]);
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Vacuna Aftosa', 'stock' => 8, 'min_stock' => 10]);
        Product::factory()->create(['supplier_id' => $supplier->id, 'name' => 'Oxitetraciclina', 'stock' => 4, 'min_stock' => 2]);
        Product::factory()->inactive()->create(['supplier_id' => $supplier->id, 'name' => 'Discontinuado', 'stock' => 0, 'min_stock' => 5]);

        $component = Livewire::test(SupplierDetail::class, ['supplier' => $supplier]);

        $this->assertSame(
            "Hola Martín, les escribimos de la veterinaria.\nQueremos hacer el siguiente pedido:\n• 2 u. Oxitetraciclina\n• 12 u. Vacuna Aftosa\n\n¡Muchas gracias!",
            $component->instance()->orderText,
        );
        $this->assertStringContainsString(rawurlencode('• 12 u. Vacuna Aftosa'), $component->instance()->whatsappUrl);

        $component->assertSee('Sugerencia de pedido')->assertSee('Copiar pedido')->assertSee('Enviar por WhatsApp');
    }

    public function test_there_is_no_order_suggestion_when_stock_is_fine(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.']);
        Product::factory()->create(['supplier_id' => $supplier->id, 'stock' => 40, 'min_stock' => 10]);

        Livewire::test(SupplierDetail::class, ['supplier' => $supplier])->assertDontSee('Sugerencia de pedido');
        $this->assertSame('Hola BioPampa S.A., les escribimos de la veterinaria.', app(WhatsAppService::class)->supplierOrderText($supplier, collect()));
    }

    public function test_suggested_restock_reaches_twice_the_minimum_and_leaves_critical(): void
    {
        $this->assertSame(12, Product::factory()->make(['stock' => 8, 'min_stock' => 10])->suggestedRestock());
        $this->assertSame(2, Product::factory()->make(['stock' => 4, 'min_stock' => 2])->suggestedRestock());
        $this->assertSame(6, Product::factory()->make(['stock' => 0, 'min_stock' => 0])->suggestedRestock());
        $this->assertSame(0, Product::factory()->make(['stock' => 40, 'min_stock' => 10])->suggestedRestock());
    }
}
