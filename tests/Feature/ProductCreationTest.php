<?php

namespace Tests\Feature;

use App\Livewire\ProductEditor;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_it_creates_a_product_with_initial_stock(): void
    {
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.barcode', '7790001112223')
            ->set('form.sku', 'ivm-1')
            ->set('form.name', 'Ivermectina 1% x 50 ml')
            ->set('form.category_id', $category->id)
            ->set('form.supplier_id', $supplier->id)
            ->set('form.cost_price', '1000')
            ->set('form.markup_percent', '50')
            ->set('form.vat_percent', '21')
            ->set('form.initial_stock', '12')
            ->set('form.min_stock', '4')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('product-saved')
            ->assertDispatched('toast');

        $product = Product::sole();

        $this->assertSame('IVM-1', $product->sku);
        $this->assertSame('1815.00', $product->price);
        $this->assertSame(12, $product->stock);
        $this->assertFalse($product->tracks_expiry);
        $this->assertSame(1, $product->priceHistories()->count());

        $movement = $product->stockMovements()->sole();
        $this->assertSame('ingreso', $movement->type);
        $this->assertSame(12, $movement->quantity);
        $this->assertSame($this->user->id, $movement->user_id);
        $this->assertSame($supplier->id, $movement->supplier_id);
    }

    public function test_the_sku_must_be_unique(): void
    {
        Product::factory()->create(['barcode' => '7790001112223', 'sku' => 'IVM-1']);

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.barcode', '7790009998887')
            ->set('form.sku', 'ivm-1')
            ->set('form.name', 'Duplicado')
            ->set('form.cost_price', '100')
            ->set('form.markup_percent', '10')
            ->call('save')
            ->assertHasErrors(['form.sku' => 'unique']);

        $this->assertSame(1, Product::count());
    }

    public function test_editing_cannot_take_the_barcode_of_another_product(): void
    {
        Product::factory()->create(['barcode' => '7790001112223']);
        $other = Product::factory()->create(['barcode' => '7790009998887']);

        Livewire::test(ProductEditor::class)
            ->call('edit', $other->id)
            ->set('form.barcode', '7790001112223')
            ->call('save')
            ->assertHasErrors(['form.barcode' => 'unique']);

        $this->assertSame('7790009998887', $other->fresh()->barcode);
    }

    public function test_scanning_a_loaded_barcode_opens_that_product(): void
    {
        $product = Product::factory()->create(['barcode' => '7790001112223', 'name' => 'Ivermectina 1%']);

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.name', 'Lo que se haya tipeado')
            ->set('form.barcode', ' 7790001112223 ')
            ->assertSet('form.product.id', $product->id)
            ->assertSet('form.name', 'Ivermectina 1%')
            ->assertSet('tab', 'datos')
            ->assertSee('ya estaba cargado: se abrió Ivermectina 1% para editarlo');

        $this->assertSame(1, Product::count());
    }

    public function test_scanning_the_barcode_of_an_inactive_product_opens_it_and_explains_how_to_reactivate_it(): void
    {
        $product = Product::factory()->create(['barcode' => '7790001112223', 'name' => 'Ivermectina 1%', 'is_active' => false]);

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.barcode', '7790001112223')
            ->assertSet('form.product.id', $product->id)
            ->assertSet('form.is_active', false)
            ->assertSee('que está dado de baja');
    }

    public function test_an_unknown_barcode_fills_nothing(): void
    {
        Product::factory()->create(['barcode' => '7790001112223']);

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.name', 'Producto nuevo')
            ->set('form.barcode', '7790009998887')
            ->assertSet('form.product', null)
            ->assertSet('form.name', 'Producto nuevo')
            ->assertSet('form.sku', '')
            ->assertSet('notice', null);
    }

    public function test_changing_the_barcode_while_editing_does_not_open_another_product(): void
    {
        Product::factory()->create(['barcode' => '7790001112223']);
        $other = Product::factory()->create(['barcode' => '7790009998887']);

        Livewire::test(ProductEditor::class)
            ->call('edit', $other->id)
            ->set('form.barcode', '7790001112223')
            ->assertSet('form.product.id', $other->id)
            ->assertSet('notice', null);
    }

    public function test_the_notice_is_cleared_when_the_editor_is_reopened(): void
    {
        Product::factory()->create(['barcode' => '7790001112223']);

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.barcode', '7790001112223')
            ->assertNotSet('notice', null)
            ->call('close')
            ->call('create')
            ->assertSet('notice', null)
            ->assertSet('form.product', null);
    }

    public function test_the_barcode_is_optional(): void
    {
        $category = Category::factory()->create();

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.sku', 'LAP-MAR')
            ->set('form.name', 'Lápiz marcador')
            ->set('form.category_id', $category->id)
            ->set('form.cost_price', '100')
            ->set('form.markup_percent', '10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('LAP-MAR', Product::whereNull('barcode')->sole()->sku);
    }

    public function test_code_category_and_a_positive_price_are_required(): void
    {
        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.name', 'Sin datos')
            ->set('form.cost_price', '0')
            ->set('form.price', '0')
            ->call('save')
            ->assertHasErrors(['form.sku' => 'required', 'form.category_id' => 'required', 'form.price' => 'gt'])
            ->assertSee('El código es obligatorio.')
            ->assertSee('Elegí el rubro.')
            ->assertSee('El precio tiene que ser mayor a cero.');

        $this->assertSame(0, Product::count());
    }

    public function test_amounts_and_percentages_cannot_be_negative(): void
    {
        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.name', 'Negativo')
            ->set('form.cost_price', '-1')
            ->set('form.vat_percent', '-21')
            ->set('form.markup_percent', '10')
            ->call('save')
            ->assertHasErrors(['form.cost_price', 'form.vat_percent']);
    }

    public function test_a_product_with_lots_requires_an_expiration_date_per_lot(): void
    {
        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.name', 'Vacuna')
            ->set('form.cost_price', '1000')
            ->set('form.markup_percent', '40')
            ->set('form.tracks_expiry', true)
            ->set('form.lots.0.lot_number', 'L1')
            ->set('form.lots.0.quantity', '10')
            ->call('save')
            ->assertHasErrors(['form.lots.0.expiration_date' => 'required']);

        $this->assertSame(0, Product::count());
    }

    public function test_a_final_price_below_cost_warns_but_saves(): void
    {
        $component = Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.sku', 'LIQ-1')
            ->set('form.name', 'Liquidación')
            ->set('form.category_id', Category::factory()->create()->id)
            ->set('form.cost_price', '1000')
            ->set('form.vat_percent', '21')
            ->set('form.price', '900');

        $this->assertTrue($component->instance()->belowCost);
        $component->assertSee('por debajo del costo')->call('save')->assertHasNoErrors();

        $this->assertSame('900.00', Product::sole()->price);
    }

    public function test_editing_never_changes_stock_and_keeps_the_price_history(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory(), 'stock' => 20, 'cost_price' => 1000, 'markup_percent' => 50, 'vat_percent' => 21, 'price' => 1815]);
        $product->priceHistories()->create(['cost_price' => 1000, 'markup_percent' => 50, 'vat_percent' => 21, 'price' => 1815]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->assertSet('form.cost_price', '1000')
            ->set('form.name', 'Nombre nuevo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $product->priceHistories()->count(), 'Sin cambio de precio no hay historial nuevo.');

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('form.cost_price', '1200')
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertSame('Nombre nuevo', $product->name);
        $this->assertSame('2178.00', $product->price);
        $this->assertSame(20, $product->stock);
        $this->assertSame(2, $product->priceHistories()->count());
        $this->assertSame($this->user->id, $product->priceHistories()->latest('id')->first()->user_id);
    }
}
