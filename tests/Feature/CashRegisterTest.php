<?php

namespace Tests\Feature;

use App\Livewire\Erp\DailyAudit;
use App\Livewire\Erp\UserManagement;
use App\Livewire\Pos\PosDesk;
use App\Livewire\Pos\PosLock;
use App\Livewire\Pos\TerminalSetup;
use App\Models\CashSession;
use App\Models\PosTerminal;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_enables_the_pc_and_their_session_is_closed(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(TerminalSetup::class)
            ->set('name', 'Mostrador 1')
            ->call('enable')
            ->assertRedirect(route('pos.lock'));

        $terminal = PosTerminal::sole();
        $this->assertSame('Mostrador 1', $terminal->name);
        $this->assertTrue($admin->is($terminal->enabledBy));
        $this->assertGuest();
    }

    public function test_a_seller_cannot_enable_a_pc(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(TerminalSetup::class)->call('enable')->assertForbidden();

        $this->assertSame(0, PosTerminal::count());
    }

    public function test_a_seller_logged_in_on_the_pc_can_switch_to_an_admin_to_enable_it(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['name' => 'Agustin']));

        $this->get(route('pos.setup'))->assertOk()->assertSee('Estás con la sesión de Agustin (Vendedor)');

        Livewire::test(TerminalSetup::class)
            ->call('switchToAdmin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(route('pos.setup'), session('url.intended'));
    }

    public function test_the_code_opens_the_caja_in_the_users_name(): void
    {
        $seller = User::factory()->create(['name' => 'Laura']);
        $code = app(UserService::class)->generatePosCode($seller);
        $terminal = $this->onPosTerminal();

        $this->get(route('pos.desk'))->assertRedirect(route('pos.lock'));

        Livewire::test(PosLock::class)
            ->set('code', $code)
            ->call('unlock')
            ->assertHasNoErrors()
            ->assertSee('Hola, Laura')
            ->set('openingAmount', '5.000')
            ->call('openCashRegister')
            ->assertRedirect(route('pos.desk'));

        $cashSession = $terminal->openCashSession;
        $this->assertTrue($seller->is($cashSession->user));
        $this->assertSame('5000.00', $cashSession->opening_amount);
        $this->assertGuest();
    }

    public function test_a_wrong_code_is_rejected_and_the_terminal_locks_after_five_attempts(): void
    {
        $seller = User::factory()->create();
        $code = app(UserService::class)->generatePosCode($seller);
        $this->onPosTerminal();
        $wrong = $code === '000000' ? '000001' : '000000';

        $lock = Livewire::test(PosLock::class);

        foreach (range(1, PosLock::MAX_ATTEMPTS) as $attempt) {
            $lock->set('code', $wrong)->call('unlock')->assertHasErrors(['code' => 'Código incorrecto.']);
        }

        $lock->set('code', $code)->call('unlock')->assertSet('userId', null);
        $this->assertStringStartsWith('Demasiados intentos.', $lock->errors()->first('code'));
    }

    public function test_the_code_of_a_user_given_a_baja_does_not_open_the_caja(): void
    {
        $seller = User::factory()->inactive()->create();
        $code = app(UserService::class)->generatePosCode($seller);
        $this->onPosTerminal();

        Livewire::test(PosLock::class)
            ->set('code', $code)
            ->call('unlock')
            ->assertHasErrors(['code' => 'Código incorrecto.']);
    }

    public function test_sales_go_in_the_name_of_the_caja_and_the_close_compares_the_cash(): void
    {
        $seller = User::factory()->create(['name' => 'Laura']);
        $cashSession = $this->openCashRegister($seller, 1000);
        $product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 10, 'price' => 1500]);
        $producer = Producer::create(['name' => 'Estancia La Josefina']);

        $pos = Livewire::test(PosDesk::class);
        $pos->call('addProduct', $product->id)->set('paymentMethod', 'efectivo')->call('confirm');
        $pos->call('addProduct', $product->id)->set('paymentMethod', 'qr')->call('confirm');
        $pos->call('addProduct', $product->id)->call('selectProducer', $producer->id)->set('paymentMethod', 'cuenta_corriente')->call('confirm');

        $this->assertSame(3, $cashSession->sales()->where('user_id', $seller->id)->count());

        $pos->call('openCloseModal')
            ->assertSet('showCloseModal', true)
            ->assertDontSee('2.500')
            ->set('declaredCash', '2.400')
            ->call('closeCashRegister')
            ->assertRedirect(route('pos.lock'));

        $cashSession->refresh();
        $this->assertFalse($cashSession->isOpen());
        $this->assertSame('2500.00', $cashSession->expected_cash);
        $this->assertSame('2400.00', $cashSession->declared_cash);
        $this->assertSame(-100.0, $cashSession->difference());

        $this->get(route('pos.desk'))->assertRedirect(route('pos.lock'));
    }

    public function test_the_audit_shows_the_closing_and_the_admin_reviews_it(): void
    {
        $this->withoutVite();
        $cashSession = CashSession::factory()->create(['opening_amount' => 1000]);
        Sale::create(['voucher_number' => 'C-00000001', 'type' => 'contado', 'payment_method' => 'efectivo', 'total' => 1500, 'paid' => 1500, 'status' => 'pagada', 'cash_session_id' => $cashSession->id, 'user_id' => $cashSession->user_id]);
        $cashSession->update(['closed_at' => now(), 'expected_cash' => 2500, 'declared_cash' => 2400]);
        $admin = User::factory()->admin()->create(['name' => 'Diego']);
        $this->actingAs($admin);

        $this->get('/erp/auditoria')->assertOk()
            ->assertSee('Cajas del mostrador')
            ->assertSeeInOrder([$cashSession->user->name, '1 venta', '$ 2.500,00', '$ 2.400,00', '$ -100,00', 'Marcar revisada']);

        Livewire::test(DailyAudit::class)->call('reviewCashSession', $cashSession->id);

        $this->assertTrue($admin->is($cashSession->fresh()->reviewer));
    }

    public function test_the_pos_reprints_only_the_remitos_of_its_open_caja(): void
    {
        $cashSession = $this->openCashRegister();
        $producer = Producer::create(['name' => 'Estancia La Josefina']);
        $own = Sale::create(['voucher_number' => 'R-00000001', 'type' => 'remito', 'payment_method' => 'cuenta_corriente', 'producer_id' => $producer->id, 'total' => 1000, 'paid' => 0, 'status' => 'pendiente', 'cash_session_id' => $cashSession->id]);
        $other = Sale::create(['voucher_number' => 'R-00000002', 'type' => 'remito', 'payment_method' => 'cuenta_corriente', 'producer_id' => $producer->id, 'total' => 1000, 'paid' => 0, 'status' => 'pendiente']);

        $this->get(route('pos.remito-pdf', $own))->assertOk();
        $this->get(route('pos.remito-pdf', $other))->assertNotFound();
    }

    public function test_an_admin_caja_on_the_terminal_does_not_open_the_erp(): void
    {
        $this->openCashRegister(User::factory()->admin()->create());

        $this->get('/erp/inventario')->assertRedirect(route('login'));
    }

    public function test_a_terminal_with_an_open_caja_cannot_be_disabled(): void
    {
        $cashSession = $this->openCashRegister();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->call('revokeTerminal', $cashSession->pos_terminal_id)
            ->assertDispatched('toast', type: 'error');

        $this->assertSame(1, PosTerminal::count());
    }

    public function test_a_disabled_terminal_stops_opening_the_pos(): void
    {
        $terminal = $this->onPosTerminal();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)->call('revokeTerminal', $terminal->id);

        $this->get(route('pos.lock'))->assertRedirect(route('pos.setup'));
    }

    public function test_a_user_with_the_caja_open_cannot_be_given_a_baja(): void
    {
        $cashSession = $this->openCashRegister();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->call('deactivateUser', $cashSession->user_id)
            ->assertDispatched('toast', type: 'error');

        $this->assertTrue($cashSession->user->fresh()->is_active);
    }
}
