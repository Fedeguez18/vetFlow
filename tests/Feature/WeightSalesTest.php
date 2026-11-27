<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Livewire\Erp\InventoryCatalog;
use App\Livewire\Pos\PosDesk;
use App\Livewire\ProductEditor;
use App\Livewire\RestockModal;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\StockService;
use App\Support\Quantity;
use Database\Seeders\DogFoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WeightSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_quantities_format_as_grams_or_kilos(): void
    {
        $this->assertSame('350 g', Quantity::format(350, true));
        $this->assertSame('2,5 kg', Quantity::format(2500, true));
        $this->assertSame('12 kg', Quantity::format(12000, true));
        $this->assertSame('3', Quantity::format(3, false));
        $this->assertSame(2500, Quantity::gramsFromKilos('2,5'));
        $this->assertSame(800.0, Quantity::lineTotal(8000, 100, true));
    }

    public function test_checkout_charges_the_grams_at_the_price_per_kg(): void
    {
        $food = $this->looseFood(stockGrams: 10000, pricePerKg: 8000);

        $sale = app(CheckoutService::class)->execute(
            [['product_id' => $food->id, 'quantity' => 100]],
            ['type' => 'contado', 'payment_method' => 'efectivo'],
        );

        $item = $sale->saleItems->sole();
        $this->assertSame('800.00', $sale->total);
        $this->assertSame(100, $item->quantity);
        $this->assertTrue($item->sold_by_weight);
        $this->assertSame('8000.00', $item->unit_price);
        $this->assertSame('100 g', $item->formattedQuantity());
        $this->assertSame(9900, $food->fresh()->stock);
    }

    public function test_checkout_refuses_more_grams_than_in_stock(): void
    {
        $food = $this->looseFood(stockGrams: 500);

        $this->expectExceptionMessage('disponible 500 g, solicitado 1,2 kg');

        app(CheckoutService::class)->execute([['product_id' => $food->id, 'quantity' => 1200]], ['type' => 'contado', 'payment_method' => 'efectivo']);
    }

    public function test_the_pos_adds_loose_food_with_one_kilo_and_takes_the_typed_grams(): void
    {
        $this->openCashRegister();
        $food = $this->looseFood(stockGrams: 10000, pricePerKg: 8000);

        $component = Livewire::test(PosDesk::class)
            ->call('addProduct', $food->id)
            ->assertSet("cart.{$food->id}.quantity", 1000)
            ->assertDispatched('focus-weight')
            ->call('addProduct', $food->id)
            ->assertSet("cart.{$food->id}.quantity", 1000)
            ->call('setQuantity', $food->id, 250);

        $this->assertSame(2000.0, $component->get('total'));

        $component->set('paymentMethod', 'efectivo')->call('confirm');

        $this->assertSame('2000.00', Sale::sole()->total);
        $this->assertSame(9750, $food->fresh()->stock);
    }

    public function test_selling_loose_food_opens_a_closed_bag_and_lowers_its_stock(): void
    {
        $loose = $this->looseFood(stockGrams: 0, pricePerKg: 8000);
        $bag = $this->bagOf($loose, bags: 3, kilos: 15);

        $this->assertSame(45000, $loose->sellableStock());

        $sale = app(CheckoutService::class)->execute([['product_id' => $loose->id, 'quantity' => 300]], ['type' => 'contado', 'payment_method' => 'efectivo']);

        $this->assertSame('2400.00', $sale->total);
        $this->assertSame(2, $bag->fresh()->stock);
        $this->assertSame(14700, $loose->fresh()->stock);
        $this->assertSame('Bolsa abierta: pasó a Alimento suelto', $bag->stockMovements()->sole()->reason);

        app(CheckoutService::class)->execute([['product_id' => $loose->id, 'quantity' => 500]], ['type' => 'contado', 'payment_method' => 'efectivo']);

        $this->assertSame(2, $bag->fresh()->stock, 'The open bag still covers it: no new bag is opened.');
        $this->assertSame(14200, $loose->fresh()->stock);
    }

    public function test_a_sale_bigger_than_the_open_bag_opens_as_many_bags_as_needed(): void
    {
        $loose = $this->looseFood(stockGrams: 1000);
        $bag = $this->bagOf($loose, bags: 3, kilos: 15);

        app(CheckoutService::class)->execute([['product_id' => $loose->id, 'quantity' => 20000]], ['type' => 'contado', 'payment_method' => 'efectivo']);

        $this->assertSame(1, $bag->fresh()->stock);
        $this->assertSame(11000, $loose->fresh()->stock);
    }

    public function test_loose_food_without_bags_left_cannot_be_sold(): void
    {
        $loose = $this->looseFood(stockGrams: 200);
        $this->bagOf($loose, bags: 0, kilos: 15);

        $this->expectExceptionMessage('disponible 200 g, solicitado 500 g');

        app(CheckoutService::class)->execute([['product_id' => $loose->id, 'quantity' => 500]], ['type' => 'contado', 'payment_method' => 'efectivo']);
    }

    public function test_an_offline_sale_of_loose_food_also_opens_the_bag(): void
    {
        $this->withCredentials();
        $cashSession = $this->openCashRegister();
        $loose = $this->looseFood(stockGrams: 0, pricePerKg: 8000);
        $bag = $this->bagOf($loose, bags: 2, kilos: 15);

        $this->postJson(route('pos.offline.sync'), [
            'uuid' => (string) Str::uuid(),
            'sold_at' => now()->subMinutes(5)->toIso8601String(),
            'cash_session_id' => $cashSession->id,
            'type' => 'contado',
            'payment_method' => 'efectivo',
            'producer_id' => null,
            'voucher_number' => null,
            'provisional_number' => null,
            'items' => [['product_id' => $loose->id, 'quantity' => 300, 'unit_price' => 8000]],
        ])->assertOk()->assertDontSee('Faltó stock');

        $this->assertSame(1, $bag->fresh()->stock);
        $this->assertSame(14700, $loose->fresh()->stock);
    }

    public function test_a_small_rest_of_the_open_bag_goes_to_waste_when_a_new_bag_is_opened(): void
    {
        $loose = $this->looseFood(stockGrams: 50);
        $bag = $this->bagOf($loose, bags: 2, kilos: 15);

        app(CheckoutService::class)->execute([['product_id' => $loose->id, 'quantity' => 500]], ['type' => 'contado', 'payment_method' => 'efectivo']);

        $this->assertSame(1, $bag->fresh()->stock);
        $this->assertSame(14500, $loose->fresh()->stock);

        $waste = $loose->stockMovements()->where('type', 'egreso')->sole();
        $this->assertSame(-50, $waste->quantity);
        $this->assertSame('Merma de bolsa: resto de 50 g al abrir otra', $waste->reason);
    }

    public function test_a_big_rest_of_the_open_bag_is_kept_when_a_new_bag_is_opened(): void
    {
        $loose = $this->looseFood(stockGrams: 1000);
        $bag = $this->bagOf($loose, bags: 2, kilos: 15);

        app(StockService::class)->openBag($bag);

        $this->assertSame(16000, $loose->fresh()->stock);
        $this->assertSame(0, $loose->stockMovements()->where('type', 'egreso')->count());
    }

    public function test_finishing_the_open_bag_writes_off_what_the_system_still_shows(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $loose = $this->looseFood(stockGrams: 300);
        $bag = $this->bagOf($loose, bags: 2, kilos: 15);

        Livewire::test(InventoryCatalog::class)
            ->assertSee('Terminar bolsa')
            ->call('finishOpenBag', $loose->id)
            ->assertDispatched('toast');

        $this->assertSame(0, $loose->fresh()->stock);
        $this->assertSame('Merma de bolsa: se terminó con 300 g sin vender', $loose->stockMovements()->sole()->reason);

        app(CheckoutService::class)->execute([['product_id' => $loose->id, 'quantity' => 200]], ['type' => 'contado', 'payment_method' => 'efectivo']);

        $this->assertSame(1, $bag->fresh()->stock);
        $this->assertSame(14800, $loose->fresh()->stock);
    }

    public function test_an_empty_open_bag_cannot_be_finished(): void
    {
        $loose = $this->looseFood(stockGrams: 0);

        $this->expectException(StockException::class);

        app(StockService::class)->finishOpenBag($loose);
    }

    public function test_opening_a_bag_moves_its_kilos_to_the_loose_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $loose = $this->looseFood(stockGrams: 2000);
        $bag = Product::factory()->for(Category::factory())->create(['stock' => 3, 'tracks_expiry' => false, 'bulk_product_id' => $loose->id, 'bag_weight_grams' => 15000]);

        Livewire::test(InventoryCatalog::class)->call('openBag', $bag->id)->assertDispatched('toast');

        $this->assertSame(2, $bag->fresh()->stock);
        $this->assertSame(17000, $loose->fresh()->stock);
        $this->assertSame(-1, $bag->stockMovements()->sole()->quantity);
        $this->assertSame(15000, $loose->stockMovements()->where('type', 'ingreso')->latest('id')->first()->quantity);
    }

    public function test_a_bag_cannot_be_opened_without_stock(): void
    {
        $loose = $this->looseFood(stockGrams: 0);
        $bag = Product::factory()->create(['stock' => 0, 'tracks_expiry' => false, 'bulk_product_id' => $loose->id, 'bag_weight_grams' => 15000]);

        $this->expectException(StockException::class);

        app(StockService::class)->openBag($bag);
    }

    public function test_a_loose_product_is_created_without_stock_of_its_own(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.sku', 'DCH-SUE')
            ->set('form.name', 'Dog Chow suelto')
            ->set('form.category_id', $category->id)
            ->set('form.sold_by_weight', true)
            ->set('form.cost_price', '2500')
            ->set('form.markup_percent', '40')
            ->set('form.vat_percent', '10,5')
            ->set('form.initial_stock', '12')
            ->set('form.min_stock', '5')
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::sole();
        $this->assertTrue($product->sold_by_weight);
        $this->assertSame(0, $product->stock);
        $this->assertSame(0, $product->min_stock);
        $this->assertSame(0, $product->stockMovements()->count());
    }

    public function test_a_bag_is_linked_to_its_loose_product_from_the_editor(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $loose = $this->looseFood(stockGrams: 0);
        $bag = Product::factory()->for(Category::factory())->create(['stock' => 0, 'tracks_expiry' => false]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $bag->id)
            ->set('form.bulk_product_id', $loose->id)
            ->set('form.bag_weight', '21')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$loose->id, 21000], [$bag->fresh()->bulk_product_id, $bag->fresh()->bag_weight_grams]);
    }

    public function test_weight_selling_cannot_be_switched_with_stock_loaded(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $product = Product::factory()->for(Category::factory())->create(['stock' => 4, 'tracks_expiry' => false]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('form.sold_by_weight', true)
            ->set('form.min_stock', '0')
            ->call('save');

        $this->assertFalse($product->fresh()->sold_by_weight);
    }

    public function test_restocking_loose_food_takes_kilos(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $food = $this->looseFood(stockGrams: 1000);

        Livewire::test(RestockModal::class)
            ->call('start', $food->id)
            ->set('quantity', '7,5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8500, $food->fresh()->stock);
    }

    public function test_an_offline_sale_of_loose_food_is_charged_by_weight(): void
    {
        $this->withCredentials();
        $cashSession = $this->openCashRegister();
        $food = $this->looseFood(stockGrams: 5000, pricePerKg: 8000);

        $this->getJson(route('pos.offline.snapshot'))->assertOk()->assertJsonFragment(['id' => $food->id, 'sold_by_weight' => true]);

        $this->postJson(route('pos.offline.sync'), [
            'uuid' => (string) Str::uuid(),
            'sold_at' => now()->subMinutes(5)->toIso8601String(),
            'cash_session_id' => $cashSession->id,
            'type' => 'contado',
            'payment_method' => 'efectivo',
            'producer_id' => null,
            'voucher_number' => null,
            'provisional_number' => null,
            'items' => [['product_id' => $food->id, 'quantity' => 300, 'unit_price' => 8000]],
        ])->assertOk();

        $this->assertSame('2400.00', Sale::sole()->total);
        $this->assertSame(4700, $food->fresh()->stock);
    }

    public function test_the_dog_food_seeder_creates_bags_linked_to_loose_food_once(): void
    {
        $this->seed(DogFoodSeeder::class);
        $this->seed(DogFoodSeeder::class);

        $this->assertSame(count(DogFoodSeeder::FOODS) * 2, Product::count());

        $bag = Product::where('sku', 'DCH-ADU-21K')->sole();
        $this->assertTrue($bag->canBeOpened());
        $this->assertSame(21000, $bag->bag_weight_grams);
        $this->assertSame(6, $bag->stock);
        $this->assertTrue($bag->bulkProduct->sold_by_weight);
        $this->assertSame(0, $bag->bulkProduct->stock);
        $this->assertSame(6 * 21000, $bag->bulkProduct->sellableStock());
    }

    protected function bagOf(Product $loose, int $bags, int $kilos): Product
    {
        return Product::factory()->for(Category::factory())->create([
            'name' => "Alimento x {$kilos} kg",
            'is_active' => true,
            'tracks_expiry' => false,
            'stock' => $bags,
            'bulk_product_id' => $loose->id,
            'bag_weight_grams' => $kilos * 1000,
        ]);
    }

    protected function looseFood(int $stockGrams, float $pricePerKg = 5000): Product
    {
        return Product::factory()->for(Category::factory())->create([
            'name' => 'Alimento suelto',
            'is_active' => true,
            'tracks_expiry' => false,
            'sold_by_weight' => true,
            'stock' => $stockGrams,
            'min_stock' => 0,
            'price' => $pricePerKg,
        ]);
    }
}
