<?php

namespace Tests\Feature;

use App\Livewire\Erp\AccountsReceivable;
use App\Livewire\Erp\DailyAudit;
use App\Livewire\Pos\PosDesk;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_summary_splits_the_day_by_method(): void
    {
        $producer = Producer::factory()->create(['name' => 'Estancia La Josefina', 'balance' => 9000]);

        $at = fn (int $hour) => today()->setTime($hour, 0);

        Sale::factory()->create(['payment_method' => 'efectivo', 'total' => 1000, 'created_at' => $at(9)]);
        Sale::factory()->create(['payment_method' => 'qr', 'total' => 2500.50, 'created_at' => $at(10)]);
        Sale::factory()->create(['payment_method' => 'tarjeta', 'total' => 499.50, 'created_at' => $at(11)]);
        Sale::factory()->remito()->for($producer)->create(['total' => 9000, 'created_at' => $at(12)]);
        Sale::factory()->create(['payment_method' => 'efectivo', 'total' => 7777, 'created_at' => now()->subDay()]);
        $payment = Payment::factory()->for($producer)->create(['amount' => 300, 'payment_method' => 'transferencia', 'created_at' => $at(13)]);

        $audit = app(CashAuditService::class);
        $entries = $audit->entries(CarbonImmutable::today());
        $summary = $audit->summary($entries);

        $this->assertSame(['C-00000001', 'C-00000002', 'C-00000003', 'R-00000001', $payment->voucher_number], $entries->pluck('voucher')->all());
        $this->assertSame(4300.0, $summary['collected']);
        $this->assertSame(1000.0, $summary['cash']);
        $this->assertSame(2800.5, $summary['bank']);
        $this->assertSame([4, 3, 1, 9000.0, 1, 300.0], [$summary['vouchers'], $summary['tickets'], $summary['remitos'], $summary['remitos_amount'], $summary['payments'], $summary['payments_amount']]);
        $this->assertSame(['qr' => 2500.5, 'efectivo' => 1000.0, 'tarjeta' => 499.5, 'transferencia' => 300.0], $summary['by_method']);
    }

    public function test_each_movement_shows_the_user_who_made_it(): void
    {
        $this->withoutVite();
        $seller = User::factory()->create(['name' => 'Laura']);
        $admin = User::factory()->admin()->create(['name' => 'Diego']);
        $product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 10, 'price' => 1500]);
        $producer = Producer::factory()->create(['name' => 'Estancia La Josefina']);

        $this->openCashRegister($seller);
        Livewire::test(PosDesk::class)
            ->call('addProduct', $product->id)
            ->call('selectProducer', $producer->id)
            ->set('paymentMethod', 'cuenta_corriente')
            ->call('confirm');

        $this->travel(1)->minutes();
        $this->actingAs($admin);
        Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $producer->id)
            ->call('openPaymentModal')
            ->set('paymentAmount', '500')
            ->call('registerPayment');

        $this->travel(1)->minutes();
        Sale::factory()->create(['payment_method' => 'efectivo', 'total' => 100]);
        $payment = Payment::sole();

        $entries = app(CashAuditService::class)->entries(CarbonImmutable::today());

        $this->assertSame([
            'R-00000001' => ['Laura', User::ROLE_SELLER],
            $payment->voucher_number => ['Diego', User::ROLE_ADMIN],
            'C-00000001' => [null, null],
        ], $entries->mapWithKeys(fn (array $entry) => [$entry['voucher'] => [$entry['user'], $entry['user_role']]])->all());

        $this->get('/erp/auditoria')->assertOk()
            ->assertSeeInOrder(['R-00000001', 'Laura', 'Vendedor', $payment->voucher_number, 'Diego', 'Administrador', 'C-00000001', 'Sin registrar']);
    }

    public function test_the_day_exports_to_csv_with_the_balance_and_the_movements(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $producer = Producer::factory()->create(['name' => 'Estancia La Josefina']);
        $day = today()->subDays(2);
        $at = fn (int $hour) => $day->copy()->setTime($hour, 0);

        Sale::factory()->create(['payment_method' => 'efectivo', 'total' => 1000, 'created_at' => $at(9)]);
        Sale::factory()->create(['payment_method' => 'qr', 'total' => 2500.50, 'created_at' => $at(10)]);
        Sale::factory()->remito()->for($producer)->create(['total' => 9000, 'created_at' => $at(11)]);
        $payment = Payment::factory()->for($producer)->create(['amount' => 300, 'payment_method' => 'transferencia', 'created_at' => $at(12)]);
        Sale::factory()->create(['payment_method' => 'efectivo', 'total' => 7777, 'created_at' => today()]);

        $response = $this->get(route('erp.audit.export', ['date' => $day->toDateString()]))->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('arqueo-'.$day->toDateString().'.csv', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("\"Total cobrado\";3800,50\n", $csv);
        $this->assertStringContainsString("\"Dinero en efectivo\";1000,00\n", $csv);
        $this->assertStringContainsString("\"QR / Transferencia\";2800,50\n", $csv);
        $this->assertStringContainsString("\"Tickets contado\";3500,50;2\n", $csv);
        $this->assertStringContainsString('R-00000001;Remito;"Estancia La Josefina";"Cuenta Corriente";"Sin registrar";;9000,00;0,00', $csv);
        $this->assertStringContainsString($payment->voucher_number.';"Cobro Cta. Cte."', $csv);
        $this->assertStringNotContainsString('7777', $csv);

        $this->withoutVite()->get('/erp/auditoria?date='.$day->toDateString())
            ->assertSee(route('erp.audit.export', ['date' => $day->toDateString()]), false);
    }

    public function test_the_screen_ignores_future_and_invalid_dates(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(Livewire::test(DailyAudit::class)->set('date', now()->addDay()->toDateString())->instance()->day->isToday());
        $this->assertTrue(Livewire::test(DailyAudit::class)->set('date', 'no-es-fecha')->instance()->day->isToday());
        $this->assertSame(now()->subDays(2)->toDateString(), Livewire::test(DailyAudit::class)->set('date', now()->subDays(2)->toDateString())->instance()->day->toDateString());
    }
}
