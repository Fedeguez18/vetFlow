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

    public function test_barcode_and_sku_must_be_unique(): void
    {
        Product::factory()->create(['barcode' => '7790001112223', 'sku' => 'IVM-1']);

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.barcode', '7790001112223')
            ->set('form.sku', 'ivm-1')
            ->set('form.name', 'Duplicado')
            ->set('form.cost_price', '100')
            ->set('form.markup_percent', '10')
            ->call('save')
            ->assertHasErrors(['form.barcode' => 'unique', 'form.sku' => 'unique']);

        $this->assertSame(1, Product::count());
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
