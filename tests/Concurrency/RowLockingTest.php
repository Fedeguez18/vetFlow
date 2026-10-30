<?php

namespace Tests\Concurrency;

use App\Models\PosTerminal;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\ProductService;
use App\Services\StockService;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class RowLockingTest extends PostgresTestCase
{
    /**
     * @return callable(Connection): void
     */
    protected function lockRow(string $table, int $id): callable
    {
        return fn (Connection $other) => $other->table($table)->where('id', $id)->lockForUpdate()->first();
    }

    public function test_a_checkout_waits_for_the_product_another_terminal_is_selling(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $cart = [['product_id' => $product->id, 'quantity' => 1]];
        $checkout = ['type' => 'contado', 'payment_method' => 'efectivo'];

        $this->assertWaitsForLock($this->lockRow('products', $product->id), fn () => app(CheckoutService::class)->execute($cart, $checkout));

        $sale = app(CheckoutService::class)->execute($cart, $checkout);

        $this->assertSame('C-00000001', $sale->voucher_number, 'La venta que esperó no gastó un número.');
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_a_remito_waits_for_the_producer_account_being_charged(): void
    {
        $producer = Producer::factory()->withCreditLimit(5000)->create();
        $product = Product::factory()->create(['stock' => 10, 'price' => 1000]);

        $this->assertWaitsForLock(
            $this->lockRow('producers', $producer->id),
            fn () => app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'remito', 'producer_id' => $producer->id]),
        );
    }

    public function test_two_checkouts_cannot_take_the_same_voucher_number(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        DB::table('voucher_sequences')->insert(['prefix' => 'C', 'last_number' => 7, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertWaitsForLock(
            fn (Connection $other) => $other->table('voucher_sequences')->where('prefix', 'C')->lockForUpdate()->first(),
            fn () => app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'contado', 'payment_method' => 'efectivo']),
        );

        $this->assertSame(10, $product->fresh()->stock, 'La venta que no consiguió número no descontó stock.');
    }

    public function test_payments_and_credit_notes_wait_for_the_producer_account(): void
    {
        $producer = Producer::factory()->withBalance(3000)->create();

        $this->assertWaitsForLock($this->lockRow('producers', $producer->id), fn () => app(PaymentService::class)->register($producer->id, 1000, 'efectivo'));
        $this->assertWaitsForLock($this->lockRow('producers', $producer->id), fn () => app(PaymentService::class)->registerCreditNote($producer->id, 500, 'Bonificación'));

        $this->assertSame('3000.00', $producer->fresh()->balance);
    }

    public function test_restocking_and_adjusting_wait_for_the_product(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->assertWaitsForLock($this->lockRow('products', $product->id), fn () => app(StockService::class)->restock($product, 5));
        $this->assertWaitsForLock($this->lockRow('products', $product->id), fn () => app(StockService::class)->adjust($product, 'subtract', 2, 'rotura'));

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_switching_expiry_tracking_waits_for_the_product_stock(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $this->assertWaitsForLock($this->lockRow('products', $product->id), fn () => app(ProductService::class)->update($product->fresh(), ['tracks_expiry' => true]));
    }

    public function test_a_terminal_cannot_open_two_cajas_at_once(): void
    {
        $terminal = PosTerminal::factory()->create();

        $this->assertWaitsForLock($this->lockRow('pos_terminals', $terminal->id), fn () => app(CashSessionService::class)->open($terminal, User::factory()->create(), 0));
    }
}
