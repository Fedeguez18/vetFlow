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

    protected function sale(string $voucher, string $type, ?string $method, float $total, ?int $producerId = null, $date = null): void
    {
        $sale = Sale::create(['voucher_number' => $voucher, 'type' => $type, 'payment_method' => $method, 'producer_id' => $producerId, 'total' => $total, 'paid' => $type === 'contado' ? $total : 0, 'status' => $type === 'contado' ? 'pagada' : 'pendiente']);
        $sale->forceFill(['created_at' => $date ?? now()])->save();
    }

    public function test_the_summary_splits_the_day_by_method(): void
    {
        $producer = Producer::create(['name' => 'Estancia La Josefina', 'balance' => 9000]);

        $at = fn (int $hour) => today()->setTime($hour, 0);

        $this->sale('C-00000001', 'contado', 'efectivo', 1000, null, $at(9));
        $this->sale('C-00000002', 'contado', 'qr', 2500.50, null, $at(10));
        $this->sale('C-00000003', 'contado', 'tarjeta', 499.50, null, $at(11));
        $this->sale('R-00000001', 'remito', 'cuenta_corriente', 9000, $producer->id, $at(12));
        $this->sale('C-00000004', 'contado', 'efectivo', 7777, null, now()->subDay());
        Payment::create(['producer_id' => $producer->id, 'amount' => 300, 'payment_method' => 'transferencia'])->forceFill(['created_at' => $at(13)])->save();

        $audit = app(CashAuditService::class);
        $entries = $audit->entries(CarbonImmutable::today());
        $summary = $audit->summary($entries);

        $this->assertSame(['C-00000001', 'C-00000002', 'C-00000003', 'R-00000001', 'P-00000001'], $entries->pluck('voucher')->all());
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
        $producer = Producer::create(['name' => 'Estancia La Josefina']);

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
        $this->sale('C-00000001', 'contado', 'efectivo', 100);

        $entries = app(CashAuditService::class)->entries(CarbonImmutable::today());

        $this->assertSame([
            'R-00000001' => ['Laura', User::ROLE_SELLER],
            'P-00000001' => ['Diego', User::ROLE_ADMIN],
            'C-00000001' => [null, null],
        ], $entries->mapWithKeys(fn (array $entry) => [$entry['voucher'] => [$entry['user'], $entry['user_role']]])->all());

        $this->get('/erp/auditoria')->assertOk()
            ->assertSeeInOrder(['R-00000001', 'Laura', 'Vendedor', 'P-00000001', 'Diego', 'Administrador', 'C-00000001', 'Sin registrar']);
    }

    public function test_the_screen_ignores_future_and_invalid_dates(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->assertTrue(Livewire::test(DailyAudit::class)->set('date', now()->addDay()->toDateString())->instance()->day->isToday());
        $this->assertTrue(Livewire::test(DailyAudit::class)->set('date', 'no-es-fecha')->instance()->day->isToday());
        $this->assertSame(now()->subDays(2)->toDateString(), Livewire::test(DailyAudit::class)->set('date', now()->subDays(2)->toDateString())->instance()->day->toDateString());
    }
}
