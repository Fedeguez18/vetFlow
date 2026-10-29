<?php

namespace Tests\Feature;

use App\Livewire\Pos\PosDesk;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosInactiveProductsTest extends TestCase
{
    use RefreshDatabase;

    protected Product $active;

    protected Product $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openCashRegister();

        $this->active = Product::factory()->create(['name' => 'Ivermectina 1% x 50 ml', 'barcode' => '7790000000017', 'stock' => 10]);
        $this->inactive = Product::factory()->create(['name' => 'Ivermectina Discontinuada x 50 ml', 'barcode' => '7790000000024', 'stock' => 10, 'is_active' => false]);
    }

    public function test_an_inactive_product_picked_from_the_grid_is_not_added_to_the_cart(): void
    {
        Livewire::test(PosDesk::class)
            ->call('addProduct', $this->inactive->id)
            ->assertSet('cart', [])
            ->assertSet('alert', 'El producto no existe o está inactivo.')
            ->assertSet('alertType', 'error')
            ->call('addProduct', $this->active->id)
            ->assertSet('cart.'.$this->active->id.'.quantity', 1)
            ->assertSet('alert', 'Ivermectina 1% x 50 ml añadido al ticket.');
    }

    public function test_scanning_the_barcode_of_an_inactive_product_does_not_add_it(): void
    {
        Livewire::test(PosDesk::class)
            ->call('addByCode', '7790000000024')
            ->assertSet('cart', [])
            ->assertSet('alert', 'No se encontró ningún producto para «7790000000024».')
            ->call('addByCode', '7790000000017')
            ->assertSet('cart.'.$this->active->id.'.quantity', 1)
            ->assertSet('alert', 'Escaneado: 7790000000017 — Ivermectina 1% x 50 ml añadido al ticket.');
    }

    public function test_the_catalog_and_search_do_not_list_inactive_products(): void
    {
        $component = Livewire::test(PosDesk::class);

        $this->assertSame([$this->active->id], $component->get('catalog')->pluck('id')->all());
        $this->assertSame(1, $component->get('catalogCount'));

        $component->set('search', 'Ivermectina');

        $this->assertSame([$this->active->id], $component->get('catalog')->pluck('id')->all());
        $component->assertSee('Ivermectina 1% x 50 ml')->assertDontSee('Ivermectina Discontinuada x 50 ml');
    }

    public function test_categories_with_only_inactive_products_are_not_listed(): void
    {
        $withActive = Category::factory()->create(['name' => 'Antiparasitarios']);
        $onlyInactive = Category::factory()->create(['name' => 'Discontinuados']);
        Category::factory()->create(['name' => 'Sin productos']);

        $this->active->update(['category_id' => $withActive->id]);
        $this->inactive->update(['category_id' => $onlyInactive->id]);

        $categories = Livewire::test(PosDesk::class)->get('categories');

        $this->assertSame(['Antiparasitarios'], $categories->pluck('name')->all());
    }
}
