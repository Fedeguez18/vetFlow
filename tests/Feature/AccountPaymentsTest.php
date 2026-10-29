<?php

namespace Tests\Feature;

use App\Exceptions\PaymentException;
use App\Livewire\Erp\AccountsReceivable;
use App\Models\Producer;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected Producer $producer;

    protected Sale $older;

    protected Sale $newer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->producer = Producer::create(['name' => 'Estancia La Josefina', 'phone' => '(0358) 15-464-2210', 'balance' => 3000]);
        $this->older = $this->remito('R-00000001', 1000, now()->subDays(5));
        $this->newer = $this->remito('R-00000002', 2000, now()->subDay());
    }

    protected function remito(string $voucher, float $total, $date): Sale
    {
        $sale = Sale::create(['voucher_number' => $voucher, 'type' => 'remito', 'payment_method' => 'cuenta_corriente', 'producer_id' => $this->producer->id, 'total' => $total, 'paid' => 0, 'status' => 'pendiente']);
        $sale->forceFill(['created_at' => $date])->save();

        return $sale;
    }

    public function test_a_partial_payment_settles_the_oldest_remito_first(): void
    {
        app(PaymentService::class)->register($this->producer->id, 1500, 'efectivo', '  entrega  ');

        $this->assertSame('1500.00', $this->producer->fresh()->balance);
        $this->assertSame(['pagada', '1000.00'], [$this->older->fresh()->status, $this->older->fresh()->paid]);
        $this->assertSame(['parcial', '500.00'], [$this->newer->fresh()->status, $this->newer->fresh()->paid]);
        $this->assertSame('entrega', $this->producer->payments()->sole()->notes);
    }

    public function test_a_payment_with_cents_settles_the_older_remito_and_leaves_the_exact_rest(): void
    {
        $this->older->update(['total' => 27879.15]);
        $this->newer->update(['total' => 73079.65]);
        $this->producer->update(['balance' => 100958.80]);

        app(PaymentService::class)->register($this->producer->id, 100757.80, 'transferencia');

        $this->assertSame(['pagada', '27879.15'], [$this->older->fresh()->status, $this->older->fresh()->paid]);
        $this->assertSame(['parcial', '72878.65'], [$this->newer->fresh()->status, $this->newer->fresh()->paid]);
        $this->assertSame('201.00', $this->producer->fresh()->balance);
    }

    public function test_paying_the_full_balance_settles_every_remito(): void
    {
        app(PaymentService::class)->register($this->producer->id, 3000, 'transferencia');

        $this->assertSame('0.00', $this->producer->fresh()->balance);
        $this->assertSame(0, Sale::whereIn('status', Sale::UNPAID_STATUSES)->count());
    }

    public function test_a_payment_above_the_balance_leaves_a_credit_balance(): void
    {
        app(PaymentService::class)->register($this->producer->id, 3500, 'efectivo');

        $this->assertSame('-500.00', $this->producer->fresh()->balance);
        $this->assertTrue($this->producer->fresh()->hasCreditBalance());
        $this->assertSame(0, Sale::whereIn('status', Sale::UNPAID_STATUSES)->count());
    }

    public function test_a_zero_payment_is_rejected_without_changes(): void
    {
        try {
            app(PaymentService::class)->register($this->producer->id, 0, 'efectivo');
            $this->fail('El pago en cero tendría que fallar.');
        } catch (PaymentException $e) {
            $this->assertSame('El monto tiene que ser mayor a cero.', $e->getMessage());
        }

        $this->assertSame('3000.00', $this->producer->fresh()->balance);
        $this->assertSame(0, $this->producer->payments()->count());
    }

    public function test_a_payment_with_an_unknown_method_is_rejected_without_changes(): void
    {
        try {
            app(PaymentService::class)->register($this->producer->id, 1000, 'bitcoin');
            $this->fail('El medio de pago desconocido tendría que fallar.');
        } catch (PaymentException $e) {
            $this->assertSame('Elegí un medio de pago válido.', $e->getMessage());
        }

        $this->assertSame('3000.00', $this->producer->fresh()->balance);
        $this->assertSame(0, $this->producer->payments()->count());
    }

    public function test_an_inactive_account_rejects_payments_and_credit_notes_without_changes(): void
    {
        $this->producer->update(['is_active' => false]);

        foreach ([
            fn () => app(PaymentService::class)->register($this->producer->id, 1000, 'efectivo'),
            fn () => app(PaymentService::class)->registerCreditNote($this->producer->id, 1000, 'Bonificación'),
        ] as $movement) {
            try {
                $movement();
                $this->fail('La cuenta dada de baja tendría que rechazar el movimiento.');
            } catch (PaymentException $e) {
                $this->assertSame('La cuenta de Estancia La Josefina está dada de baja. Reactivala para registrar movimientos.', $e->getMessage());
            }
        }

        $this->assertSame('3000.00', $this->producer->fresh()->balance);
        $this->assertSame(0, $this->producer->payments()->count());
        $this->assertSame(0, $this->producer->creditNotes()->count());
        $this->assertSame(0, Sale::where('status', 'pendiente')->where('paid', '>', 0)->count());
    }

    public function test_the_screen_rejects_a_payment_to_an_inactive_account_skipping_the_modal(): void
    {
        $component = Livewire::test(AccountsReceivable::class)
            ->set('showInactive', true)
            ->call('selectProducer', $this->producer->id);

        $this->producer->update(['is_active' => false]);

        $component->set('paymentAmount', '1000')
            ->call('registerPayment')
            ->assertHasErrors('paymentAmount')
            ->set('creditNoteAmount', '1000')
            ->set('creditNoteReason', 'Bonificación')
            ->call('registerCreditNote')
            ->assertHasErrors('creditNoteAmount');

        $this->assertSame('3000.00', $this->producer->fresh()->balance);
    }

    public function test_the_screen_registers_a_payment_typed_in_local_format(): void
    {
        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $this->producer->id)
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->set('paymentAmount', '1.500,50')
            ->assertSet('balanceAfterPayment', 1499.5)
            ->call('registerPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false)
            ->assertSet('paymentSuccess.amount', 1500.5)
            ->assertSet('paymentSuccess.balance', 1499.5)
            ->assertSee('$ 1.500,50')
            ->assertSee('$ 1.499,50');

        $this->assertSame('1499.50', $this->producer->fresh()->balance);
    }

    public function test_the_screen_shows_the_errors(): void
    {
        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $this->producer->id)
            ->call('openPaymentModal')
            ->set('paymentAmount', 'abc')
            ->call('registerPayment')
            ->assertHasErrors(['paymentAmount' => 'numeric'])
            ->set('paymentAmount', '0')
            ->call('registerPayment')
            ->assertHasErrors(['paymentAmount' => 'min'])
            ->assertSet('showPaymentModal', true);

        $this->assertSame(0, $this->producer->payments()->count());
    }

    public function test_the_producer_list_filters_and_counts_unpaid_remitos(): void
    {
        Producer::create(['name' => 'Haras El Ombú', 'cuit' => '20-12345678-9']);

        $component = Livewire::test(AccountsReceivable::class)->set('search', 'josefina');

        $this->assertSame(['Estancia La Josefina'], $component->instance()->producers->pluck('name')->all());
        $this->assertSame(2, $component->instance()->producers->first()->unpaid_count);
        $this->assertSame(['Haras El Ombú'], $component->set('search', '12345678')->instance()->producers->pluck('name')->all());
    }

    public function test_the_whatsapp_reminder_lists_the_debt(): void
    {
        app(PaymentService::class)->register($this->producer->id, 1500, 'efectivo');

        $url = app(WhatsAppService::class)->balanceReminderUrl($this->producer->fresh(), Sale::query()->unpaidRemitos()->get());
        $text = rawurldecode(substr($url, strpos($url, '?text=') + 6));

        $this->assertStringStartsWith('https://wa.me/549358154642210?text=', $url);
        $this->assertStringContainsString('es de *$ 1.500,00*.', $text);
        $this->assertStringContainsString('• R-00000002 del '.$this->newer->created_at->format('d/m/Y').': $ 1.500,00 (pendiente de un total de $ 2.000,00)', $text);
        $this->assertStringNotContainsString('R-00000001', $text);
    }

    public function test_phones_are_converted_to_the_whatsapp_format(): void
    {
        $whatsapp = app(WhatsAppService::class);

        $this->assertSame('5493584642210', $whatsapp->internationalPhone('(0358) 464-2210'));
        $this->assertSame('5493584642210', $whatsapp->internationalPhone('+54 9 358 464-2210'));
        $this->assertNull($whatsapp->internationalPhone(''));
    }
}
