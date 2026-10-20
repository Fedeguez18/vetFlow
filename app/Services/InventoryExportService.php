<?php

namespace App\Services;

use App\Models\Product;

class InventoryExportService
{
    public const HEADERS = ['SKU', 'Código de barras', 'Artículo', 'Descripción', 'Rubro', 'Proveedor', 'Costo', '% Remarque', '% IVA', 'Precio venta', 'Existencias', 'Stock mínimo', 'Próximo vencimiento', 'Estado', 'Activo'];

    protected const EXPIRY_LABELS = ['expired' => 'Vencido', 'critical' => 'Crítico', 'warning' => 'Atención', 'ok' => 'Al día'];

    /**
     * Write the inventory, filtered like the catalog, as a CSV that Excel (es-AR) opens directly:
     * UTF-8 BOM, ";" separator and comma decimals.
     *
     * @param  resource  $output
     */
    public function writeCsv($output, string $search = '', ?int $categoryId = null, string $sort = 'name'): void
    {
        $sort = array_key_exists($sort, Product::SORTS) ? $sort : 'name';

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, self::HEADERS, ';', escape: '\\');

        Product::query()
            ->with(['category', 'supplier', 'nextLot'])
            ->inventory($search, $categoryId, $sort)
            ->chunk(200, function ($products) use ($output) {
                foreach ($products as $product) {
                    fputcsv($output, $this->row($product), ';', escape: '\\');
                }
            });
    }

    /**
     * @return array<int, string|int|null>
     */
    protected function row(Product $product): array
    {
        $number = fn ($value) => number_format((float) $value, 2, ',', '');

        return [
            $product->sku,
            $product->barcode,
            $product->name,
            $product->description,
            $product->category?->name,
            $product->supplier?->business_name,
            $number($product->cost_price),
            $number($product->markup_percent),
            $number($product->vat_percent),
            $number($product->price),
            $product->stock,
            $product->min_stock,
            $product->tracks_expiry ? $product->nextLot?->expiration_date->format('d/m/Y') : '',
            self::EXPIRY_LABELS[$product->expiry_status],
            $product->is_active ? 'Sí' : 'No',
        ];
    }
}
