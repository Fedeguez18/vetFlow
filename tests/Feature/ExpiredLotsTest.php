<?php

namespace Tests\Feature;

use App\Livewire\Pos\PosDesk;
use App\Models\Lot;
use App\Models\Product;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpiredLotsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Product with lots of the given quantities expiring in the given number of days.
     *
     * @param  array<int, array{0: int, 1: int}>  $lots  [days until expiration, quantity]
     */
    protected function productWithLots(array $lots): Product
    {
        $product = Product::factory()->tracksExpiry()->create(['is_active' => true, 'price' => 1000]);

        foreach ($lots as [$days, $quantity]) {
            Lot::factory()->for($product)->expiresIn($days)->create(['quantity' => $quantity]);
        }

        $product->syncStockFromLots();

        return $product->fresh();
    }

    protected function sell(Product $product, int $quantity): void
    {
        app(CheckoutService::class)->execute(
            [['product_id' => $product->id, 'quantity' => $quantity]],
            ['type' => 'contado', 'payment_method' => 'efectivo'],
        );
    }

    public function test_a_sale_skips_the_expired_lot_and_takes_the_valid_one(): void
    {
        $product = $this->productWithLots([[-1, 5], [30, 10]]);
        [$expired, $valid] = $product->lots()->orderBy('expiration_date')->get()->all();

        $this->sell($product, 1);

        $this->assertSame(5, $expired->fresh()->quantity);
        $this->assertSame(9, $valid->fresh()->quantity);
        $this->assertSame(14, $product->fresh()->stock);
    }

    public function test_a_lot_expiring_today_can_still_be_sold(): void
    {
        $product = $this->productWithLots([[0, 3]]);

        $this->assertSame(3, $product->sellableStock());

        $this->sell($product, 3);

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_only_expired_units_cannot_be_sold(): void
    {
        $product = $this->productWithLots([[-10, 4]]);

        try {
            $this->sell($product, 1);
            $this->fail('La venta de un lote vencido tendría que fallar.');
        } catch (Exception $e) {
            $this->assertStringContainsString('disponible 0', $e->getMessage());
            $this->assertStringContainsString('4 unidades vencidas', $e->getMessage());
        }

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(0, $product->stockMovements()->count());
    }

    public function test_total_stock_is_not_enough_when_part_of_it_expired(): void
    {
        $product = $this->productWithLots([[-1, 5], [30, 2]]);

        $this->expectExceptionMessage('disponible 2, solicitado 3');

        $this->sell($product, 3);
    }

    public function test_the_pos_only_offers_sellable_units(): void
    {
        $this->openCashRegister();
        $onlyExpired = $this->productWithLots([[-1, 4]]);
        $mixed = $this->productWithLots([[-1, 5], [30, 2]]);

        Livewire::test(PosDesk::class)
            ->assertSee('Solo vencidos')
            ->assertSee('Stock 2')
            ->call('addProduct', $onlyExpired->id)
            ->assertSet('alert', "No se puede vender {$onlyExpired->name}: las 4 unidades en stock están vencidas.")
            ->assertSet('cart', [])
            ->call('addProduct', $mixed->id)
            ->call('setQuantity', $mixed->id, 5)
            ->assertSet("cart.{$mixed->id}.quantity", 2);
    }
}
