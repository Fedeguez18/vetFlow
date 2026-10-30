<?php

namespace Tests\Feature;

use App\Livewire\ProductEditor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPriceFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_calculates_the_final_price_from_the_markup(): void
    {
        $this->actingAs(User::factory()->admin()->create());

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
        $this->actingAs(User::factory()->admin()->create());

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
        $this->actingAs(User::factory()->admin()->create());

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
