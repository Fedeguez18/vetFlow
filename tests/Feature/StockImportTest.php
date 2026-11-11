<?php

namespace Tests\Feature;

use App\Exceptions\StockException;
use App\Livewire\StockImportModal;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PriceListPdfParser;
use App\Services\StockImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class StockImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }

    /**
     * A price list PDF like the ones droguerías send: title, table header and a title row per rubro.
     *
     * @param  array<string, list<array{0: string, 1: string, 2: string}>>  $sections  rubro => [code, description, price]
     */
    protected function priceListPdf(array $sections, string $name = 'lista.pdf'): UploadedFile
    {
        $html = '<h1>DROGUERIA EL CAMPO SA</h1><p>Lista de precios sin IVA</p><table><tr><th>Código</th><th>Descripción</th><th>Precio</th></tr>';

        foreach ($sections as $category => $rows) {
            $html .= "<tr><td colspan=\"3\"><b>{$category}</b></td></tr>";

            foreach ($rows as [$code, $description, $price]) {
                $html .= "<tr><td>{$code}</td><td>{$description}</td><td>{$price}</td></tr>";
            }
        }

        $html .= '</table><p>Página 1 de 1</p>';

        return UploadedFile::fake()->createWithContent($name, Pdf::loadHTML($html)->output());
    }

    public function test_the_parser_reads_rubros_codes_and_prices_from_a_price_list(): void
    {
        $pdf = $this->priceListPdf([
            'VACUNAS' => [['AFT-50', 'Vacuna Aftosa x 50 dosis', '$ 12.345,67'], ['7791234567890', 'Bovilis Clostridial 100 ml', '8.900,00']],
            'Antiparasitarios' => [['IVO-500', 'Ivomec 1% x 500 ml', '45.600']],
        ]);

        $result = (new PriceListPdfParser)->parseFile($pdf->getRealPath());

        $this->assertSame([
            ['category' => 'Vacunas', 'code' => 'AFT-50', 'name' => 'Vacuna Aftosa x 50 dosis', 'quantity' => 0, 'cost' => 12345.67, 'vat' => null],
            ['category' => 'Vacunas', 'code' => '7791234567890', 'name' => 'Bovilis Clostridial 100 ml', 'quantity' => 0, 'cost' => 8900.0, 'vat' => null],
            ['category' => 'Antiparasitarios', 'code' => 'IVO-500', 'name' => 'Ivomec 1% x 500 ml', 'quantity' => 0, 'cost' => 45600.0, 'vat' => null],
        ], $result['rows']);
        $this->assertSame(['DROGUERIA EL CAMPO SA', 'Lista de precios sin IVA', 'Página 1 de 1'], $result['ignored']);
    }

    public function test_the_parser_reads_quantity_and_vat_columns_when_the_header_has_them(): void
    {
        $html = '<table><tr><th>Cód.</th><th>Producto</th><th>Cant.</th><th>Precio unit.</th><th>IVA</th></tr>'
            .'<tr><td colspan="5">Rubro: Farmacia general</td></tr>'
            .'<tr><td>F-100</td><td>Oxitetraciclina 20% x 100 ml</td><td>12</td><td>$ 4.500,00</td><td>21%</td></tr>'
            .'<tr><td>F-101</td><td>Dipirona x 50 ml</td><td>3</td><td>1.200,50</td><td>10,5%</td></tr></table>';
        $pdf = UploadedFile::fake()->createWithContent('remito.pdf', Pdf::loadHTML($html)->output());

        $rows = (new PriceListPdfParser)->parseFile($pdf->getRealPath())['rows'];

        $this->assertSame(['Farmacia general', 'F-100', 12, 4500.0, 21.0], [$rows[0]['category'], $rows[0]['code'], $rows[0]['quantity'], $rows[0]['cost'], $rows[0]['vat']]);
        $this->assertSame(['Farmacia general', 'F-101', 3, 1200.5, 10.5], [$rows[1]['category'], $rows[1]['code'], $rows[1]['quantity'], $rows[1]['cost'], $rows[1]['vat']]);
    }

    public function test_the_parser_splits_lines_that_come_as_a_single_text(): void
    {
        $parser = new PriceListPdfParser;

        $rows = $parser->parseLines($parser->linesFromText("VACUNAS\nAFT-50 Vacuna Aftosa x 50 dosis $ 12.345,67\nIvomec x 500 ml   45.600"))['rows'];

        $this->assertSame(['Vacunas', 'AFT-50', 'Vacuna Aftosa x 50 dosis', 12345.67], [$rows[0]['category'], $rows[0]['code'], $rows[0]['name'], $rows[0]['cost']]);
        $this->assertSame(['Vacunas', '', 'Ivomec x 500 ml', 45600.0], [$rows[1]['category'], $rows[1]['code'], $rows[1]['name'], $rows[1]['cost']]);
    }

    public function test_importing_creates_the_missing_rubros_and_products_and_restocks_the_existing_ones(): void
    {
        $supplier = Supplier::factory()->create();
        $pharmacy = Category::factory()->create(['name' => 'Antiparasitarios']);
        $existing = Product::factory()->create([
            'sku' => 'IVO-500', 'category_id' => $pharmacy->id, 'stock' => 10,
            'cost_price' => 40000, 'markup_percent' => 50, 'vat_percent' => 21,
        ]);

        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', $this->priceListPdf([
                'VACUNAS' => [['AFT-50', 'Vacuna Aftosa x 50 dosis', '$ 12.345,67'], ['7791234567890', 'Bovilis Clostridial 100 ml', '8.900,00']],
                'ANTIPARASITARIOS' => [['IVO-500', 'Ivomec 1% x 500 ml', '45.600']],
            ]))
            ->call('readFile')
            ->assertHasNoErrors()
            ->assertSet('fileName', 'lista.pdf')
            ->assertCount('rows', 3)
            ->set('rows.0.quantity', '20')
            ->set('rows.0.lot_number', 'af-2611')
            ->set('rows.0.expiration_date', today()->addYear()->toDateString())
            ->set('rows.2.quantity', '6')
            ->set('supplierId', $supplier->id)
            ->set('markupPercent', '40')
            ->call('import')
            ->assertHasNoErrors()
            ->assertDispatched('stock-updated')
            ->assertDispatched('toast', message: 'Importación lista: 2 artículos nuevos, 1 repuestos (26 unidades), 1 con costo nuevo y 1 rubros nuevos.')
            ->assertSet('open', false);

        $vaccines = Category::where('name', 'Vacunas')->sole();
        $this->assertSame(2, Category::count());

        $aftosa = Product::where('sku', 'AFT-50')->sole();
        $this->assertSame($vaccines->id, $aftosa->category_id);
        $this->assertSame($supplier->id, $aftosa->supplier_id);
        $this->assertTrue($aftosa->tracks_expiry);
        $this->assertSame(20, $aftosa->stock);
        $this->assertSame('AF-2611', $aftosa->lots()->sole()->lot_number);
        $this->assertSame('12345.67', $aftosa->cost_price);
        $this->assertSame('40.00', $aftosa->markup_percent);
        $this->assertSame('20913.56', $aftosa->price);

        $bovilis = Product::where('barcode', '7791234567890')->sole();
        $this->assertSame('7791234567890', $bovilis->sku);
        $this->assertFalse($bovilis->tracks_expiry);
        $this->assertSame(0, $bovilis->stock);

        $existing->refresh();
        $this->assertSame(16, $existing->stock);
        $this->assertSame($pharmacy->id, $existing->category_id);
        $this->assertSame('45600.00', $existing->cost_price);
        $this->assertSame('82764.00', $existing->price);

        $movement = $existing->stockMovements()->sole();
        $this->assertSame('ingreso', $movement->type);
        $this->assertSame(6, $movement->quantity);
        $this->assertSame('Importación PDF: lista.pdf', $movement->reason);
        $this->assertSame($supplier->id, $movement->supplier_id);
        $this->assertSame($this->user->id, $movement->user_id);
    }

    public function test_unchecked_rows_are_not_imported_and_prices_with_vat_are_stored_net(): void
    {
        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', $this->priceListPdf(['VACUNAS' => [['AFT-50', 'Vacuna Aftosa', '1.210,00'], ['BRU-19', 'Vacuna Brucelosis', '5.000,00']]]))
            ->call('readFile')
            ->set('rows.1.include', false)
            ->set('markupPercent', '0')
            ->set('pricesIncludeVat', true)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame(['AFT-50'], Product::pluck('sku')->all());
        $this->assertSame('1000.00', Product::sole()->cost_price);
        $this->assertSame('1210.00', Product::sole()->price);
    }

    public function test_an_existing_product_with_lots_needs_the_lot_to_add_units(): void
    {
        $product = Product::factory()->tracksExpiry()->create(['sku' => 'AFT-50']);

        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', $this->priceListPdf(['VACUNAS' => [['AFT-50', 'Vacuna Aftosa', '1.000,00']]]))
            ->call('readFile')
            ->set('rows.0.quantity', '5')
            ->call('import')
            ->assertHasErrors(['rows.0.lot_number' => 'required', 'rows.0.expiration_date' => 'required']);

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_new_products_need_a_rubro_and_the_markup(): void
    {
        $parsed = Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', UploadedFile::fake()->createWithContent('lista.pdf', Pdf::loadHTML('<p>AFT-50 Vacuna Aftosa $ 1.000,00</p>')->output()))
            ->call('readFile')
            ->assertSet('rows.0.category', '');

        $parsed->call('import')->assertHasErrors(['rows.0.category' => 'required', 'markupPercent' => 'required']);

        $parsed->set('defaultCategory', 'Vacunas')->set('markupPercent', '30')->call('import')->assertHasNoErrors();

        $this->assertSame('Vacunas', Product::where('sku', 'AFT-50')->sole()->category->name);
    }

    public function test_a_failing_row_rolls_back_the_whole_import(): void
    {
        $vaccine = Product::factory()->tracksExpiry()->create(['sku' => 'BRU-19']);
        $row = ['category' => 'Vacunas', 'quantity' => 4, 'cost' => 1000.0, 'vat' => null, 'lot_number' => '', 'expiration_date' => ''];

        try {
            app(StockImportService::class)->import([
                ['code' => 'AFT-50', 'name' => 'Vacuna Aftosa'] + $row,
                ['code' => 'BRU-19', 'name' => 'Vacuna Brucelosis'] + $row,
            ], [
                'supplier_id' => null, 'markup_percent' => 30.0, 'vat_percent' => 21.0, 'prices_include_vat' => false,
                'update_costs' => true, 'default_category' => '', 'reason' => 'Importación PDF: lista.pdf',
            ], $this->user->id);

            $this->fail('The import should have failed.');
        } catch (StockException $e) {
            $this->assertSame('Fila 2 (Vacuna Brucelosis): El número de lote y la fecha de vencimiento son obligatorios para este producto.', $e->getMessage());
        }

        $this->assertSame(0, Category::count());
        $this->assertSame(['BRU-19'], Product::pluck('sku')->all());
        $this->assertSame(0, $vaccine->fresh()->stock);
    }

    public function test_a_pdf_without_prices_or_a_file_that_is_not_a_pdf_is_rejected(): void
    {
        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', UploadedFile::fake()->createWithContent('carta.pdf', Pdf::loadHTML('<p>Estimado cliente, adjuntamos novedades.</p>')->output()))
            ->call('readFile')
            ->assertHasErrors('file')
            ->assertSet('rows', [])
            ->set('file', UploadedFile::fake()->create('lista.xlsx', 10))
            ->call('readFile')
            ->assertHasErrors(['file' => 'extensions']);
    }

    public function test_the_template_downloads_with_the_columns_the_import_reads(): void
    {
        $response = $this->get(route('erp.inventory.import-template'))->assertOk();

        $this->assertStringContainsString('plantilla-importar-stock.csv', $response->headers->get('Content-Disposition'));
        $this->assertSame("\xEF\xBB\xBFSKU;\"Código de barras\";Artículo;Descripción;Rubro;Proveedor;Costo;\"% Remarque\";\"% IVA\";\"Precio venta\";Existencias;\"Stock mínimo\";\"Próximo vencimiento\";Estado;Activo;Lote\n", $response->streamedContent());

        $this->actingAs(User::factory()->create())->get(route('erp.inventory.import-template'))->assertForbidden();
    }

    public function test_the_template_in_the_inventory_export_format_imports(): void
    {
        $csv = "\xEF\xBB\xBFSKU;\"Código de barras\";Artículo;Descripción;Rubro;Proveedor;Costo;\"% Remarque\";\"% IVA\";\"Precio venta\";Existencias;\"Stock mínimo\";\"Próximo vencimiento\";Estado;Activo;Lote\n"
            ."aft-50;7790000000001;Vacuna Aftosa x 50 dosis;Frasco multidosis;Vacunas;;12.345,67;40,00;21,00;20913,56;12;2;31/12/2027;;Sí;af-2611\n"
            .";;;;;;;;;;;;;;;\n"
            .";7791234567890;Ivomec 1% x 500 ml;;Antiparasitarios;;45600,00;;10,50;;3;;;;;\n"
            ."SIN-NOMBRE;;;Sin artículo;;;100;;;;1;;;;;\n";
        $ivomec = Product::factory()->create(['sku' => 'IVO-500', 'barcode' => '7791234567890', 'stock' => 10]);

        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', UploadedFile::fake()->createWithContent('stock.csv', $csv))
            ->call('readFile')
            ->assertHasNoErrors()
            ->assertCount('rows', 2)
            ->assertSet('rows.0.code', 'AFT-50')
            ->assertSet('rows.0.quantity', '12')
            ->assertSet('rows.0.cost', '12345.67')
            ->assertSet('rows.0.name', 'Vacuna Aftosa x 50 dosis')
            ->assertSet('rows.0.category', 'Vacunas')
            ->assertSet('rows.0.vat', 21.0)
            ->assertSet('rows.0.expiration_date', '2027-12-31')
            ->assertSet('rows.1.code', '7791234567890')
            ->assertSet('rows.1.vat', 10.5)
            ->assertSet('rows.0.lot_number', 'af-2611')
            ->assertSet('ignored', ['SIN-NOMBRE Sin artículo 100 1'])
            ->set('markupPercent', '40')
            ->call('import')
            ->assertHasNoErrors();

        $aftosa = Product::where('sku', 'AFT-50')->sole();
        $this->assertSame(12, $aftosa->stock);
        $this->assertTrue($aftosa->tracks_expiry);
        $this->assertSame(['AF-2611', '2027-12-31'], [$aftosa->lots()->sole()->lot_number, $aftosa->lots()->sole()->expiration_date->toDateString()]);
        $this->assertSame(13, $ivomec->fresh()->stock);
        $this->assertSame('Importación planilla: stock.csv', $ivomec->stockMovements()->sole()->reason);
    }

    public function test_a_spreadsheet_saved_by_excel_in_windows_1252_with_commas_is_read(): void
    {
        $csv = mb_convert_encoding("Descripción,Precio,Cantidad\n\"Dipirona x 50 ml\",\"1200,50\",4\n", 'Windows-1252', 'UTF-8');

        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', UploadedFile::fake()->createWithContent('stock.csv', $csv))
            ->call('readFile')
            ->assertHasNoErrors()
            ->assertSet('rows.0.name', 'Dipirona x 50 ml')
            ->assertSet('rows.0.cost', '1200.5')
            ->assertSet('rows.0.quantity', '4');
    }

    public function test_a_spreadsheet_without_the_template_header_is_rejected(): void
    {
        Livewire::test(StockImportModal::class)
            ->call('start')
            ->set('file', UploadedFile::fake()->createWithContent('stock.csv', "AFT-50;Vacuna Aftosa;1000\n"))
            ->call('readFile')
            ->assertHasErrors('file')
            ->assertSet('rows', []);
    }

    public function test_only_administrators_can_import(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(StockImportModal::class)->assertForbidden();
    }
}
