<?php

namespace Tests\Feature;

use App\Exceptions\CreditLimitException;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected Producer $producer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->producer = Producer::create(['name' => 'Estancia La Josefina']);
        $this->product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 100, 'price' => 200]);
    }

    protected function remito(int $quantity, array $options = []): Sale
    {
        return app(CheckoutService::class)->execute(
            [['product_id' => $this->product->id, 'quantity' => $quantity]],
            ['type' => 'remito', 'producer_id' => $this->producer->id, ...$options],
        );
    }

    protected function manualRemito(string $voucher, float $total, $date): Sale
    {
        $sale = Sale::create(['voucher_number' => $voucher, 'type' => 'remito', 'payment_method' => 'cuenta_corriente', 'producer_id' => $this->producer->id, 'total' => $total, 'paid' => 0, 'status' => 'pendiente']);
        $sale->forceFill(['created_at' => $date])->save();

        return $sale;
    }

    public function test_a_credit_note_above_the_debt_settles_every_remito_and_leaves_a_credit_balance(): void
    {
        $older = $this->manualRemito('R-00000001', 1000, now()->subDays(5));
        $newer = $this->manualRemito('R-00000002', 2000, now()->subDay());
        $this->producer->update(['balance' => 3000]);

        $creditNote = app(PaymentService::class)->registerCreditNote($this->producer->id, 3800, '  Devolución de mercadería  ');

        $this->assertSame(['3800.00', 'Devolución de mercadería'], [$creditNote->amount, $creditNote->reason]);
        $this->assertSame('-800.00', $this->producer->fresh()->balance);
        $this->assertTrue($this->producer->fresh()->hasCreditBalance());
        $this->assertSame(['pagada', '1000.00'], [$older->fresh()->status, $older->fresh()->paid]);
        $this->assertSame(['pagada', '2000.00'], [$newer->fresh()->status, $newer->fresh()->paid]);
        $this->assertSame(0, Sale::query()->unpaidRemitos()->count());
        $this->assertSame(0, $this->producer->payments()->count());
    }

    public function test_the_credit_balance_left_by_a_credit_note_pays_the_next_remito(): void
    {
        $first = $this->remito(5);
        app(PaymentService::class)->registerCreditNote($this->producer->id, 1600, 'Bonificación');

        $covered = $this->remito(3);

        $this->assertSame(['pagada', '1000.00'], [$first->fresh()->status, $first->fresh()->paid]);
        $this->assertSame(['pagada', '600.00', '600.00'], [$covered->status, $covered->total, $covered->paid]);
        $this->assertSame('0.00', $this->producer->fresh()->balance);
    }

    public function test_a_credit_balance_keeps_a_remito_within_the_credit_limit_and_is_applied(): void
    {
        $this->producer->update(['balance' => -500, 'credit_limit' => 1000]);

        $sale = $this->remito(7);

        $this->assertSame(['1400.00', '500.00', 'parcial'], [$sale->total, $sale->paid, $sale->status]);
        $this->assertSame('900.00', $this->producer->fresh()->balance);
        $this->assertSame(93, $this->product->fresh()->stock);
    }

    public function test_a_remito_that_reaches_exactly_the_credit_limit_with_a_credit_balance_goes_through(): void
    {
        $this->producer->update(['balance' => -500, 'credit_limit' => 1000]);

        $this->product->update(['price' => 300]);

        $exact = $this->remito(5);

        $this->assertSame(['1500.00', '500.00', 'parcial'], [$exact->total, $exact->paid, $exact->status]);
        $this->assertSame('1000.00', $this->producer->fresh()->balance);
    }

    public function test_a_remito_whose_net_debt_exceeds_the_limit_despite_the_credit_balance_is_rejected(): void
    {
        $this->producer->update(['balance' => -500, 'credit_limit' => 1000]);

        try {
            $this->remito(8);
            $this->fail('El remito con deuda neta de 1100 tendría que superar el límite de 1000.');
        } catch (CreditLimitException $e) {
            $this->assertSame('El remito de $ 1.600,00 supera el límite de crédito de Estancia La Josefina: límite $ 1.000,00, saldo actual $ -500,00.', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame('-500.00', $this->producer->fresh()->balance);
        $this->assertSame(100, $this->product->fresh()->stock);
    }

    public function test_a_remito_over_the_limit_with_a_credit_balance_applies_the_credit_when_confirmed(): void
    {
        $this->producer->update(['balance' => -500, 'credit_limit' => 1000]);

        $sale = $this->remito(8, ['allow_over_limit' => true]);

        $this->assertSame(['1600.00', '500.00', 'parcial'], [$sale->total, $sale->paid, $sale->status]);
        $this->assertSame('1100.00', $this->producer->fresh()->balance);
    }

    public function test_available_credit_is_null_without_a_limit(): void
    {
        $this->producer->update(['balance' => 700]);

        $this->assertNull($this->producer->fresh()->availableCredit());
        $this->assertFalse($this->producer->fresh()->exceedsCreditLimit(1000000));
    }

    public function test_available_credit_is_the_limit_minus_the_debt(): void
    {
        $this->producer->update(['balance' => 350.25, 'credit_limit' => 1000]);

        $this->assertSame(649.75, $this->producer->fresh()->availableCredit());
    }

    public function test_available_credit_grows_with_a_credit_balance(): void
    {
        $this->producer->update(['balance' => -500, 'credit_limit' => 1000]);

        $this->assertSame(1500.0, $this->producer->fresh()->availableCredit());
    }

    public function test_available_credit_is_negative_when_the_debt_is_over_the_limit(): void
    {
        $this->producer->update(['balance' => 1100, 'credit_limit' => 1000]);

        $this->assertSame(-100.0, $this->producer->fresh()->availableCredit());
    }
}
