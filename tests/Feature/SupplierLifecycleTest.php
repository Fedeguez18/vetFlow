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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Supplier $supplier;

    protected Supplier $other;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $this->actingAs(User::factory()->admin()->create(['name' => 'Laura Gómez']));

        $this->supplier = Supplier::factory()->create(['business_name' => 'BioPampa S.A.', 'contact_name' => 'Martín', 'phone' => '(0358) 464-2210']);
        $this->other = Supplier::factory()->create(['business_name' => 'Agrovet del Centro S.R.L.']);
        $this->product = Product::factory()->create([
            'supplier_id' => $this->supplier->id,
            'category_id' => Category::factory()->create()->id,
            'name' => 'Vacuna Aftosa',
            'stock' => 30,
            'min_stock' => 10,
        ]);
        StockMovement::factory()->create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 24,
            'reason' => 'Factura A-0003-00012345',
            'created_at' => now()->subDays(5),
        ]);
    }

    public function test_a_supplier_with_products_given_a_baja_is_not_offered_but_keeps_its_products_and_history(): void
    {
        Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier])
            ->call('deactivate')
            ->assertDispatched('toast', message: 'BioPampa S.A. dado de baja.');

        $this->assertFalse($this->supplier->fresh()->is_active);
        $this->assertSame([$this->other->id], $this->listedSupplierIds());

        $creating = Livewire::test(ProductEditor::class)->call('create');
        $this->assertSame([$this->other->id], $creating->instance()->suppliers->pluck('id')->all());
        $creating->assertDontSee('BioPampa S.A.');

        $anotherProduct = Product::factory()->create(['supplier_id' => $this->other->id]);
        $editingAnother = Livewire::test(ProductEditor::class)->call('edit', $anotherProduct->id);
        $this->assertSame([$this->other->id], $editingAnother->instance()->suppliers->pluck('id')->all());

        $restock = Livewire::test(RestockModal::class)->call('start', $this->product->id)->assertSet('supplierId', null);
        $this->assertSame([$this->other->id], $restock->instance()->suppliers->pluck('id')->all());
        $restock->assertDontSee('BioPampa S.A.');

        $this->assertSame($this->supplier->id, $this->product->fresh()->supplier_id);

        $detail = Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier->fresh()]);
        $this->assertSame(['Vacuna Aftosa'], $detail->instance()->products->pluck('name')->all());
        $this->assertSame(['Factura A-0003-00012345'], $detail->instance()->restocks->pluck('reason')->all());

        $this->withoutVite()->get(route('erp.suppliers.show', $this->supplier))->assertOk()
            ->assertSee('Este proveedor está dado de baja')
            ->assertSee('Reactivar')
            ->assertSee('Vacuna Aftosa')
            ->assertSee('Factura A-0003-00012345')
            ->assertSee('15/10/2026');

        $this->withoutVite()->get('/erp/proveedores?inactivos=1')->assertOk()
            ->assertSee('BioPampa S.A.')
            ->assertSee('De baja');
    }

    public function test_editing_a_product_whose_supplier_is_inactive_keeps_it(): void
    {
        $this->supplier->update(['is_active' => false]);
        $inactiveToo = Supplier::factory()->inactive()->create();

        $editor = Livewire::test(ProductEditor::class)
            ->call('edit', $this->product->id)
            ->assertSet('form.supplier_id', $this->supplier->id)
            ->assertSee('BioPampa S.A. (dado de baja)');

        $this->assertSame([$this->other->id, $this->supplier->id], $editor->instance()->suppliers->pluck('id')->all());

        $editor->set('form.name', 'Vacuna Aftosa x 50 dosis')
            ->set('form.min_stock', '12')
            ->call('save')
            ->assertHasNoErrors();

        $product = $this->product->fresh();
        $this->assertSame('Vacuna Aftosa x 50 dosis', $product->name);
        $this->assertSame($this->supplier->id, $product->supplier_id);

        Livewire::test(ProductEditor::class)
            ->call('edit', $this->product->id)
            ->set('form.supplier_id', $inactiveToo->id)
            ->call('save')
            ->assertHasErrors(['form.supplier_id' => 'exists']);
        $this->assertSame($this->supplier->id, $this->product->fresh()->supplier_id);

        Livewire::test(ProductEditor::class)
            ->call('edit', $this->product->id)
            ->set('form.supplier_id', $this->other->id)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame($this->other->id, $this->product->fresh()->supplier_id);

        Livewire::test(ProductEditor::class)
            ->call('edit', $this->product->id)
            ->set('form.supplier_id', $this->supplier->id)
            ->call('save')
            ->assertHasErrors(['form.supplier_id' => 'exists']);
        $this->assertSame($this->other->id, $this->product->fresh()->supplier_id);
    }

    public function test_a_reactivated_supplier_is_offered_everywhere_again(): void
    {
        $this->supplier->update(['is_active' => false]);

        Livewire::test(Suppliers::class)
            ->set('showInactive', true)
            ->call('reactivateSupplier', $this->supplier->id)
            ->assertDispatched('toast', message: 'BioPampa S.A. reactivado.');

        $this->assertTrue($this->supplier->fresh()->is_active);
        $this->assertSame([$this->other->id, $this->supplier->id], $this->listedSupplierIds());

        $this->withoutVite()->get(route('erp.suppliers.show', $this->supplier))->assertOk()
            ->assertDontSee('Este proveedor está dado de baja')
            ->assertSee('Dar de baja');

        $creating = Livewire::test(ProductEditor::class)->call('create');
        $this->assertSame([$this->other->id, $this->supplier->id], $creating->instance()->suppliers->pluck('id')->all());
        $creating->assertSee('BioPampa S.A.')->assertDontSee('(dado de baja)');

        $restock = Livewire::test(RestockModal::class)
            ->call('start', $this->product->id)
            ->assertSet('supplierId', $this->supplier->id);
        $this->assertSame([$this->other->id, $this->supplier->id], $restock->instance()->suppliers->pluck('id')->all());

        $restock->set('quantity', '6')->set('reason', 'Factura A-0003-00012399')->call('save')->assertHasNoErrors();
        $this->assertSame(2, $this->supplier->restocks()->count());

        $anotherProduct = Product::factory()->create(['supplier_id' => $this->other->id, 'category_id' => $this->product->category_id]);
        Livewire::test(ProductEditor::class)
            ->call('edit', $anotherProduct->id)
            ->set('form.supplier_id', $this->supplier->id)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame($this->supplier->id, $anotherProduct->fresh()->supplier_id);
    }

    public function test_the_new_fields_are_shown_in_the_detail_and_can_be_cleared(): void
    {
        Livewire::test(Suppliers::class)
            ->call('editSupplier', $this->supplier->id)
            ->set('form.contact_name', ' Martín Pérez ')
            ->set('form.cuit', '30712345671')
            ->set('form.iva_condition', 'Responsable Inscripto')
            ->set('form.email', ' Ventas@BioPampa.com ')
            ->set('form.address', 'Av. Sabattini 1450')
            ->set('form.city', 'Río Cuarto')
            ->set('form.payment_terms', '30 días fecha factura')
            ->set('form.notes', "Entrega martes y jueves.\nPedir antes de las 10.")
            ->call('saveSupplier')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Proveedor actualizado.');

        $supplier = $this->supplier->fresh();
        $this->assertSame('30-71234567-1', $supplier->cuit);
        $this->assertSame('ventas@biopampa.com', $supplier->email);

        $this->withoutVite()->get(route('erp.suppliers.show', $supplier))->assertOk()
            ->assertSee('Martín Pérez')
            ->assertSee('CUIT 30-71234567-1')
            ->assertSee('Responsable Inscripto')
            ->assertSee('mailto:ventas@biopampa.com', false)
            ->assertSee('Av. Sabattini 1450, Río Cuarto')
            ->assertSee('30 días fecha factura')
            ->assertSee("Entrega martes y jueves.\nPedir antes de las 10.")
            ->assertDontSee('Sin cargar');

        Livewire::test(Suppliers::class)
            ->call('editSupplier', $supplier->id)
            ->assertSet('form.payment_terms', '30 días fecha factura')
            ->set('form.contact_name', '')
            ->set('form.cuit', '   ')
            ->set('form.iva_condition', '')
            ->set('form.address', '')
            ->set('form.payment_terms', '  ')
            ->set('form.notes', '')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $supplier = $supplier->fresh();
        $this->assertNull($supplier->contact_name);
        $this->assertNull($supplier->cuit);
        $this->assertNull($supplier->iva_condition);
        $this->assertNull($supplier->address);
        $this->assertNull($supplier->payment_terms);
        $this->assertNull($supplier->notes);
        $this->assertSame('Río Cuarto', $supplier->city);
        $this->assertSame('Río Cuarto', $supplier->fullAddress());
        $this->assertSame('ventas@biopampa.com', $supplier->email);

        $this->withoutVite()->get(route('erp.suppliers.show', $supplier))->assertOk()
            ->assertDontSee('Martín Pérez')
            ->assertDontSee('CUIT')
            ->assertDontSee('Responsable Inscripto')
            ->assertDontSee('Av. Sabattini')
            ->assertDontSee('30 días fecha factura')
            ->assertDontSee('Entrega martes y jueves.')
            ->assertSee('Río Cuarto')
            ->assertSee('Sin cargar');

        $this->assertSame(
            'Hola BioPampa S.A., les escribimos de la veterinaria.',
            Livewire::test(SupplierDetail::class, ['supplier' => $supplier])->instance()->orderText,
        );
    }

    public function test_copy_order_and_whatsapp_buttons_carry_the_suggested_order(): void
    {
        Product::factory()->create(['supplier_id' => $this->supplier->id, 'name' => 'Ivermectina 1%', 'stock' => 4, 'min_stock' => 2]);
        Product::factory()->create(['supplier_id' => $this->supplier->id, 'name' => 'Oxitetraciclina', 'stock' => 40, 'min_stock' => 10]);
        $this->product->update(['stock' => 8]);

        $expected = "Hola Martín, les escribimos de la veterinaria.\nQueremos hacer el siguiente pedido:\n• 2 u. Ivermectina 1%\n• 12 u. Vacuna Aftosa\n\n¡Muchas gracias!";

        $detail = Livewire::test(SupplierDetail::class, ['supplier' => $this->supplier]);
        $this->assertSame($expected, $detail->instance()->orderText);

        $whatsapp = $detail->instance()->whatsappUrl;
        $this->assertSame('https://wa.me/5493584642210?text='.rawurlencode($expected), $whatsapp);

        $this->withoutVite()->get(route('erp.suppliers.show', $this->supplier))->assertOk()
            ->assertSee('Sugerencia de pedido')
            ->assertSee('Copiar pedido')
            ->assertSee('navigator.clipboard.writeText('.Js::from($expected)->toHtml().')', false)
            ->assertSee('Enviar por WhatsApp')
            ->assertSee('href="'.e($whatsapp).'"', false)
            ->assertSeeInOrder(['Ivermectina 1%', '4', '2', '2', 'Vacuna Aftosa', '8', '10', '12']);

        $this->supplier->update(['phone' => null]);

        $this->withoutVite()->get(route('erp.suppliers.show', $this->supplier))->assertOk()
            ->assertSee('Copiar pedido')
            ->assertSee('navigator.clipboard.writeText('.Js::from($expected)->toHtml().')', false)
            ->assertDontSee('Enviar por WhatsApp')
            ->assertDontSee('wa.me')
            ->assertSee('Cargá un teléfono para contactarlo por WhatsApp.');
    }

    /**
     * @return list<int>
     */
    protected function listedSupplierIds(): array
    {
        return collect(Livewire::test(Suppliers::class)->instance()->suppliers->items())->pluck('id')->all();
    }
}
