<?php

namespace Tests\Feature;

use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\AccountDocumentService;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPdfTest extends TestCase
{
    use RefreshDatabase;

    protected Producer $producer;

    protected Sale $remito;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(12, 0));
        $this->producer = Producer::factory()->create(['name' => 'Marome SA', 'cuit' => '30-50937538-7']);
        $taxed = Product::factory()->create(['sku' => 'FLOK500', 'name' => 'Flok x 500 cc', 'is_active' => true, 'tracks_expiry' => false, 'stock' => 50, 'price' => 1210, 'vat_percent' => 21]);
        $exempt = Product::factory()->create(['sku' => 'HON-EQ', 'name' => 'Honorarios Equino', 'is_active' => true, 'tracks_expiry' => false, 'stock' => 50, 'price' => 500, 'vat_percent' => 0]);

        $this->remito = app(CheckoutService::class)->execute(
            [['product_id' => $taxed->id, 'quantity' => 3], ['product_id' => $exempt->id, 'quantity' => 1]],
            ['type' => 'remito', 'producer_id' => $this->producer->id],
        );
    }

    public function test_the_remito_lines_are_shown_net_of_vat(): void
    {
        $document = app(AccountDocumentService::class)->remito($this->remito);
        $flok = $document['lines']->firstWhere('code', 'FLOK500');

        $this->assertSame([3, 1000.0, 3000.0], [$flok['quantity'], $flok['price'], $flok['subtotal']]);
        $this->assertSame(['exempt' => 500.0, 'net' => 3000.0, 'total' => 4130.0], $document['totals']);
    }

    public function test_each_line_subtotal_is_quantity_times_the_printed_price(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'tracks_expiry' => false, 'stock' => 10, 'price' => 1491.33, 'vat_percent' => 21]);
        $sale = app(CheckoutService::class)->execute([['product_id' => $product->id, 'quantity' => 4]], ['type' => 'remito', 'producer_id' => $this->producer->id]);

        $document = app(AccountDocumentService::class)->remito($sale);
        $line = $document['lines']->sole();

        $this->assertSame([1232.50, 4930.0], [$line['price'], $line['subtotal']]);
        $this->assertSame(5965.32, $document['totals']['total']);
    }

    public function test_the_remito_view_follows_the_paper_format(): void
    {
        $html = view('pdf.remito', app(AccountDocumentService::class)->remito($this->remito))->render();

        $this->assertStringContainsString('Remito R-00000001', $html);
        $this->assertStringContainsString(config('business.name'), $html);
        $this->assertStringContainsString('Marome SA', $html);
        $this->assertStringContainsString('30-50937538-7', $html);
        $this->assertStringContainsString('Flok x 500 cc', $html);
        $this->assertStringContainsString('$ 4.130,00', $html);
        $this->assertStringContainsString('Firma y aclaración', $html);
    }

    public function test_the_statement_lists_vouchers_not_products(): void
    {
        app(PaymentService::class)->register($this->producer->id, 1000, 'cheque', 'Cheque 123');
        app(PaymentService::class)->registerCreditNote($this->producer->id, 130, 'Bonificación');

        $data = app(AccountDocumentService::class)->statement($this->producer->fresh(), CarbonImmutable::today()->subMonth(), CarbonImmutable::today());
        $html = view('pdf.account-statement', $data)->render();

        $this->assertSame(['remito', 'pago', 'nota_credito'], $data['rows']->pluck('kind')->all());
        $this->assertSame('Pago parcial: saldo $ 3.000,00', $data['rows'][0]['status']);
        $this->assertStringContainsString('Resumen de Cuenta Corriente', $html);
        $this->assertStringContainsString('Remito R-00000001', $html);
        $this->assertStringContainsString('Recibo P-00000001', $html);
        $this->assertStringContainsString('Pago: Cheque · Cheque 123', $html);
        $this->assertStringContainsString('Nota de crédito NC-00000001', $html);
        $this->assertStringContainsString('- $ 1.000,00', $html);
        $this->assertStringContainsString('Saldo a la fecha', $html);
        $this->assertStringContainsString('Remitos con saldo pendiente', $html);
        $this->assertStringNotContainsString('Flok x 500 cc', $html);
    }

    public function test_paid_remitos_are_marked_and_left_out_of_the_pending_list(): void
    {
        app(PaymentService::class)->register($this->producer->id, 4130, 'efectivo');

        $data = app(AccountDocumentService::class)->statement($this->producer->fresh());
        $html = view('pdf.account-statement', $data)->render();

        $this->assertSame('Pagado total', $data['rows'][0]['status']);
        $this->assertTrue($data['openRemitos']->isEmpty());
        $this->assertStringNotContainsString('Remitos con saldo pendiente', $html);
        $this->assertSame(0.0, $data['statement']['closing']);
    }

    public function test_the_remitos_detail_lists_the_merchandise_of_the_filtered_period(): void
    {
        $old = app(CheckoutService::class)->execute(
            [['product_id' => Product::factory()->create(['name' => 'Caravanas x 25', 'is_active' => true, 'tracks_expiry' => false, 'stock' => 5, 'price' => 100])->id, 'quantity' => 1]],
            ['type' => 'remito', 'producer_id' => $this->producer->id],
        );
        $old->forceFill(['created_at' => now()->subMonths(2)])->save();

        $data = app(AccountDocumentService::class)->remitosDetail($this->producer, CarbonImmutable::today()->subMonth(), CarbonImmutable::today());
        $html = view('pdf.remitos-detail', $data)->render();

        $this->assertSame(['R-00000001'], $data['remitos']->pluck('voucher_number')->all());
        $this->assertSame(2, $data['lines']->count());
        $this->assertSame(4130.0, $data['totals']['total']);
        $this->assertStringContainsString('Detalle de Remitos', $html);
        $this->assertStringContainsString('Flok x 500 cc', $html);
        $this->assertStringContainsString('Honorarios Equino', $html);
        $this->assertStringNotContainsString('Caravanas x 25', $html);

        $this->assertSame(3, app(AccountDocumentService::class)->remitosDetail($this->producer)['lines']->count());
    }

    public function test_the_pdfs_are_downloaded(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('erp.remitos.pdf', $this->remito))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('erp.accounts.statement-pdf', ['producer' => $this->producer, 'desde' => '2026-01-01', 'hasta' => 'cualquier-cosa']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(route('erp.accounts.remitos-pdf', ['producer' => $this->producer, 'desde' => '2026-01-01']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_only_remitos_have_a_remito_pdf(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $sale = Sale::factory()->create(['total' => 100]);

        $this->get(route('erp.remitos.pdf', $sale))->assertNotFound();
    }

    public function test_guests_cannot_download_the_pdfs(): void
    {
        $this->get(route('erp.remitos.pdf', $this->remito))->assertRedirect(route('login'));
        $this->get(route('erp.accounts.statement-pdf', $this->producer))->assertRedirect(route('login'));
        $this->get(route('erp.accounts.remitos-pdf', $this->producer))->assertRedirect(route('login'));
    }
}
