<?php

namespace Tests\Feature;

use App\Livewire\RestockModal;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RestockTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_restocking_a_product_with_lots_creates_the_lot_and_records_the_movement(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->tracksExpiry()->create(['name' => 'Vacuna Aftosa', 'supplier_id' => $supplier->id]);

        Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->assertSet('supplierId', $supplier->id)
            ->set('quantity', '37')
            ->set('lotNumber', 'af-2611')
            ->set('expirationDate', today()->addDays(200)->toDateString())
            ->set('reason', 'Factura A-0001-123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Se ingresaron 37 unidades de Vacuna Aftosa')
            ->assertDispatched('stock-updated');

        $product->refresh();
        $lot = $product->lots()->sole();

        $this->assertSame(37, $product->stock);
        $this->assertSame('AF-2611', $lot->lot_number);
        $this->assertSame(37, $lot->quantity);

        $movement = $product->stockMovements()->sole();
        $this->assertSame('ingreso', $movement->type);
        $this->assertSame(37, $movement->quantity);
        $this->assertSame($lot->id, $movement->lot_id);
        $this->assertSame($this->user->id, $movement->user_id);
        $this->assertSame($supplier->id, $movement->supplier_id);
        $this->assertSame('Factura A-0001-123', $movement->reason);
    }

    public function test_an_existing_lot_number_adds_to_that_lot(): void
    {
        $product = Product::factory()->tracksExpiry()->create();
        Lot::factory()->for($product)->create(['lot_number' => 'L-1', 'quantity' => 10]);
        $product->syncStockFromLots();

        Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->set('quantity', '5')
            ->set('lotNumber', 'L-1')
            ->assertSet('expirationDate', $product->lots()->first()->expiration_date->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $product->lots()->count());
        $this->assertSame(15, $product->lots()->first()->quantity);
        $this->assertSame(15, $product->fresh()->stock);
    }

    public function test_lot_and_expiration_are_required_only_when_the_product_tracks_expiry(): void
    {
        $tracked = Product::factory()->tracksExpiry()->create();
        $plain = Product::factory()->create(['stock' => 10]);

        Livewire::test(RestockModal::class)
            ->call('start', $tracked->id)
            ->set('quantity', '5')
            ->call('save')
            ->assertHasErrors(['lotNumber' => 'required', 'expirationDate' => 'required']);

        Livewire::test(RestockModal::class)
            ->call('start', $plain->id)
            ->set('quantity', '5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, $tracked->fresh()->stock);
        $this->assertSame(15, $plain->fresh()->stock);
    }

    public function test_quantity_must_be_a_positive_integer(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        foreach (['', '0', '-3', '2.5'] as $quantity) {
            Livewire::test(RestockModal::class)
                ->call('start', $product->id)
                ->set('quantity', $quantity)
                ->call('save')
                ->assertHasErrors('quantity');
        }

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, $product->stockMovements()->count());
    }

    public function test_a_new_purchase_cost_recalculates_the_price_and_keeps_history(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'cost_price' => 1000, 'markup_percent' => 50, 'vat_percent' => 21, 'price' => 1815]);

        $component = Livewire::test(RestockModal::class)
            ->call('start', $product->id)
            ->set('quantity', '4')
            ->set('newCost', '1.200');

        $this->assertSame(['old' => 1815.0, 'new' => 2178.0], $component->instance()->pricePreview);

        $component->call('save')->assertHasNoErrors();

        $product->refresh();
        $this->assertSame('1200.00', $product->cost_price);
        $this->assertSame('2178.00', $product->price);
        $this->assertSame(14, $product->stock);
        $this->assertSame(1, $product->priceHistories()->count());
    }
}
