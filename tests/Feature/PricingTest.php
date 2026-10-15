<?php

namespace Tests\Feature;

use App\Livewire\ProductEditor;
use App\Models\User;
use App\Support\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_price_formula_and_its_inverse(): void
    {
        $this->assertSame(1815.0, Pricing::finalPrice(1000, 50, 21));
        $this->assertSame(1657.5, Pricing::finalPrice(1000, 50, 10.5));
        $this->assertSame(1500.0, Pricing::finalPrice(1000, 50, 0));
        $this->assertSame(50.0, Pricing::markupFromFinal(1000, 21, 1815));
        $this->assertSame(1800.0, Pricing::finalPrice(1000, 47, 21, 100));
        $this->assertSame(1780.0, Pricing::finalPrice(1000, 47, 21, 10));
        $this->assertNull(Pricing::markupFromFinal(0, 21, 100));
    }

    public function test_the_form_calculates_the_final_price_from_the_markup(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.cost_price', '1.000')
            ->set('form.markup_percent', '50')
            ->set('form.vat_percent', '21')
            ->assertSet('form.price', '1815')
            ->set('form.vat_percent', '10,5')
            ->assertSet('form.price', '1657.5');
    }

    public function test_editing_the_final_price_recalculates_the_markup_keeping_cost_and_vat(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.cost_price', '1000')
            ->set('form.markup_percent', '50')
            ->set('form.vat_percent', '21')
            ->set('form.price', '2420')
            ->assertSet('form.markup_percent', '100')
            ->assertSet('form.cost_price', '1000')
            ->assertSet('form.vat_percent', '21');

        $this->assertSame(['cost' => 1000.0, 'profit' => 1000.0, 'vat' => 420.0, 'final' => 2420.0], $component->instance()->breakdown);
    }

    public function test_optional_rounding_keeps_the_markup_coherent(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->set('form.cost_price', '1000')
            ->set('form.markup_percent', '47')
            ->set('form.vat_percent', '21')
            ->assertSet('form.price', '1778.7')
            ->set('form.round_to', 100)
            ->set('form.round', true)
            ->assertSet('form.price', '1800')
            ->assertSet('form.markup_percent', '48.76');
    }
}
