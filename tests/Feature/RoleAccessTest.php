<?php

namespace Tests\Feature;

use App\Livewire\Erp\AccountsReceivable;
use App\Livewire\Erp\CategoriesSuppliers;
use App\Livewire\Erp\InventoryCatalog;
use App\Livewire\ProductEditor;
use App\Models\Category;
use App\Models\Payment;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function erpPages(): array
    {
        return [
            'inventario' => ['/erp/inventario'],
            'exportar inventario' => ['/erp/inventario/exportar'],
            'cuentas corrientes' => ['/erp/cuentas-corrientes'],
            'auditoría' => ['/erp/auditoria'],
            'exportar auditoría' => ['/erp/auditoria/exportar'],
            'rubros y proveedores' => ['/erp/rubros-proveedores'],
        ];
    }

    #[DataProvider('erpPages')]
    public function test_a_seller_cannot_open_the_erp(string $url): void
    {
        $this->actingAs(User::factory()->create())
            ->get($url)
            ->assertForbidden();
    }

    public function test_a_seller_cannot_open_account_documents(): void
    {
        $producer = Producer::factory()->create(['name' => 'Estancia La Josefina']);
        $payment = Payment::factory()->for($producer)->create();
        $sale = Sale::factory()->remito()->for($producer)->create();

        $this->actingAs(User::factory()->create());

        $this->get(route('erp.accounts.statement-pdf', $producer))->assertForbidden();
        $this->get(route('erp.accounts.remitos-pdf', $producer))->assertForbidden();
        $this->get(route('erp.payments.receipt-pdf', $payment))->assertForbidden();
        $this->get(route('erp.remitos.pdf', $sale))->assertForbidden();
    }

    public function test_a_seller_profile_hides_the_erp_tabs(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())
            ->get('/perfil')
            ->assertOk()
            ->assertSee(route('pos.desk'))
            ->assertDontSee(route('erp.inventory'))
            ->assertDontSee(route('erp.accounts'));
    }

    public function test_an_admin_opens_the_erp(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/erp/inventario')
            ->assertOk();
    }

    public function test_a_credit_note_is_rejected_once_the_user_is_no_longer_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $producer = Producer::factory()->create(['name' => 'Estancia La Josefina', 'balance' => 3000]);
        $this->actingAs($admin);

        $component = Livewire::test(AccountsReceivable::class)
            ->call('selectProducer', $producer->id)
            ->set('creditNoteAmount', '1000')
            ->set('creditNoteReason', 'Bonificación');

        $admin->update(['role' => User::ROLE_SELLER]);

        $component->call('registerCreditNote')->assertForbidden();

        $this->assertSame('3000.00', $producer->fresh()->balance);
        $this->assertSame(0, $producer->creditNotes()->count());
    }

    public function test_categories_and_suppliers_are_not_deleted_once_the_user_is_no_longer_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->actingAs($admin);

        $categories = Livewire::test(CategoriesSuppliers::class);
        $suppliers = Livewire::test(CategoriesSuppliers::class);

        $admin->update(['role' => User::ROLE_SELLER]);

        $categories->call('deleteCategory', $category->id)->assertForbidden();
        $suppliers->call('deleteSupplier', $supplier->id)->assertForbidden();

        $this->assertModelExists($category);
        $this->assertModelExists($supplier);
    }

    public function test_prices_are_not_edited_once_the_user_is_no_longer_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['markup_percent' => 30]);
        $this->actingAs($admin);

        $component = Livewire::test(InventoryCatalog::class);

        $admin->update(['role' => User::ROLE_SELLER]);

        $component->call('updatePercent', $product->id, 'markup_percent', '90')->assertForbidden();

        $this->assertEquals(30, $product->fresh()->markup_percent);
    }

    public function test_stock_is_not_adjusted_once_the_user_is_no_longer_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock' => 20]);
        $this->actingAs($admin);

        $component = Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->set('adjustQuantity', '3')
            ->set('adjustReason', 'rotura');

        $admin->update(['role' => User::ROLE_SELLER]);

        $component->call('adjust')->assertForbidden();

        $this->assertSame(20, $product->fresh()->stock);
        $this->assertSame(0, $product->stockMovements()->count());
    }
}
