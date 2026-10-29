<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Livewire\ProductEditor;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }

    public function test_an_adjustment_requires_a_reason(): void
    {
        $product = Product::factory()->create(['stock' => 20]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('tab', 'ajuste')
            ->set('adjustQuantity', '3')
            ->call('adjust')
            ->assertHasErrors(['adjustReason' => 'required']);

        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(0, $product->stockMovements()->count());

        $this->expectException(StockException::class);
        app(StockService::class)->adjust($product, 'subtract', 3, '');
    }

    public function test_breakage_subtracts_units_as_an_outflow(): void
    {
        $product = Product::factory()->create(['stock' => 20]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('adjustQuantity', '3')
            ->set('adjustReason', 'rotura')
            ->set('adjustNote', 'Frasco roto en mostrador')
            ->call('adjust')
            ->assertHasNoErrors()
            ->assertDispatched('stock-updated');

        $this->assertSame(17, $product->fresh()->stock);

        $movement = $product->stockMovements()->sole();
        $this->assertSame('egreso', $movement->type);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame('Rotura: Frasco roto en mostrador', $movement->reason);
        $this->assertSame($this->user->id, $movement->user_id);
    }

    public function test_a_physical_count_corrects_the_lot_quantity(): void
    {
        $product = Product::factory()->tracksExpiry()->create();
        $lot = $product->lots()->create(['lot_number' => 'L-1', 'expiration_date' => today()->addDays(100), 'quantity' => 10]);
        $product->syncStockFromLots();

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('adjustMode', 'set')
            ->set('adjustQuantity', '12')
            ->set('adjustReason', 'conteo')
            ->call('adjust')
            ->assertHasErrors(['adjustLotId' => 'required'])
            ->set('adjustLotId', $lot->id)
            ->call('adjust')
            ->assertHasNoErrors();

        $this->assertSame(12, $lot->fresh()->quantity);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame(['ajuste', 2], [$product->stockMovements()->sole()->type, $product->stockMovements()->sole()->quantity]);
    }

    public function test_it_cannot_subtract_more_than_available_and_other_needs_a_detail(): void
    {
        $product = Product::factory()->create(['stock' => 2]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('adjustQuantity', '5')
            ->set('adjustReason', 'vencido')
            ->call('adjust')
            ->assertHasErrors('adjustQuantity')
            ->set('adjustQuantity', '1')
            ->set('adjustReason', 'otro')
            ->call('adjust')
            ->assertHasErrors(['adjustNote' => 'required']);

        $this->assertSame(2, $product->fresh()->stock);
    }
}
