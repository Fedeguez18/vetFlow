<?php

namespace App\Livewire;

use App\Exceptions\StockException;
use App\Livewire\Concerns\RequiresAdmin;
use App\Livewire\Forms\ProductForm;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\PriceListPdfParser;
use App\Services\StockImportService;
use App\Services\StockImportSpreadsheet;
use App\Support\Pricing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "Importar stock": reads a supplier price list PDF or the template spreadsheet, shows every row for review and loads it into the
 * inventory, creating the rubros and products that don't exist yet.
 */
class StockImportModal extends Component
{
    use RequiresAdmin, WithFileUploads;

    public bool $open = false;

    /**
     * The supplier PDF or the filled template spreadsheet.
     *
     * @var UploadedFile|null
     */
    public $file = null;

    public string $fileName = '';

    /**
     * What was read, for the stock movement reason: "PDF" or "planilla".
     */
    public string $source = 'PDF';

    /**
     * @var list<array{include: bool, category: string, code: string, name: string, quantity: string, cost: string, vat: ?float, lot_number: string, expiration_date: string}>
     */
    public array $rows = [];

    /**
     * File lines that were neither a product nor a rubro, shown so nothing is lost silently.
     *
     * @var list<string>
     */
    public array $ignored = [];

    public ?int $supplierId = null;

    public string $markupPercent = '';

    public string $vatPercent = '21';

    public bool $pricesIncludeVat = false;

    public bool $updateCosts = true;

    public string $defaultCategory = '';

    #[On('stock-import')]
    public function start(): void
    {
        $this->reset();
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->file = null;
        $this->resetValidation();
    }

