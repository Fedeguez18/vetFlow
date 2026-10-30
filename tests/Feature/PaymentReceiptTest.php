<?php

namespace Tests\Feature;

use App\Livewire\Erp\AccountsReceivable;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountDocumentService;
use App\Services\CheckoutService;
use App\Services\PaymentAllocationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected Producer $producer;

    /**
     * @var array<int, Sale>
     */
    protected array $remitos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create(['name' => 'Establecimiento Don Ramón', 'business_name' => 'Ramón Gutiérrez', 'cuit' => '20-17654321-9', 'balance' => 500000]);
        $this->remitos = [
            Sale::factory()->remito()->for($this->producer)->create(['total' => 150000, 'created_at' => now()->subDays(20)]),
            Sale::factory()->remito()->for($this->producer)->create(['total' => 200000, 'created_at' => now()->subDays(10)]),
            Sale::factory()->remito()->for($this->producer)->create(['total' => 150000, 'created_at' => now()->subDays(5)]),
        ];
    }

    public function test_a_partial_payment_receipt_shows_which_remitos_it_settled(): void
    {
        $payment = app(PaymentService::class)->register($this->producer->id, 300000, 'transferencia', 'Op. 4455');

        $receipt = app(PaymentAllocationService::class)->forPayment($payment);

        $this->assertSame(
            [[$this->remitos[0]->voucher_number, 150000.0, 0.0], [$this->remitos[1]->voucher_number, 150000.0, 50000.0]],
            $receipt['allocations']->map(fn ($allocation) => [$allocation['sale']->voucher_number, $allocation['applied'], $allocation['pending']])->all(),
        );
        $this->assertSame([0.0, 500000.0, 200000.0], [$receipt['unapplied'], $receipt['balance_before'], $receipt['balance_after']]);
        $this->assertSame('150000.00', $this->remitos[1]->fresh()->paid);
    }

    public function test_an_old_receipt_keeps_its_allocation_after_later_payments(): void
    {
        $first = app(PaymentService::class)->register($this->producer->id, 300000, 'efectivo');
        $first->forceFill(['created_at' => now()->subDays(2)])->save();
        app(PaymentService::class)->register($this->producer->id, 200000, 'efectivo');

        $receipt = app(PaymentAllocationService::class)->forPayment($first->fresh());

        $this->assertSame([$this->remitos[0]->voucher_number, $this->remitos[1]->voucher_number], $receipt['allocations']->map(fn ($allocation) => $allocation['sale']->voucher_number)->all());
        $this->assertSame(50000.0, $receipt['allocations'][1]['pending']);
        $this->assertSame(200000.0, $receipt['balance_after']);
    }

    public function test_an_overpayment_receipt_shows_the_credit_left(): void
    {
        $payment = app(PaymentService::class)->register($this->producer->id, 520000.50, 'cheque');

        $receipt = app(PaymentAllocationService::class)->forPayment($payment);

        $this->assertSame(3, $receipt['allocations']->count());
        $this->assertSame([20000.5, -20000.5], [$receipt['unapplied'], $receipt['balance_after']]);
    }

    public function test_the_credit_left_by_a_payment_is_used_by_the_next_remito(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 10, 'price' => 30000]);
        app(PaymentService::class)->register($this->producer->id, 520000, 'efectivo');
        $sale = app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'remito', 'producer_id' => $this->producer->id]);
        $this->assertSame('R-00000004', $sale->voucher_number, 'El checkout sigue la numeración de los remitos creados por la factory.');

        $next = app(PaymentService::class)->register($this->producer->id, 10000, 'efectivo');
        $receipt = app(PaymentAllocationService::class)->forPayment($next);

        $this->assertSame([[$sale->voucher_number, 10000.0, 0.0]], $receipt['allocations']->map(fn ($allocation) => [$allocation['sale']->voucher_number, $allocation['applied'], $allocation['pending']])->all());
        $this->assertSame('pagada', $sale->fresh()->status);
        $this->assertSame(0.0, $receipt['balance_after']);
    }

    public function test_the_receipt_view_has_the_amount_in_words_and_the_allocation(): void
    {
        $payment = app(PaymentService::class)->register($this->producer->id, 300000, 'transferencia', 'Op. 4455');

        $html = view('pdf.receipt', app(AccountDocumentService::class)->receipt($payment))->render();

        $this->assertStringContainsString('Recibo de Pago '.$payment->voucher_number, $html);
        $this->assertStringContainsString('Ramón Gutiérrez', $html);
        $this->assertStringContainsString('PESOS TRESCIENTOS MIL CON 00/100', $html);
        $this->assertStringContainsString('Transferencia · Op. 4455', $html);
        $this->assertStringContainsString('Remito '.$this->remitos[1]->voucher_number, $html);
        $this->assertStringContainsString('$ 50.000,00', $html);
        $this->assertStringContainsString('$ 200.000,00', $html);
        $this->assertStringNotContainsString($this->remitos[2]->voucher_number, $html);
    }

    public function test_a_success_modal_offers_the_receipt_after_registering_a_payment(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $this->producer->id)
            ->call('openPaymentModal')
            ->set('paymentAmount', '300.000')
            ->set('paymentMethod', 'transferencia')
            ->call('registerPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false)
            ->assertSet('alert', null)
            ->assertSee('Pago registrado')
            ->assertSee('Imprimir recibo (PDF)');

        $payment = Payment::sole();
        $component->assertSee(route('erp.payments.receipt-pdf', $payment));

        $this->assertSame([
            'id' => $payment->id,
            'voucher' => $payment->voucher_number,
            'amount' => 300000.0,
            'method' => 'Transferencia',
            'balance' => 200000.0,
            'allocations' => [
                ['voucher' => $this->remitos[0]->voucher_number, 'applied' => 150000.0, 'pending' => 0.0],
                ['voucher' => $this->remitos[1]->voucher_number, 'applied' => 150000.0, 'pending' => 50000.0],
            ],
            'unapplied' => 0.0,
        ], $component->get('paymentSuccess'));

        $component->call('closePaymentSuccess')->assertSet('paymentSuccess', null)->assertDontSee('Pago registrado');

        $this->get(route('erp.payments.receipt-pdf', $payment))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_receipts_stay_available_next_to_each_remito_and_movement(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $payment = app(PaymentService::class)->register($this->producer->id, 300000, 'efectivo');

        $component = Livewire::test(AccountsReceivable::class)->call('selectProducer', $this->producer->id);
        $receipts = $component->instance()->receiptsBySale;

        $this->assertSame([[$payment->id, 150000.0]], $receipts[$this->remitos[1]->id]->map(fn ($receipt) => [$receipt['payment']->id, $receipt['applied']])->all());
        $this->assertTrue($receipts[$this->remitos[2]->id]->isEmpty());

        $component
            ->assertSee('Recibo '.$payment->voucher_number)
            ->assertSee('Recibo PDF')
            ->assertSee(route('erp.payments.receipt-pdf', $payment))
            ->assertSee(route('erp.remitos.pdf', $this->remitos[2]));
    }

    public function test_guests_cannot_download_receipts(): void
    {
        $payment = app(PaymentService::class)->register($this->producer->id, 1000, 'efectivo');

        $this->get(route('erp.payments.receipt-pdf', $payment))->assertRedirect(route('login'));
    }
}
