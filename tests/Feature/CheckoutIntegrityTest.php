<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Models\Lot;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\CheckoutService;
use App\Services\StockService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::create(['name' => 'Estancia La Josefina', 'balance' => 1500]);
    }

    protected function checkout(): CheckoutService
    {
        return app(CheckoutService::class);
    }

    /**
     * Run a checkout that is expected to fail and return the exception message.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $cartItems
     * @param  array<string, mixed>  $checkoutData
     */
    protected function failedCheckoutMessage(array $cartItems, array $checkoutData = ['type' => 'contado', 'payment_method' => 'efectivo']): string
    {
        try {
            $this->checkout()->execute($cartItems, $checkoutData);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $this->fail('El checkout tendría que haber fallado.');
    }

    public function test_a_failure_while_consuming_the_second_product_rolls_back_the_whole_remito(): void
    {
        $first = Product::factory()->tracksExpiry()->create(['price' => 1000]);
        $lot = Lot::factory()->for($first)->expiresIn(200)->create(['quantity' => 10]);
        $first->syncStockFromLots();
        $second = Product::factory()->create(['stock' => 5, 'price' => 500]);
        DB::table('voucher_sequences')->updateOrInsert(['prefix' => 'R'], ['last_number' => 7, 'created_at' => now(), 'updated_at' => now()]);

        $this->app->instance(StockService::class, new class extends StockService
        {
            public int $calls = 0;

            public function consumeForSale(Product $product, int $quantity, Sale $sale, ?int $userId = null): void
            {
                if (++$this->calls === 2) {
                    throw new StockException('Falla simulada al descontar el segundo producto.');
                }

                parent::consumeForSale($product, $quantity, $sale, $userId);
            }
        });

        $message = $this->failedCheckoutMessage(
            [['product_id' => $first->id, 'quantity' => 3], ['product_id' => $second->id, 'quantity' => 2]],
            ['type' => 'remito', 'producer_id' => $this->producer->id],
        );

        $this->assertSame('Falla simulada al descontar el segundo producto.', $message);
        $this->assertSame(2, app(StockService::class)->calls);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleItem::count());
        $this->assertSame(0, StockMovement::where('type', 'venta')->count());
        $this->assertSame(10, $first->fresh()->stock);
        $this->assertSame(10, $lot->fresh()->quantity);
        $this->assertSame(5, $second->fresh()->stock);
        $this->assertSame('1500.00', $this->producer->fresh()->balance);
        $this->assertSame(7, (int) DB::table('voucher_sequences')->where('prefix', 'R')->value('last_number'));
        $this->assertSame('R-00000008', $this->checkout()->nextVoucherNumber('remito'));
    }

    public function test_an_inactive_product_cannot_be_sold_and_keeps_its_stock(): void
    {
        $product = Product::factory()->create(['is_active' => false, 'stock' => 20]);

        $message = $this->failedCheckoutMessage([['product_id' => $product->id, 'quantity' => 1]]);

        $this->assertSame("El producto #{$product->id} no existe o está inactivo.", $message);
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_repeated_lines_of_the_same_product_are_validated_against_the_total_quantity(): void
    {
        $product = Product::factory()->create(['name' => 'Ivermectina 1%', 'stock' => 5]);

        $message = $this->failedCheckoutMessage([
            ['product_id' => $product->id, 'quantity' => 3],
            ['product_id' => $product->id, 'quantity' => 3],
        ]);

        $this->assertSame('Stock insuficiente para Ivermectina 1%: disponible 5, solicitado 6.', $message);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_repeated_lines_of_the_same_product_are_merged_into_one_item_and_one_movement(): void
    {
        $product = Product::factory()->create(['stock' => 5, 'price' => 1000]);

        $sale = $this->checkout()->execute(
            [['product_id' => $product->id, 'quantity' => 2], ['product_id' => $product->id, 'quantity' => 2]],
            ['type' => 'contado', 'payment_method' => 'efectivo'],
        );

        $this->assertSame(1, $product->fresh()->stock);
        $item = $sale->saleItems->sole();
        $this->assertSame(4, $item->quantity);
        $this->assertSame('4000.00', $sale->fresh()->total);
        $this->assertSame(-4, StockMovement::where('sale_id', $sale->id)->sole()->quantity);
    }

    public function test_zero_and_negative_quantities_are_rejected_without_touching_stock(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        foreach ([0, -3] as $quantity) {
            $message = $this->failedCheckoutMessage([['product_id' => $product->id, 'quantity' => $quantity]]);

            $this->assertSame('Renglón de carrito inválido.', $message);
        }

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, Sale::count());
    }

    public function test_an_empty_cart_is_rejected(): void
    {
        $this->assertSame('El carrito está vacío.', $this->failedCheckoutMessage([]));
        $this->assertSame(0, Sale::count());
    }

    public function test_an_unknown_sale_type_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $message = $this->failedCheckoutMessage([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'fiado']);

        $this->assertSame('Tipo de venta inválido: fiado.', $message);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_a_remito_without_producer_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $message = $this->failedCheckoutMessage([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'remito']);

        $this->assertSame('Un remito requiere un productor.', $message);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame('C-00000001', $this->checkout()->nextVoucherNumber('contado'));
        $this->assertSame('R-00000001', $this->checkout()->nextVoucherNumber('remito'));
    }
}
