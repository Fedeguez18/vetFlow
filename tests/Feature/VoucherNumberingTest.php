<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\Product;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $checkout;

    protected Product $product;

    protected Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkout = app(CheckoutService::class);
        $this->product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 100, 'price' => 500]);
        $this->producer = Producer::create(['name' => 'Estancia La Josefina']);
    }

    protected function sell(string $type, int $quantity = 1): string
    {
        return $this->checkout->execute(
            [['product_id' => $this->product->id, 'quantity' => $quantity]],
            $type === 'remito'
                ? ['type' => 'remito', 'producer_id' => $this->producer->id]
                : ['type' => 'contado', 'payment_method' => 'efectivo'],
        )->voucher_number;
    }

    public function test_each_voucher_type_has_its_own_correlative_counter(): void
    {
        $numbers = [$this->sell('contado'), $this->sell('remito'), $this->sell('contado'), $this->sell('remito')];

        $this->assertSame(['C-00000001', 'R-00000001', 'C-00000002', 'R-00000002'], $numbers);
    }

    public function test_the_preview_matches_the_number_that_gets_assigned(): void
    {
        $this->sell('remito');

        $this->assertSame('C-00000001', $this->checkout->nextVoucherNumber('contado'));
        $this->assertSame('R-00000002', $this->checkout->nextVoucherNumber('remito'));
        $this->assertSame('R-00000002', $this->sell('remito'));
    }

    public function test_a_failed_sale_does_not_consume_a_number(): void
    {
        $this->assertSame('C-00000001', $this->sell('contado'));

        try {
            $this->sell('contado', 1000);
            $this->fail('La venta sin stock tendría que fallar.');
        } catch (Exception) {
            // Expected: not enough stock.
        }

        $this->assertSame('C-00000002', $this->sell('contado'));
    }
}
