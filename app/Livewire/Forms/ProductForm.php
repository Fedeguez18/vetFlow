<?php

namespace App\Livewire\Forms;

use App\Models\Product;
use App\Support\Pricing;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Product create/edit form: validation rules (the Livewire counterpart of a Form Request) and the
 * bidirectional price calculation.
 */
class ProductForm extends Form
{
    public ?Product $product = null;

    public string $barcode = '';

    public string $sku = '';

    public string $name = '';

    public string $description = '';

    public ?int $category_id = null;

    public ?int $supplier_id = null;

    public string $cost_price = '';

    public string $markup_percent = '';

    public string $vat_percent = '21';

    public string $price = '';

    public bool $round = false;

    public int $round_to = 10;

    public bool $tracks_expiry = false;

    public string $initial_stock = '0';

    public string $min_stock = '0';

    public bool $is_active = true;

    /**
     * Initial lots when creating a product that tracks expiry.
     *
     * @var array<int, array{lot_number: string, quantity: string, expiration_date: string}>
     */
    public array $lots = [];

    public function setProduct(Product $product): void
    {
        $this->product = $product;
        $this->barcode = (string) $product->barcode;
        $this->sku = (string) $product->sku;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->category_id = $product->category_id;
        $this->supplier_id = $product->supplier_id;
        $this->cost_price = self::plain($product->cost_price);
        $this->markup_percent = self::plain($product->markup_percent);
        $this->vat_percent = self::plain($product->vat_percent);
        $this->price = self::plain($product->price);
        $this->tracks_expiry = $product->tracks_expiry;
        $this->min_stock = (string) $product->min_stock;
        $this->is_active = $product->is_active;
        $this->lots = [];
    }

    public function isEditing(): bool
    {
        return $this->product !== null;
    }

    /**
     * final = cost × (1 + markup/100) × (1 + vat/100), with the optional rounding.
     */
    public function recalculatePrice(): void
    {
        $cost = Pricing::parse($this->cost_price);
        $markup = Pricing::parse($this->markup_percent);
        $vat = Pricing::parse($this->vat_percent);

        if ($cost === null || $markup === null || $vat === null) {
            return;
        }

        $final = Pricing::finalPrice($cost, $markup, $vat, $this->round ? $this->round_to : 0);
        $this->price = self::plain($final);

        if ($this->round) {
            $this->recalculateMarkup();
        }
    }

    /**
     * The final price was typed by hand: keep cost and VAT, derive the markup.
     */
    public function recalculateMarkup(): void
    {
        $cost = Pricing::parse($this->cost_price);
        $vat = Pricing::parse($this->vat_percent);
        $final = Pricing::parse($this->price);

        if ($cost === null || $vat === null || $final === null) {
            return;
        }

        $markup = Pricing::markupFromFinal($cost, $vat, $final);

        if ($markup !== null) {
            $this->markup_percent = self::plain($markup);
        }
    }

    public function addLot(): void
    {
        $this->lots[] = ['lot_number' => '', 'quantity' => '', 'expiration_date' => ''];
    }

    public function removeLot(int $index): void
    {
        unset($this->lots[$index]);
        $this->lots = array_values($this->lots);
    }

    public function lotsTotal(): int
    {
        return array_sum(array_map(fn ($lot) => max(0, (int) $lot['quantity']), $this->lots));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = ! $this->isEditing();

        return [
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($this->product?->id)],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($this->product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            // A negative markup is allowed: the final price below cost only triggers a warning.
            'markup_percent' => ['required', 'numeric', 'min:-100'],
            'vat_percent' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'tracks_expiry' => ['boolean'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'initial_stock' => $creating && ! $this->tracks_expiry ? ['required', 'integer', 'min:0'] : ['nullable'],
            'lots' => ['array'],
            'lots.*.lot_number' => $creating && $this->tracks_expiry ? ['required', 'string', 'max:60', 'distinct:ignore_case'] : ['nullable'],
            'lots.*.quantity' => $creating && $this->tracks_expiry ? ['required', 'integer', 'min:1'] : ['nullable'],
            'lots.*.expiration_date' => $creating && $this->tracks_expiry ? ['required', 'date'] : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'barcode.unique' => 'Ya existe un artículo con ese código de barras.',
            'sku.unique' => 'Ya existe un artículo con ese SKU.',
            'name.required' => 'El nombre es obligatorio.',
            '*.required' => 'Obligatorio.',
            '*.numeric' => 'Tiene que ser un número.',
            '*.integer' => 'Tiene que ser un número entero.',
            '*.min' => 'No puede ser negativo.',
            'markup_percent.min' => 'El remarque no puede ser menor a -100%.',
            'lots.*.lot_number.required' => 'Número de lote obligatorio.',
            'lots.*.lot_number.distinct' => 'Lote repetido.',
            'lots.*.quantity.required' => 'Cantidad obligatoria.',
            'lots.*.quantity.min' => 'Mayor a cero.',
            'lots.*.expiration_date.required' => 'Vencimiento obligatorio.',
            'lots.*.expiration_date.date' => 'Fecha inválida.',
        ];
    }

    /**
     * Turn the typed amounts ("1.500,50") into plain numbers before validating.
     */
    public function normalize(): void
    {
        foreach (['cost_price', 'markup_percent', 'vat_percent', 'price'] as $field) {
            $value = Pricing::parse($this->{$field});
            $this->{$field} = $value === null ? trim($this->{$field}) : self::plain($value);
        }

        $this->barcode = trim($this->barcode);
        $this->sku = strtoupper(trim($this->sku));
    }

    /**
     * Validated product attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'barcode' => $this->barcode !== '' ? $this->barcode : null,
            'sku' => $this->sku !== '' ? $this->sku : null,
            'name' => trim($this->name),
            'description' => trim($this->description) ?: null,
            'category_id' => $this->category_id,
            'supplier_id' => $this->supplier_id,
            'cost_price' => round((float) $this->cost_price, 2),
            'markup_percent' => round((float) $this->markup_percent, 2),
            'vat_percent' => round((float) $this->vat_percent, 2),
            'price' => round((float) $this->price, 2),
            'tracks_expiry' => $this->tracks_expiry,
            'min_stock' => (int) $this->min_stock,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * Number without thousands separators and without trailing zeros: 18200, 10.5.
     */
    public static function plain(mixed $value): string
    {
        $value = round((float) $value, 2);

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
