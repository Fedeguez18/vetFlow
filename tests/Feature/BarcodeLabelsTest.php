<?php

namespace Tests\Feature;

use App\Livewire\Erp\BarcodeLabels;
use App\Livewire\ProductEditor;
use App\Models\BarcodeLabel;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Ean13;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeLabelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_check_digit_matches_a_real_ean13(): void
    {
        $this->assertSame(4, Ean13::checkDigit('779000111222'));
        $this->assertTrue(Ean13::isValid('4006381333931'));
        $this->assertFalse(Ean13::isValid('4006381333932'));
        $this->assertSame(95, strlen(Ean13::modules('4006381333931')));
    }

    public function test_internal_codes_are_sequential_valid_and_unused(): void
    {
        $first = Ean13::nextInternal();

        $this->assertSame('2000000000015', $first);
        $this->assertTrue(Ean13::isValid($first));

        Product::factory()->create(['barcode' => $first]);
        Product::factory()->create(['barcode' => '7790001112221']);

        $this->assertSame('2000000000022', Ean13::nextInternal());
    }

    public function test_generating_a_code_while_editing_saves_it_and_queues_the_label_with_the_stock(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['barcode' => null, 'name' => 'Amoxicilina 15%', 'stock' => 7]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->call('generateBarcode')
            ->assertSet('form.barcode', '2000000000015')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2000000000015', $product->fresh()->barcode);
        $this->assertSame(7, BarcodeLabel::where('product_id', $product->id)->sole()->copies);
    }

    public function test_a_new_product_with_a_generated_code_is_queued(): void
    {
        $category = Category::factory()->create();

        Livewire::test(ProductEditor::class)
            ->call('create')
            ->call('generateBarcode')
            ->set('form.sku', 'AMX-1')
            ->set('form.name', 'Amoxicilina 15% x 100 ml')
            ->set('form.category_id', $category->id)
            ->set('form.cost_price', '1000')
            ->set('form.markup_percent', '50')
            ->set('form.initial_stock', '0')
            ->call('save')
            ->assertHasNoErrors();

        $label = BarcodeLabel::sole();
        $this->assertSame(Product::sole()->id, $label->product_id);
        $this->assertSame(1, $label->copies);
    }

    public function test_generate_does_nothing_when_the_product_already_has_a_code(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['barcode' => '7790001112221']);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->call('generateBarcode')
            ->assertSet('form.barcode', '7790001112221')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, BarcodeLabel::count());
    }

    public function test_a_generated_code_replaced_by_hand_is_not_queued(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['barcode' => null]);

        Livewire::test(ProductEditor::class)
            ->call('edit', $product->id)
            ->call('generateBarcode')
            ->set('form.barcode', '7790001112221')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, BarcodeLabel::count());
    }

    public function test_copies_can_be_changed_removed_and_the_sheet_cleared(): void
    {
        $label = BarcodeLabel::queue(Product::factory()->create(['barcode' => '2000000000015']), 3);
        BarcodeLabel::queue(Product::factory()->create(['barcode' => '2000000000022']), 2);

        $component = Livewire::test(BarcodeLabels::class)
            ->call('updateCopies', $label->id, '10');
        $this->assertSame(10, $label->fresh()->copies);

        $component->call('updateCopies', $label->id, '0')->assertDispatched('toast');
        $this->assertSame(10, $label->fresh()->copies);

        $component->call('remove', $label->id);
        $this->assertSame(1, BarcodeLabel::count());

        $component->call('clear');
        $this->assertSame(0, BarcodeLabel::count());
    }

    public function test_print_sheet_repeats_each_product_by_its_copies(): void
    {
        BarcodeLabel::queue(Product::factory()->create(['name' => 'Amoxicilina 15%', 'barcode' => '2000000000015']), 3);

        $response = $this->get(route('erp.labels.print'))->assertOk()->assertSee('<svg', false);

        $this->assertSame(3, substr_count($response->getContent(), 'Amoxicilina 15%'));
    }

    public function test_labels_page_lists_the_pending_sheet(): void
    {
        BarcodeLabel::queue(Product::factory()->create(['name' => 'Amoxicilina 15%', 'barcode' => '2000000000015']), 4);

        $this->get(route('erp.labels'))->assertOk()->assertSee('Amoxicilina 15%')->assertSee('Etiquetas (4)');
    }
}
