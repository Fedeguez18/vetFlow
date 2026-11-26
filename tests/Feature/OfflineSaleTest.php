<?php

namespace Tests\Feature;

use App\Livewire\Pos\PosDesk;
use App\Models\CashSession;
use App\Models\Lot;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\VoucherBlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OfflineSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        // The browser sends the terminal and caja cookies along with its JSON requests.
        $this->withCredentials();
    }

    public function test_the_snapshot_has_what_the_pos_needs_to_sell_without_internet(): void
    {
        $seller = User::factory()->create(['name' => 'Laura']);
        $cashSession = $this->openCashRegister($seller);
        $product = Product::factory()->create(['name' => 'Ivermectina 1%', 'barcode' => '7790001', 'price' => 1500, 'stock' => 7, 'is_active' => true]);
        Product::factory()->inactive()->create(['name' => 'Producto dado de baja']);
        $producer = Producer::factory()->create(['name' => 'Estancia La Josefina', 'balance' => 2000, 'credit_limit' => 50000]);

        $snapshot = $this->getJson(route('pos.offline.snapshot'))->assertOk()->json();

        $this->assertSame(['id' => $cashSession->id, 'user_id' => $seller->id, 'user_name' => 'Laura'], array_intersect_key($snapshot['cash_session'], array_flip(['id', 'user_id', 'user_name'])));
        $this->assertTrue($snapshot['unlocked']);
        $this->assertSame([['from' => 1, 'to' => VoucherBlockService::BLOCK_SIZE]], $snapshot['vouchers']['C']);
        $this->assertSame([['from' => 1, 'to' => VoucherBlockService::BLOCK_SIZE]], $snapshot['vouchers']['R']);
        $this->assertSame(['Ivermectina 1%'], array_column($snapshot['products'], 'name'));
        $this->assertEquals(['id' => $product->id, 'barcode' => '7790001', 'price' => 1500, 'stock' => 7], array_intersect_key($snapshot['products'][0], array_flip(['id', 'barcode', 'price', 'stock'])));
        $this->assertEquals(['id' => $producer->id, 'balance' => 2000, 'credit_limit' => 50000], array_intersect_key($snapshot['producers'][0], array_flip(['id', 'balance', 'credit_limit'])));
        $this->assertArrayNotHasKey('tarjeta', $snapshot['methods']);
    }

    public function test_without_an_open_caja_the_snapshot_hands_out_no_voucher_numbers(): void
    {
        $this->onPosTerminal();

        $snapshot = $this->getJson(route('pos.offline.snapshot'))->assertOk()->json();

        $this->assertNull($snapshot['cash_session']);
        $this->assertFalse($snapshot['unlocked']);
        $this->assertNull($snapshot['vouchers']);
        $this->assertDatabaseCount('voucher_blocks', 0);
    }

    public function test_a_caja_left_open_on_the_terminal_is_in_the_snapshot_but_locked(): void
    {
        $cashSession = app(CashSessionService::class)->open($this->onPosTerminal(), User::factory()->create(['name' => 'Laura']));

        $snapshot = $this->getJson(route('pos.offline.snapshot'))->assertOk()->json();

        $this->assertSame([$cashSession->id, 'Laura', false], [$snapshot['cash_session']['id'], $snapshot['cash_session']['user_name'], $snapshot['unlocked']]);
        $this->assertNotNull($snapshot['vouchers']);
    }

    public function test_a_pc_that_is_not_a_terminal_cannot_get_the_data_nor_upload_sales(): void
    {
        $this->getJson(route('pos.offline.snapshot'))->assertForbidden();
        $this->postJson(route('pos.offline.sync'), [])->assertForbidden();
    }

    public function test_each_terminal_numbers_its_sales_from_its_own_block(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 100]);
        $this->openCashRegister();

        Livewire::test(PosDesk::class)->assertSee('#C-00000001')
            ->call('addProduct', $product->id)->call('confirm')
            ->assertDispatched('pos-sale-registered', voucher: 'C-00000001')
            ->assertSee('#C-00000002');

        $this->openCashRegister();
        Livewire::test(PosDesk::class)->call('addProduct', $product->id)->call('confirm');

        $this->assertSame(['C-00000001', 'C-00000051'], Sale::query()->orderBy('id')->pluck('voucher_number')->all());
        $this->assertSame('C-00000101', Sale::factory()->create()->voucher_number, 'Lo que no sale de una terminal sigue el contador general, después de los bloques.');
    }

    public function test_a_sale_made_offline_is_registered_with_its_time_number_and_prices(): void
    {
        $cashSession = $this->openCashRegister(openingAmount: 1000);
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 1500]);
        $this->getJson(route('pos.offline.snapshot'))->assertOk();
        $product->update(['price' => 1800]);

        $payload = $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1500]], [
            'voucher_number' => 'C-00000001',
            'sold_at' => now()->subMinutes(40)->toIso8601String(),
        ]);

        $this->postJson(route('pos.offline.sync'), $payload)
            ->assertOk()
            ->assertExactJson(['status' => 'created', 'voucher_number' => 'C-00000001', 'needs_review' => false, 'notes' => []]);

        $sale = Sale::sole();
        $this->assertTrue($sale->wasMadeOffline());
        $this->assertTrue($sale->created_at->equalTo(now()->subMinutes(40)));
        $this->assertSame(['3000.00', 'pagada', 'efectivo'], [$sale->total, $sale->status, $sale->payment_method]);
        $this->assertSame('1500.00', $sale->saleItems->sole()->unit_price);
        $this->assertTrue($cashSession->is($sale->cashSession));
        $this->assertSame($cashSession->user_id, $sale->user_id);
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(4000.0, app(CashSessionService::class)->expectedCash($cashSession));

        Livewire::test(PosDesk::class)->assertSee('#C-00000002');
    }

    public function test_uploading_the_same_sale_twice_registers_it_once(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 1500]);
        $payload = $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1500]]);

        $first = $this->postJson(route('pos.offline.sync'), $payload)->assertOk()->json();
        $this->postJson(route('pos.offline.sync'), $payload)->assertOk()->assertJson(['status' => 'duplicate', 'voucher_number' => $first['voucher_number']]);

        $this->assertSame(1, Sale::count());
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_a_sale_without_enough_stock_is_registered_and_flagged(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['name' => 'Vacuna Aftosa', 'is_active' => true, 'stock' => 1, 'price' => 500]);

        $result = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 500]]))
            ->assertOk()->json();

        $this->assertTrue($result['needs_review']);
        $this->assertContains('Faltó stock de Vacuna Aftosa: se vendieron 3 y el sistema tenía 1.', $result['notes']);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(3, Sale::sole()->saleItems->sole()->quantity);
    }

    public function test_offline_sales_take_the_lots_first_to_expire_and_never_the_expired_ones(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->tracksExpiry()->create(['name' => 'Antiparasitario', 'is_active' => true, 'price' => 900]);
        $expired = Lot::factory()->for($product)->expiresIn(-5)->create(['quantity' => 5]);
        $later = Lot::factory()->for($product)->expiresIn(200)->create(['quantity' => 1]);
        $sooner = Lot::factory()->for($product)->expiresIn(30)->create(['quantity' => 1]);
        $product->syncStockFromLots();

        $result = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 900]]))
            ->assertOk()->json();

        $this->assertSame([0, 0, 5], [$sooner->fresh()->quantity, $later->fresh()->quantity, $expired->fresh()->quantity]);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertContains('Faltó stock de Antiparasitario: se vendieron 3 y el sistema tenía 2.', $result['notes']);
    }

    public function test_a_printed_number_already_used_or_a_provisional_one_gets_a_new_number_and_is_flagged(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 100]);
        Livewire::test(PosDesk::class)->call('addProduct', $product->id)->call('confirm');

        $reused = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]], ['voucher_number' => 'C-00000001']))
            ->assertOk()->json();
        $provisional = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]], ['voucher_number' => null, 'provisional_number' => 'OFF-1-0001']))
            ->assertOk()->json();
        $foreign = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]], ['voucher_number' => 'C-00009999']))
            ->assertOk()->json();

        $this->assertSame(['C-00000002', true], [$reused['voucher_number'], $reused['needs_review']]);
        $this->assertSame(['El comprobante salió impreso como C-00000001, que ya estaba usado o no era de esta terminal: se le asignó C-00000002.'], $reused['notes']);
        $this->assertSame(['El comprobante salió impreso como provisorio OFF-1-0001: se le asignó C-00000003.'], $provisional['notes']);
        $this->assertSame('C-00000004', $foreign['voucher_number']);
    }

    public function test_a_remito_made_offline_goes_to_the_account_even_above_the_credit_limit(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 30000]);
        $producer = Producer::factory()->create(['name' => 'Cabaña El Ombú', 'balance' => 40000, 'credit_limit' => 50000]);
        $this->getJson(route('pos.offline.snapshot'))->assertOk();

        $result = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 30000]], [
            'type' => 'remito',
            'payment_method' => 'cuenta_corriente',
            'producer_id' => $producer->id,
            'voucher_number' => 'R-00000001',
        ]))->assertOk()->json();

        $sale = Sale::sole();
        $this->assertSame(['R-00000001', 'remito', 'cuenta_corriente', 'pendiente'], [$sale->voucher_number, $sale->type, $sale->payment_method, $sale->status]);
        $this->assertSame('70000.00', $producer->fresh()->balance);
        $this->assertFalse($result['needs_review']);
        $this->assertSame(['El remito superó el límite de crédito de Cabaña El Ombú ($ 50.000,00).'], $result['notes']);
    }

    public function test_a_sale_uploaded_after_the_caja_closed_by_itself_updates_its_expected_cash(): void
    {
        $cashSession = $this->openCashRegister(openingAmount: 1000);
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 500]);
        $this->getJson(route('pos.offline.snapshot'))->assertOk();
        $soldAt = now()->addHour();

        $this->travel(CashSessionService::IDLE_HOURS + 2)->hours();
        app(CashSessionService::class)->closeIdle();
        $this->assertSame('1000.00', $cashSession->fresh()->expected_cash);

        $result = $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 500]], ['sold_at' => $soldAt->toIso8601String(), 'voucher_number' => 'C-00000001']))
            ->assertOk()->json();

        $this->assertSame('2000.00', $cashSession->fresh()->expected_cash);
        $this->assertTrue($cashSession->is(Sale::sole()->cashSession));
        $this->assertFalse($result['needs_review']);
        $this->assertSame(['La caja ya estaba cerrada cuando se subió la venta: se recalculó el efectivo esperado.'], $result['notes']);
    }

    public function test_a_pc_clock_ahead_of_time_is_not_trusted(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 500]);

        $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 500]], ['sold_at' => now()->addDay()->toIso8601String()]))
            ->assertOk();

        $this->assertTrue(Sale::sole()->created_at->equalTo(now()));
    }

    public function test_a_malformed_upload_is_refused_and_registers_nothing(): void
    {
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['is_active' => true, 'stock' => 10, 'price' => 500]);

        $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, []))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 500]], ['payment_method' => 'tarjeta']))
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 500]], ['type' => 'remito', 'payment_method' => 'cuenta_corriente']))
            ->assertUnprocessable()->assertJsonValidationErrors('producer_id');

        $this->assertSame(0, Sale::count());
    }

    public function test_the_audit_tells_offline_sales_apart_and_shows_what_to_review(): void
    {
        $this->withoutVite();
        $cashSession = $this->openCashRegister();
        $product = Product::factory()->create(['name' => 'Vacuna Aftosa', 'is_active' => true, 'stock' => 1, 'price' => 500]);
        $this->postJson(route('pos.offline.sync'), $this->offlineSale($cashSession, [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 500]], ['voucher_number' => null]));

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/erp/auditoria')->assertOk()
            ->assertSee('Sin conexión')
            ->assertSee('Revisar')
            ->assertSee('Faltó stock de Vacuna Aftosa');
    }

    public function test_the_offline_page_is_served_to_terminals_only(): void
    {
        $this->withoutVite();
        $this->get(route('pos.offline'))->assertRedirect(route('pos.setup'));

        $this->onPosTerminal();
        $this->get(route('pos.offline'))->assertOk()->assertSee('Sin conexión');
    }

    /**
     * A sale as the browser uploads it.
     *
     * @param  list<array{product_id: int, quantity: int, unit_price: float|int}>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function offlineSale(CashSession $cashSession, array $items, array $overrides = []): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'sold_at' => now()->subMinutes(10)->toIso8601String(),
            'cash_session_id' => $cashSession->id,
            'type' => 'contado',
            'payment_method' => 'efectivo',
            'producer_id' => null,
            'voucher_number' => null,
            'provisional_number' => null,
            'items' => $items,
            ...$overrides,
        ];
    }
}
