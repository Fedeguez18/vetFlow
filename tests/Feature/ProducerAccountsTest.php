<?php

namespace Tests\Feature;

use App\Livewire\Erp\AccountsReceivable;
use App\Livewire\Pos\PosDesk;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProducerAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_a_new_account_is_created_and_selected(): void
    {
        $component = Livewire::test(AccountsReceivable::class)
            ->call('openCreateModal')
            ->assertSet('showProducerModal', true)
            ->set('form.name', '  Estancia La Josefina ')
            ->set('form.business_name', 'Roberto Méndez')
            ->set('form.cuit', '20284890294')
            ->set('form.phone', '358 412-3344')
            ->set('form.iva_condition', 'Responsable Inscripto')
            ->set('form.discount_percent', '5,5')
            ->set('form.credit_limit', '1.500.000')
            ->call('saveProducer')
            ->assertHasNoErrors()
            ->assertSet('showProducerModal', false)
            ->assertSet('alert', 'Cuenta de Estancia La Josefina creada.');

        $producer = Producer::sole();

        $this->assertSame(['Estancia La Josefina', '20-28489029-4', '5.50', '0.00', '1500000.00', true], [$producer->name, $producer->cuit, $producer->discount_percent, $producer->balance, $producer->credit_limit, $producer->is_active]);
        $component->assertSet('producerId', $producer->id);
    }

    public function test_an_account_is_edited_and_the_credit_limit_can_be_removed(): void
    {
        $producer = Producer::create(['name' => 'Haras San Jorge', 'cuit' => '20-23456789-0', 'credit_limit' => 50000]);

        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $producer->id)
            ->call('openEditModal')
            ->assertSet('form.name', 'Haras San Jorge')
            ->assertSet('form.credit_limit', '50000')
            ->set('form.phone', '3582 401-776')
            ->set('form.credit_limit', '')
            ->call('saveProducer')
            ->assertHasNoErrors()
            ->assertSet('alert', 'Datos de Haras San Jorge guardados.');

        $producer->refresh();

        $this->assertSame(['3582 401-776', null, '20-23456789-0'], [$producer->phone, $producer->credit_limit, $producer->cuit]);
    }

    public function test_creating_an_account_validates_the_data(): void
    {
        Producer::create(['name' => 'Estancia La Josefina', 'cuit' => '20-28489029-4']);

        Livewire::test(AccountsReceivable::class)
            ->call('openCreateModal')
            ->call('saveProducer')
            ->assertHasErrors(['form.name' => 'required'])
            ->set('form.name', 'Estancia La Josefina')
            ->set('form.cuit', '20-28489029-4')
            ->set('form.discount_percent', '150')
            ->set('form.credit_limit', '-10')
            ->call('saveProducer')
            ->assertHasErrors(['form.name' => 'unique', 'form.cuit' => 'unique', 'form.discount_percent' => 'max', 'form.credit_limit' => 'min'])
            ->set('form.cuit', '123')
            ->call('saveProducer')
            ->assertHasErrors(['form.cuit' => 'regex'])
            ->assertSet('showProducerModal', true);

        $this->assertSame(1, Producer::count());
    }

    public function test_an_account_without_balance_is_given_a_baja_and_hidden(): void
    {
        $producer = Producer::create(['name' => 'Haras San Jorge']);

        $component = Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $producer->id)
            ->call('deactivateProducer')
            ->assertSet('alert', 'Cuenta de Haras San Jorge dada de baja.')
            ->assertSet('producerId', null);

        $this->assertFalse($producer->fresh()->is_active);
        $this->assertSame([], $component->instance()->producers->pluck('name')->all());
        $this->assertSame(['Haras San Jorge'], $component->set('showInactive', true)->instance()->producers->pluck('name')->all());
        $this->assertSame([], Livewire::test(PosDesk::class)->instance()->producers->pluck('name')->all());
    }

    public function test_an_account_with_balance_cannot_be_given_a_baja(): void
    {
        $producer = Producer::create(['name' => 'Estancia La Josefina', 'balance' => 1500]);

        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $producer->id)
            ->call('deactivateProducer')
            ->assertSet('alertType', 'error')
            ->assertSet('alert', 'No se puede dar de baja una cuenta con saldo ($ 1.500,00). Cancelá la deuda primero.');

        $this->assertTrue($producer->fresh()->is_active);
    }

    public function test_the_walk_in_account_cannot_be_given_a_baja(): void
    {
        $walkIn = Producer::create(['name' => Producer::WALK_IN]);

        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $walkIn->id)
            ->call('deactivateProducer')
            ->assertSet('alertType', 'error');

        $this->assertTrue($walkIn->fresh()->is_active);
    }

    public function test_a_closed_account_can_be_reactivated(): void
    {
        $producer = Producer::create(['name' => 'Haras San Jorge', 'is_active' => false]);

        Livewire::test(AccountsReceivable::class)
            ->set('showInactive', true)
            ->call('selectProducer', $producer->id)
            ->call('reactivateProducer')
            ->assertSet('alert', 'Cuenta de Haras San Jorge reactivada.');

        $this->assertTrue($producer->fresh()->is_active);
    }

    public function test_a_remito_cannot_be_charged_to_a_closed_account(): void
    {
        $producer = Producer::create(['name' => 'Haras San Jorge', 'is_active' => false]);
        $product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 10, 'price' => 1000]);

        try {
            app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 1]], ['type' => 'remito', 'producer_id' => $producer->id]);
            $this->fail('El remito a una cuenta dada de baja tendría que fallar.');
        } catch (Exception $e) {
            $this->assertSame('La cuenta de Haras San Jorge está dada de baja.', $e->getMessage());
        }

        $this->assertSame('0.00', $producer->fresh()->balance);
        $this->assertSame(10, $product->fresh()->stock);
    }
}
