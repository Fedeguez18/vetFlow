<?php

namespace Tests\Feature;

use App\Livewire\ProductEditor;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LotStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    protected function assertStockMatchesLots(Product $product): void
    {
        $product->refresh();
        $this->assertSame((int) $product->lots()->sum('quantity'), $product->stock);
    }

    public function test_stock_of_a_product_with_lots_is_always_the_sum_of_its_lots(): void
    {
        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.sku', 'VAC-ANT')
            ->set('form.name', 'Vacuna Anticarbunclosa')
            ->set('form.category_id', Category::factory()->create()->id)
            ->set('form.cost_price', '19500')
            ->set('form.markup_percent', '40')
            ->set('form.vat_percent', '10.5')
            ->set('form.tracks_expiry', true)
            ->set('form.lots.0.lot_number', 'A1')
            ->set('form.lots.0.quantity', '10')
            ->set('form.lots.0.expiration_date', today()->addDays(20)->toDateString())
            ->call('addLot')
            ->set('form.lots.1.lot_number', 'B2')
            ->set('form.lots.1.quantity', '14')
            ->set('form.lots.1.expiration_date', today()->addDays(200)->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::sole();
        $this->assertSame(24, $product->stock);
        $this->assertSame(2, $product->lots()->count());
        $this->assertSame(2, $product->stockMovements()->where('type', 'ingreso')->count());
        $this->assertStockMatchesLots($product);

        $stock = app(StockService::class);

        $stock->restock($product, 6, 'C3', today()->addDays(300)->toDateString());
        $this->assertStockMatchesLots($product);

        $stock->adjust($product, 'subtract', 2, 'rotura', $product->lots()->where('lot_number', 'B2')->value('id'));
        $this->assertStockMatchesLots($product);

        app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 12]], ['type' => 'contado', 'payment_method' => 'efectivo']);
        $this->assertStockMatchesLots($product);
        $this->assertSame(16, $product->fresh()->stock);
    }

    public function test_sales_take_units_from_the_lot_that_expires_first(): void
    {
        $product = Product::factory()->tracksExpiry()->create();
        $later = $product->lots()->create(['lot_number' => 'LATER', 'expiration_date' => today()->addDays(300), 'quantity' => 10]);
        $sooner = $product->lots()->create(['lot_number' => 'SOONER', 'expiration_date' => today()->addDays(15), 'quantity' => 4]);
        $product->syncStockFromLots();

        $sale = app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 6]], ['type' => 'contado', 'payment_method' => 'qr']);

        $this->assertSame(0, $sooner->fresh()->quantity);
        $this->assertSame(8, $later->fresh()->quantity);

        $movements = StockMovement::where('sale_id', $sale->id)->orderBy('id')->get();
        $this->assertSame([[$sooner->id, -4], [$later->id, -2]], $movements->map(fn ($m) => [$m->lot_id, $m->quantity])->all());
        $this->assertTrue($movements->every(fn ($m) => $m->type === 'venta'));
    }
}
