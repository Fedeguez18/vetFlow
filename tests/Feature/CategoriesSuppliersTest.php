<?php

namespace Tests\Feature;

use App\Livewire\Erp\Categories;
use App\Livewire\Erp\Suppliers;
use App\Livewire\ProductEditor;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriesSuppliersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_categories_and_suppliers_have_their_own_tabs(): void
    {
        $this->withoutVite();
        Category::factory()->create(['name' => 'Farmacia']);
        Supplier::factory()->create(['business_name' => 'BioPampa S.A.']);

        $this->get('/erp/inventario')->assertOk()->assertSee(route('erp.categories'))->assertSee(route('erp.suppliers'));
        $this->get('/erp/rubros')->assertOk()->assertSee('Farmacia')->assertDontSee('BioPampa S.A.');
        $this->get('/erp/proveedores')->assertOk()->assertSee('BioPampa S.A.')->assertDontSee('Farmacia');
        $this->get('/erp/rubros-proveedores')->assertRedirect('/erp/proveedores');
    }

    public function test_a_category_can_be_created_and_then_used_by_products(): void
    {
        Livewire::test(Categories::class)
            ->set('categoryName', '  Antiparasitarios ')
            ->set('categoryColor', 'pharmacy')
            ->call('saveCategory')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('categoryName', '');

        $category = Category::sole();
        $this->assertSame('Antiparasitarios', $category->name);
        $this->assertSame('antiparasitarios', $category->slug);
        $this->assertSame('pharmacy', $category->icon);

        $this->assertTrue(Livewire::test(ProductEditor::class)->instance()->categories->contains('id', $category->id));
    }

    public function test_category_names_are_unique_and_slugs_never_collide(): void
    {
        Category::factory()->create(['name' => 'Farmacia', 'slug' => 'farmacia']);
        Category::factory()->create(['name' => 'Otro rubro', 'slug' => 'farmacia-general']);

        Livewire::test(Categories::class)
            ->set('categoryName', 'Farmacia')
            ->call('saveCategory')
            ->assertHasErrors('categoryName')
            ->set('categoryName', 'Farmacia General')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertSame('farmacia-general-2', Category::where('name', 'Farmacia General')->value('slug'));
    }

    public function test_a_category_can_be_edited(): void
    {
        $category = Category::factory()->create(['name' => 'Farmacia', 'slug' => 'farmacia', 'icon' => 'pharmacy']);

        Livewire::test(Categories::class)
            ->call('editCategory', $category->id)
            ->assertSet('categoryName', 'Farmacia')
            ->set('categoryName', 'Farmacia Veterinaria')
            ->set('categoryColor', 'vaccine')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $category->refresh();
        $this->assertSame('Farmacia Veterinaria', $category->name);
        $this->assertSame('vaccine', $category->icon);
        $this->assertSame('farmacia', $category->slug);
        $this->assertSame(1, Category::count());
    }

    public function test_categories_with_products_cannot_be_deleted(): void
    {
        $used = Category::factory()->create();
        Product::factory()->create(['category_id' => $used->id]);
        $unused = Category::factory()->create();

        Livewire::test(Categories::class)
            ->call('deleteCategory', $used->id)
            ->assertDispatched('toast', type: 'error')
            ->call('deleteCategory', $unused->id);

        $this->assertModelExists($used);
        $this->assertModelMissing($unused);
    }

    public function test_a_supplier_can_be_created_with_a_normalized_cuit(): void
    {
        Livewire::test(Suppliers::class)
            ->set('form.business_name', 'Agrovet del Centro S.R.L.')
            ->set('form.cuit', '30709876543')
            ->set('form.phone', '(0358) 464-2210')
            ->set('form.email', 'Pedidos@Agrovet.com')
            ->call('saveSupplier')
            ->assertHasNoErrors()
            ->assertSet('form.business_name', '');

        $supplier = Supplier::sole();
        $this->assertSame('30-70987654-3', $supplier->cuit);
        $this->assertSame('pedidos@agrovet.com', $supplier->email);
    }

    public function test_supplier_validation(): void
    {
        Supplier::factory()->create(['cuit' => '30-71234567-1']);

        Livewire::test(Suppliers::class)
            ->set('form.business_name', '')
            ->set('form.cuit', '30-71234567-1')
            ->set('form.email', 'no-es-un-email')
            ->call('saveSupplier')
            ->assertHasErrors(['form.business_name', 'form.cuit', 'form.email'])
            ->set('form.cuit', '123')
            ->call('saveSupplier')
            ->assertHasErrors(['form.cuit' => 'regex']);

        $this->assertSame(1, Supplier::count());
    }

    public function test_a_supplier_can_be_edited_keeping_its_own_cuit(): void
    {
        $supplier = Supplier::factory()->create(['business_name' => 'BioPampa', 'cuit' => '30-71234567-1']);

        Livewire::test(Suppliers::class)
            ->call('editSupplier', $supplier->id)
            ->set('form.business_name', 'BioPampa Laboratorios S.A.')
            ->call('saveSupplier')
            ->assertHasNoErrors();

        $this->assertSame('BioPampa Laboratorios S.A.', $supplier->fresh()->business_name);
        $this->assertSame('30-71234567-1', $supplier->fresh()->cuit);
    }

    public function test_suppliers_in_use_cannot_be_deleted(): void
    {
        $withProducts = Supplier::factory()->create();
        Product::factory()->create(['supplier_id' => $withProducts->id]);

        $withHistory = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => null]);
        StockMovement::factory()->create(['product_id' => $product->id, 'supplier_id' => $withHistory->id, 'quantity' => 5]);

        $unused = Supplier::factory()->create();

        Livewire::test(Suppliers::class)
            ->call('deleteSupplier', $withProducts->id)
            ->assertDispatched('toast', type: 'error')
            ->call('deleteSupplier', $withHistory->id)
            ->call('deleteSupplier', $unused->id);

        $this->assertModelExists($withProducts);
        $this->assertModelExists($withHistory);
        $this->assertModelMissing($unused);
    }
}
