<?php

namespace Tests\Feature;

use App\Livewire\Erp\InventoryCatalog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryInlinePercentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }

    public function test_editing_the_cost_keeps_the_percents_recalculates_the_price_and_records_the_history(): void
    {
        $product = Product::factory()->create(['cost_price' => 1000, 'markup_percent' => 30, 'vat_percent' => 21, 'price' => 1573]);

        Livewire::test(InventoryCatalog::class)
            ->call('updateCost', $product->id, '2.000,50')
            ->assertReturned('$2.000,50')
            ->assertDispatched('toast', message: "Se actualizó {$product->name}: costo \$2.000,50, precio final \$3.146,79.");

        $product->refresh();
        $this->assertEquals(2000.5, $product->cost_price);
        $this->assertEquals(30, $product->markup_percent);
        $this->assertEquals(21, $product->vat_percent);
        $this->assertEquals(3146.79, $product->price);

        $history = $product->priceHistories()->latest('id')->first();
        $this->assertEquals([2000.5, 3146.79], [(float) $history->cost_price, (float) $history->price]);
        $this->assertSame($this->user->id, $history->user_id);
    }

    public function test_an_invalid_or_unchanged_cost_leaves_the_product_alone(): void
    {
        $product = Product::factory()->create(['cost_price' => 1000, 'markup_percent' => 30, 'vat_percent' => 21, 'price' => 1573]);
        $historyCount = $product->priceHistories()->count();

        foreach (['-1', 'abc', '', '0'] as $value) {
            Livewire::test(InventoryCatalog::class)
                ->call('updateCost', $product->id, $value)
                ->assertReturned(null);
        }

        Livewire::test(InventoryCatalog::class)
            ->call('updateCost', $product->id, '1000')
            ->assertReturned('$1.000')
            ->assertNotDispatched('toast');

        $product->refresh();
        $this->assertEquals([1000, 1573], [(float) $product->cost_price, (float) $product->price]);
        $this->assertSame($historyCount, $product->priceHistories()->count());
    }

    public function test_the_cost_cell_opens_for_editing_with_a_double_click(): void
    {
        $product = Product::factory()->create(['cost_price' => 2700]);

        Livewire::test(InventoryCatalog::class)
            ->assertSeeHtml('x-on:dblclick=')
            ->assertSeeHtml('value="2700"')
            ->assertSeeHtml("\$wire.updateCost({$product->id}, \$el.value)");
    }

    public function test_editing_the_markup_recalculates_the_price_and_records_the_history(): void
    {
        $product = Product::factory()->create(['cost_price' => 1000, 'markup_percent' => 30, 'vat_percent' => 21, 'price' => 1573]);

        Livewire::test(InventoryCatalog::class)
            ->call('updatePercent', $product->id, 'markup_percent', '50')
            ->assertReturned('50')
            ->assertDispatched('toast');

        $product->refresh();
        $this->assertEquals(50, $product->markup_percent);
        $this->assertEquals(21, $product->vat_percent);
        $this->assertEquals(1815, $product->price);

        $history = $product->priceHistories()->latest('id')->first();
        $this->assertEquals(1815, $history->price);
        $this->assertSame($this->user->id, $history->user_id);
    }

    public function test_editing_the_vat_accepts_comma_decimals(): void
    {
        $product = Product::factory()->create(['cost_price' => 1000, 'markup_percent' => 30, 'vat_percent' => 21, 'price' => 1573]);

        Livewire::test(InventoryCatalog::class)
            ->call('updatePercent', $product->id, 'vat_percent', '10,5')
            ->assertReturned('10,5');

        $product->refresh();
        $this->assertEquals(10.5, $product->vat_percent);
        $this->assertEquals(30, $product->markup_percent);
        $this->assertEquals(1436.5, $product->price);
    }

    public function test_invalid_values_are_rejected_without_touching_the_product(): void
    {
        $product = Product::factory()->create(['cost_price' => 1000, 'markup_percent' => 30, 'vat_percent' => 21, 'price' => 1573]);
        $historyCount = $product->priceHistories()->count();

        foreach ([['vat_percent', '-1'], ['vat_percent', 'abc'], ['markup_percent', '-101'], ['markup_percent', '-100'], ['stock', '5']] as [$field, $value]) {
            Livewire::test(InventoryCatalog::class)
                ->call('updatePercent', $product->id, $field, $value)
                ->assertReturned(null);
        }

        $product->refresh();
        $this->assertEquals(30, $product->markup_percent);
        $this->assertEquals(21, $product->vat_percent);
        $this->assertEquals(1573, $product->price);
        $this->assertSame($historyCount, $product->priceHistories()->count());
    }

    public function test_the_table_shows_both_percent_columns(): void
    {
        Product::factory()->create(['markup_percent' => 35, 'vat_percent' => 10.5]);

        Livewire::test(InventoryCatalog::class)
            ->assertSee('% Remarque')
            ->assertSee('% IVA')
            ->assertSeeHtml('value="10,5"')
            ->assertSeeHtml('value="35"');
    }
}
