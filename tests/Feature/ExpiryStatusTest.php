<?php

namespace Tests\Feature;

use App\Livewire\Erp\InventoryCatalog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpiryStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_thresholds(): void
    {
        $this->assertSame('expired', Product::expiryStatusFor(-1));
        $this->assertSame('critical', Product::expiryStatusFor(0));
        $this->assertSame('critical', Product::expiryStatusFor(29));
        $this->assertSame('warning', Product::expiryStatusFor(30));
        $this->assertSame('warning', Product::expiryStatusFor(59));
        $this->assertSame('ok', Product::expiryStatusFor(60));
        $this->assertSame('ok', Product::expiryStatusFor(null));
    }

    public function test_the_status_comes_from_the_lot_that_expires_first(): void
    {
        $product = Product::factory()->tracksExpiry()->create();
        $product->lots()->create(['lot_number' => 'FAR', 'expiration_date' => today()->addDays(300), 'quantity' => 5]);
        $product->lots()->create(['lot_number' => 'NEAR', 'expiration_date' => today()->addDays(14), 'quantity' => 5]);
        $product->lots()->create(['lot_number' => 'EMPTY', 'expiration_date' => today()->subDays(3), 'quantity' => 0]);

        $product = Product::with('nextLot')->find($product->id);
        $this->assertSame('NEAR', $product->nextLot->lot_number);
        $this->assertSame(14, $product->expiry_days);
        $this->assertSame('critical', $product->expiry_status);

        $plain = Product::factory()->create();
        $this->assertSame('ok', $plain->expiry_status);
        $this->assertNull($plain->expiry_days);
    }

    public function test_the_inventory_shows_each_alert_label(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['Vencido' => -5, 'Critico' => 14, 'Atencion' => 45, 'Al dia' => 200] as $name => $days) {
            $product = Product::factory()->tracksExpiry()->create(['name' => "Lote {$name}"]);
            $product->lots()->create(['lot_number' => 'L', 'expiration_date' => today()->addDays($days), 'quantity' => 8]);
            $product->syncStockFromLots();
        }

        Livewire::test(InventoryCatalog::class)
            ->assertSee('Vencido')
            ->assertSee('Crítico: 14 días')
            ->assertSee('Atención: 45 días')
            ->assertSee('Al día')
            ->assertSee(today()->addDays(14)->format('d/m/Y'));
    }
}
