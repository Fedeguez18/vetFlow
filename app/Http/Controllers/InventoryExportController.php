<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    /**
     * Export the inventory with the same filters as the catalog, as a CSV that Excel (es-AR) opens directly.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $sort = array_key_exists($request->query('sort'), Product::SORTS) ? $request->query('sort') : 'name';

        $products = Product::query()
            ->with(['category', 'supplier', 'nextLot'])
            ->inventory((string) $request->query('q', ''), $request->integer('category') ?: null, $sort);

        $number = fn ($value) => number_format((float) $value, 2, ',', '');

        return response()->streamDownload(function () use ($products, $number) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['SKU', 'Código de barras', 'Artículo', 'Descripción', 'Rubro', 'Proveedor', 'Costo', '% Remarque', '% IVA', 'Precio venta', 'Existencias', 'Stock mínimo', 'Próximo vencimiento', 'Estado', 'Activo'], ';');

            $products->chunk(200, function ($chunk) use ($out, $number) {
                foreach ($chunk as $product) {
                    fputcsv($out, [
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
                        ['expired' => 'Vencido', 'critical' => 'Crítico', 'warning' => 'Atención', 'ok' => 'Al día'][$product->expiry_status],
                        $product->is_active ? 'Sí' : 'No',
                    ], ';');
                }
            });

            fclose($out);
        }, 'inventario-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
