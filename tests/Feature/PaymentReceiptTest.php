<?php

namespace Tests\Feature;

use App\Livewire\Erp\AccountsReceivable;
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

        $this->producer = Producer::create(['name' => 'Establecimiento Don Ramón', 'business_name' => 'Ramón Gutiérrez', 'cuit' => '20-17654321-9', 'balance' => 500000]);
        $this->remitos = [
            $this->remito('R-00000101', 150000, now()->subDays(20)),
            $this->remito('R-00000102', 200000, now()->subDays(10)),
            $this->remito('R-00000103', 150000, now()->subDays(5)),
        ];
    }

    protected function remito(string $voucher, float $total, $date): Sale
    {
        $sale = Sale::create(['voucher_number' => $voucher, 'type' => 'remito', 'payment_method' => 'cuenta_corriente', 'producer_id' => $this->producer->id, 'total' => $total, 'paid' => 0, 'status' => 'pendiente']);
        $sale->forceFill(['created_at' => $date])->save();

        return $sale;
    }

    public function test_a_partial_payment_receipt_shows_which_remitos_it_settled(): void
    {
        $payment = app(PaymentService::class)->register($this->producer->id, 300000, 'transferencia', 'Op. 4455');

        $receipt = app(PaymentAllocationService::class)->forPayment($payment);

        $this->assertSame(
            [['R-00000101', 150000.0, 0.0], ['R-00000102', 150000.0, 50000.0]],
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

        $this->assertSame(['R-00000101', 'R-00000102'], $receipt['allocations']->map(fn ($allocation) => $allocation['sale']->voucher_number)->all());
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
        app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'remito', 'producer_id' => $this->producer->id]);

        $next = app(PaymentService::class)->register($this->producer->id, 10000, 'efectivo');
        $receipt = app(PaymentAllocationService::class)->forPayment($next);

        $this->assertSame([['R-00000001', 10000.0, 0.0]], $receipt['allocations']->map(fn ($allocation) => [$allocation['sale']->voucher_number, $allocation['applied'], $allocation['pending']])->all());
        $this->assertSame('pagada', Sale::where('voucher_number', 'R-00000001')->latest('id')->first()->status);
        $this->assertSame(0.0, $receipt['balance_after']);
    }

    public function test_the_receipt_view_has_the_amount_in_words_and_the_allocation(): void
    {
        $payment = app(PaymentService::class)->register($this->producer->id, 300000, 'transferencia', 'Op. 4455');

        $html = view('pdf.receipt', app(AccountDocumentService::class)->receipt($payment))->render();

        $this->assertStringContainsString('Recibo de Pago P-00000001', $html);
        $this->assertStringContainsString('Ramón Gutiérrez', $html);
        $this->assertStringContainsString('PESOS TRESCIENTOS MIL CON 00/100', $html);
        $this->assertStringContainsString('Transferencia · Op. 4455', $html);
        $this->assertStringContainsString('Remito R-00000102', $html);
        $this->assertStringContainsString('$ 50.000,00', $html);
        $this->assertStringContainsString('$ 200.000,00', $html);
        $this->assertStringNotContainsString('R-00000103', $html);
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
            ->assertSee('Imprimir recibo (PDF)')
            ->assertSee(route('erp.payments.receipt-pdf', 1));

        $this->assertSame([
            'id' => 1,
            'voucher' => 'P-00000001',
            'amount' => 300000.0,
            'method' => 'Transferencia',
            'balance' => 200000.0,
            'allocations' => [
                ['voucher' => 'R-00000101', 'applied' => 150000.0, 'pending' => 0.0],
                ['voucher' => 'R-00000102', 'applied' => 150000.0, 'pending' => 50000.0],
            ],
            'unapplied' => 0.0,
        ], $component->get('paymentSuccess'));

        $component->call('closePaymentSuccess')->assertSet('paymentSuccess', null)->assertDontSee('Pago registrado');

        $this->get(route('erp.payments.receipt-pdf', 1))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_receipts_stay_available_next_to_each_remito_and_movement(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $payment = app(PaymentService::class)->register($this->producer->id, 300000, 'efectivo');

        $component = Livewire::test(AccountsReceivable::class)->call('selectProducer', $this->producer->id);
        $receipts = $component->instance()->receiptsBySale;

        $this->assertSame([[1, 150000.0]], $receipts[$this->remitos[1]->id]->map(fn ($receipt) => [$receipt['payment']->id, $receipt['applied']])->all());
        $this->assertTrue($receipts[$this->remitos[2]->id]->isEmpty());

        $component
            ->assertSee('Recibo P-00000001')
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
