<?php

namespace Tests\Feature;

use App\Exceptions\CreditLimitException;
use App\Livewire\Erp\AccountsReceivable;
use App\Livewire\Pos\PosDesk;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountStatementService;
use App\Services\CashAuditService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use App\Services\WhatsAppService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrentAccountCycleTest extends TestCase
{
    use RefreshDatabase;

    protected Producer $producer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->producer = Producer::create(['name' => 'Estancia La Josefina']);
        $this->product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 100, 'price' => 1000]);
    }

    protected function remito(int $quantity, array $options = []): Sale
    {
        return app(CheckoutService::class)->execute(
            [['product_id' => $this->product->id, 'quantity' => $quantity]],
            ['type' => 'remito', 'producer_id' => $this->producer->id, ...$options],
        );
    }

    public function test_a_credit_balance_pays_the_next_remito(): void
    {
        app(PaymentService::class)->register($this->producer->id, 500, 'transferencia');

        $partial = $this->remito(2);

        $this->assertSame(['parcial', '500.00'], [$partial->status, $partial->paid]);
        $this->assertSame('1500.00', $this->producer->fresh()->balance);

        app(PaymentService::class)->register($this->producer->id, 4000, 'efectivo');
        $covered = $this->remito(1);

        $this->assertSame(['pagada', '1000.00'], [$covered->status, $covered->paid]);
        $this->assertSame('-1500.00', $this->producer->fresh()->balance);
    }

    public function test_a_remito_above_the_credit_limit_needs_confirmation(): void
    {
        $this->producer->update(['credit_limit' => 2500, 'balance' => 1000]);

        try {
            $this->remito(2);
            $this->fail('El remito que supera el límite tendría que pedir confirmación.');
        } catch (CreditLimitException $e) {
            $this->assertSame('El remito de $ 2.000,00 supera el límite de crédito de Estancia La Josefina: límite $ 2.500,00, saldo actual $ 1.000,00.', $e->getMessage());
        }

        $this->assertSame(0, Sale::count());
        $this->assertSame(100, $this->product->fresh()->stock);

        $this->remito(2, ['allow_over_limit' => true]);

        $this->assertSame('3000.00', $this->producer->fresh()->balance);
    }

    public function test_a_remito_within_the_credit_limit_goes_through(): void
    {
        $this->producer->update(['credit_limit' => 2000]);

        $this->remito(2);

        $this->assertSame('2000.00', $this->producer->fresh()->balance);
    }

    public function test_the_pos_warns_and_emits_the_remito_after_confirming(): void
    {
        $this->producer->update(['credit_limit' => 500]);

        $pos = Livewire::test(PosDesk::class)
            ->call('addProduct', $this->product->id)
            ->set('paymentMethod', 'cuenta_corriente')
            ->call('selectProducer', $this->producer->id)
            ->assertSet('exceedsCreditLimit', true)
            ->call('confirm')
            ->assertSet('creditLimitWarning', 'El remito de $ 1.000,00 supera el límite de crédito de Estancia La Josefina: límite $ 500,00, saldo actual $ 0,00.');

        $this->assertSame(0, Sale::count());

        $pos->call('confirm', true)->assertSet('creditLimitWarning', null)->assertSet('cart', []);

        $this->assertSame('1000.00', $this->producer->fresh()->balance);
    }

    public function test_confirming_over_the_limit_without_the_warning_is_ignored(): void
    {
        $this->producer->update(['credit_limit' => 500]);

        Livewire::test(PosDesk::class)
            ->call('addProduct', $this->product->id)
            ->set('paymentMethod', 'cuenta_corriente')
            ->call('selectProducer', $this->producer->id)
            ->call('confirm', true)
            ->assertNotSet('creditLimitWarning', null);

        $this->assertSame(0, Sale::count());
    }

    public function test_a_credit_note_lowers_the_debt_without_entering_the_cash_audit(): void
    {
        $remito = $this->remito(3);

        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $this->producer->id)
            ->call('openCreditNoteModal')
            ->assertSet('showCreditNoteModal', true)
            ->set('creditNoteAmount', '500')
            ->call('registerCreditNote')
            ->assertHasErrors(['creditNoteReason' => 'required'])
            ->set('creditNoteReason', 'Bonificación por volumen')
            ->assertSet('balanceAfterCreditNote', 2500.0)
            ->call('registerCreditNote')
            ->assertHasNoErrors()
            ->assertSet('showCreditNoteModal', false)
            ->assertSet('alert', 'Nota de crédito NC-00000001 por $ 500,00 emitida. Nuevo saldo: $ 2.500,00.');

        $this->assertSame('2500.00', $this->producer->fresh()->balance);
        $this->assertSame(['parcial', '500.00'], [$remito->fresh()->status, $remito->fresh()->paid]);
        $this->assertSame('Bonificación por volumen', $this->producer->creditNotes()->sole()->reason);

        $summary = app(CashAuditService::class)->summary(app(CashAuditService::class)->entries(CarbonImmutable::today()));

        $this->assertSame([0.0, 0], [$summary['collected'], $summary['payments']]);
    }

    public function test_the_statement_lists_debe_haber_and_running_balance(): void
    {
        $old = $this->remito(1);
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();
        $this->producer->payments()->create(['amount' => 400, 'payment_method' => 'efectivo'])
            ->forceFill(['created_at' => now()->subMonths(2)->addDay()])->save();
        $this->producer->update(['balance' => 600]);

        $this->remito(2);
        app(PaymentService::class)->register($this->producer->id, 3000, 'transferencia', 'adelanto');
        app(PaymentService::class)->registerCreditNote($this->producer->id, 100, 'Error de precio');

        $statement = app(AccountStatementService::class)->statement($this->producer->fresh(), CarbonImmutable::today()->subMonth());

        $this->assertSame(600.0, $statement['opening']);
        $this->assertSame(['remito', 'pago', 'nota_credito'], $statement['rows']->pluck('kind')->all());
        $this->assertSame([2600.0, -400.0, -500.0], $statement['rows']->pluck('balance')->all());
        $this->assertSame('Transferencia · adelanto', $statement['rows'][1]['detail']);
        $this->assertSame([2000.0, 3100.0, -500.0], [$statement['debit'], $statement['credit'], $statement['closing']]);
        $this->assertSame((float) $this->producer->fresh()->balance, $statement['closing']);

        $all = app(AccountStatementService::class)->statement($this->producer->fresh());

        $this->assertSame([0.0, 5, -500.0], [$all['opening'], $all['rows']->count(), $all['closing']]);
    }

    public function test_the_screen_shows_the_credit_balance(): void
    {
        app(PaymentService::class)->register($this->producer->id, 250, 'efectivo');

        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $this->producer->id)
            ->assertSee('Saldo a favor (acreedor)')
            ->assertSee('$ 250,00 a favor')
            ->call('deactivateProducer')
            ->assertSet('alert', 'No se puede dar de baja una cuenta con saldo a favor ($ 250,00).');

        $url = app(WhatsAppService::class)->balanceReminderUrl($this->producer->fresh(), collect());

        $this->assertStringContainsString('tiene un saldo a favor en cuenta corriente de *$ 250,00*.', rawurldecode($url));
    }
}
