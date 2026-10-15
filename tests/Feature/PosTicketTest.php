<?php

namespace Tests\Feature;

use App\Livewire\Pos\PosDesk;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_printable_ticket_shows_the_last_remito(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['name' => 'Ivermectina 1% x 50 ml', 'is_active' => true, 'tracks_expiry' => false, 'stock' => 10, 'price' => 1500]);
        $producer = Producer::create(['name' => 'Estancia La Josefina', 'cuit' => '30-71234567-1']);

        $html = Livewire::test(PosDesk::class)
            ->assertDontSee('Firma y aclaración')
            ->call('addProduct', $product->id)
            ->call('setQuantity', $product->id, 2)
            ->call('selectProducer', $producer->id)
            ->set('paymentMethod', 'cuenta_corriente')
            ->call('confirm')
            ->assertDispatched('print-ticket')
            ->html();

        $ticket = $this->textOf(substr($html, strpos($html, 'x-ref="ticket"')));

        $this->assertStringContainsString('REMITO R-00000001 Fecha '.now()->format('d/m/Y'), $ticket);
        $this->assertStringContainsString('Productor Estancia La Josefina CUIT 30-71234567-1 Cobro Cuenta Corriente (A Remito)', $ticket);
        $this->assertStringContainsString('Ivermectina 1% x 50 ml 2 x $1.500 $3.000 TOTAL $3.000', $ticket);
        $this->assertStringContainsString('Firma y aclaración Comprobante no válido como factura', $ticket);
    }

    /**
     * Visible text of an HTML fragment, one space between elements.
     */
    protected function textOf(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<(div|span|hr|h1|br)/', ' <$1', $html))));
    }

    public function test_the_customer_picker_lists_producers_with_their_debt(): void
    {
        $this->actingAs(User::factory()->create());
        Producer::create(['name' => 'Estancia La Josefina', 'business_name' => 'La Josefina S.A.', 'balance' => 12500]);

        Livewire::test(PosDesk::class)
            ->assertSee('Consumidor Final')
            ->assertSee('Estancia La Josefina')
            ->assertSee('La Josefina S.A.')
            ->assertSee('$12.500');
    }
}