    /**
     * Read the uploaded supplier PDF as it came, or the filled template spreadsheet (CSV).
     */
    public function readFile(PriceListPdfParser $pdfParser, StockImportSpreadsheet $spreadsheet): void
    {
        $this->validate(['file' => ['required', 'file', 'extensions:pdf,csv,txt', 'max:10240']], [
            'file.required' => 'Elegí el PDF de la lista de precios o la planilla completa.',
            'file.extensions' => 'El archivo tiene que ser un PDF o la planilla en CSV. Si la guardaste como Excel (.xlsx), usá Archivo > Guardar como > CSV.',
            'file.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $isPdf = strtolower($this->file->getClientOriginalExtension()) === 'pdf';

        try {
            $result = $isPdf ? $pdfParser->parseFile($this->file->getRealPath()) : $spreadsheet->parseFile($this->file->getRealPath());
        } catch (StockException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        if ($result['rows'] === []) {
            $this->addError('file', $isPdf
                ? 'No se encontraron productos con precio en el PDF. Si es un escaneo (una foto del papel), no se puede leer.'
                : 'La planilla no tiene productos: completá al menos la descripción y el precio de lista debajo de los títulos.');

            return;
        }

        $this->fileName = $this->file->getClientOriginalName();
        $this->source = $isPdf ? 'PDF' : 'planilla';
        $this->file = null;
        $this->ignored = array_slice($result['ignored'], 0, 200);
        $this->rows = array_map(fn (array $row) => [
            'include' => true,
            'category' => $row['category'],
            'code' => $row['code'],
            'name' => $row['name'],
            'quantity' => (string) $row['quantity'],
            'cost' => $row['cost'] !== null ? ProductForm::plain($row['cost']) : '',
            'vat' => $row['vat'],
            'lot_number' => $row['lot_number'] ?? '',
            'expiration_date' => $row['expiration_date'] ?? '',
        ], $result['rows']);

        unset($this->matches);
    }

    /**
     * Discard the preview and pick another file.
     */
    public function restart(): void
    {
        $this->reset('rows', 'ignored', 'fileName', 'source', 'file');
        $this->resetValidation();
    }

    public function updatedRows(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.code') || str_ends_with($key, '.name')) {
            unset($this->matches);
        }
    }

    public function import(StockImportService $import): void
    {
        $this->normalize();

        $included = array_filter($this->rows, fn (array $row) => $row['include']);

        if ($included === []) {
            $this->addError('rows', 'Marcá al menos un producto para importar.');

            return;
        }

        $matches = $this->matches;
        $rules = [
            'supplierId' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'vatPercent' => ['required', 'numeric', 'min:0'],
            'defaultCategory' => ['nullable', 'string', 'max:100'],
        ];

        foreach ($included as $i => $row) {
            $isNew = $matches[$i] === null;

            if ($isNew) {
                $rules['markupPercent'] = ['required', 'numeric', 'min:-100'];
            }

            $rules["rows.$i.name"] = ['required', 'string', 'max:255'];
            $rules["rows.$i.code"] = ['nullable', 'string', 'max:40'];
            $rules["rows.$i.quantity"] = ['required', 'integer', 'min:0'];
            $rules["rows.$i.cost"] = $isNew ? ['required', 'numeric', 'gt:0'] : ['nullable', 'numeric', 'min:0'];
            $rules["rows.$i.category"] = [$isNew && trim($this->defaultCategory) === '' ? 'required' : 'nullable', 'string', 'max:100'];
            $rules["rows.$i.lot_number"] = [$matches[$i]?->tracks_expiry && (int) $row['quantity'] > 0 ? 'required' : 'nullable', 'string', 'max:60'];
            $rules["rows.$i.expiration_date"] = [$matches[$i]?->tracks_expiry && (int) $row['quantity'] > 0 ? 'required' : 'nullable', 'date'];
        }

        $this->validate($rules, [
            'markupPercent.required' => 'Ingresá el % de remarque para los artículos nuevos.',
            'markupPercent.min' => 'El remarque no puede ser menor a -100%.',
            'vatPercent.required' => 'Ingresá el % de IVA.',
            'supplierId.exists' => 'Ese proveedor está dado de baja.',
            'rows.*.name.required' => 'Falta la descripción.',
            'rows.*.code.max' => 'Máximo 40 caracteres.',
            'rows.*.quantity.required' => 'Obligatorio.',
            'rows.*.quantity.integer' => 'Número entero.',
            'rows.*.quantity.min' => 'No puede ser negativo.',
            'rows.*.cost.required' => 'Falta el precio.',
            'rows.*.cost.gt' => 'Mayor a cero.',
            'rows.*.cost.numeric' => 'Tiene que ser un número.',
            'rows.*.category.required' => 'Falta el rubro.',
            'rows.*.lot_number.required' => 'Lote obligatorio.',
            'rows.*.expiration_date.required' => 'Vencimiento obligatorio.',
            'rows.*.expiration_date.date' => 'Fecha inválida.',
            '*.numeric' => 'Tiene que ser un número.',
        ]);

        try {
            $summary = $import->import(
                array_values(array_map(fn (array $row) => [
                    'category' => trim($row['category']),
                    'code' => strtoupper(trim($row['code'])),
                    'name' => trim($row['name']),
                    'quantity' => (int) $row['quantity'],
                    'cost' => $row['cost'] !== '' ? (float) $row['cost'] : null,
                    'vat' => $row['vat'],
                    'lot_number' => trim($row['lot_number']),
                    'expiration_date' => trim($row['expiration_date']),
                ], $included)),
                [
                    'supplier_id' => $this->supplierId,
                    'markup_percent' => (float) $this->markupPercent,
                    'vat_percent' => (float) $this->vatPercent,
                    'prices_include_vat' => $this->pricesIncludeVat,
                    'update_costs' => $this->updateCosts,
                    'default_category' => trim($this->defaultCategory),
                    'reason' => mb_substr("Importación {$this->source}: ".$this->fileName, 0, 255),
                ],
                auth()->id(),
            );
        } catch (StockException $e) {
            $this->addError('rows', $e->getMessage());

            return;
        }

        $this->open = false;
        $this->dispatch('stock-updated');
        $this->dispatch('toast', message: "Importación lista: {$summary['created']} artículos nuevos, {$summary['restocked']} repuestos "
            ."({$summary['units']} unidades), {$summary['repriced']} con costo nuevo y {$summary['categories']} rubros nuevos.");
    }

    /**
     * The existing product for each preview row (null = it will be created).
     *
     * @return array<int, Product|null>
     */
    #[Computed]
    public function matches(): array
    {
        return app(StockImportService::class)->matchProducts($this->rows);
    }

    /**
     * Existing rubro names, lowercased, to flag the ones the import will create.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function categoryNames(): Collection
    {
        return Category::orderBy('name')->pluck('name');
    }

    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::active()->orderBy('business_name')->get(['id', 'business_name']);
    }

    /**
     * @return array{new: int, existing: int, units: int, categories: list<string>}
     */
    #[Computed]
    public function summary(): array
    {
        $existing = $this->categoryNames->map(fn (string $name) => mb_strtolower($name));
        $summary = ['new' => 0, 'existing' => 0, 'units' => 0, 'categories' => []];

        foreach ($this->rows as $i => $row) {
            if (! $row['include']) {
                continue;
            }

            $summary[$this->matches[$i] ? 'existing' : 'new']++;
            $summary['units'] += max(0, (int) $row['quantity']);
            $category = trim($row['category']) ?: trim($this->defaultCategory);

            if (! $this->matches[$i] && $category !== '' && ! $existing->contains(mb_strtolower($category))) {
                $summary['categories'][mb_strtolower($category)] = $category;
            }
        }

        $summary['categories'] = array_values($summary['categories']);

        return $summary;
    }

    /**
     * Turn the typed amounts ("1.500,50") into plain numbers before validating.
     */
    protected function normalize(): void
    {
        foreach ($this->rows as $i => $row) {
            foreach (['cost', 'quantity'] as $field) {
                $value = trim((string) $row[$field]);
                $parsed = $value !== '' ? Pricing::parse($value) : null;
                $this->rows[$i][$field] = $parsed === null ? $value : ProductForm::plain($parsed);
            }
        }

        foreach (['markupPercent', 'vatPercent'] as $field) {
            $parsed = Pricing::parse($this->{$field});
            $this->{$field} = $parsed === null ? trim($this->{$field}) : ProductForm::plain($parsed);
        }
    }

    public function render()
    {
        return view('livewire.stock-import-modal');
    }
}
