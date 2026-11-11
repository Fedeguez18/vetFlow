<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Category;
use App\Models\Product;
use App\Support\Pricing;
use Illuminate\Support\Facades\DB;

/**
 * Loads the rows read from a price list PDF: missing rubros and products are created, existing
 * products get the units as an "ingreso" and, optionally, the new purchase cost. All or nothing.
 */
class StockImportService
{
    public function __construct(
        protected ProductService $products,
        protected StockService $stock,
        protected CategoryService $categories,
    ) {}

    /**
     * The existing product for each row: by code (barcode or SKU), otherwise by name.
     *
     * @param  array<int, array{code: string, name: string}>  $rows
     * @return array<int, Product|null>
     */
    public function matchProducts(array $rows): array
    {
        $codes = collect($rows)->map(fn (array $row) => strtoupper(trim($row['code'])))->filter()->unique()->values()->all();
        $names = collect($rows)->map(fn (array $row) => mb_strtolower(trim($row['name'])))->filter()->unique()->values()->all();

        $candidates = Product::query()
            ->with('category')
            ->where(fn ($query) => $query
                ->whereIn(DB::raw('upper(sku)'), $codes)
                ->orWhereIn('barcode', $codes)
                ->orWhereIn(DB::raw('lower(name)'), $names))
            ->get();

        $byBarcode = $candidates->filter(fn (Product $product) => $product->barcode)->keyBy('barcode');
        $bySku = $candidates->filter(fn (Product $product) => $product->sku)->keyBy(fn (Product $product) => strtoupper($product->sku));
        $byName = $candidates->keyBy(fn (Product $product) => mb_strtolower($product->name));

        return array_map(function (array $row) use ($byBarcode, $bySku, $byName) {
            $code = strtoupper(trim($row['code']));

            return ($code !== '' ? ($byBarcode[$code] ?? $bySku[$code] ?? null) : null)
                ?? $byName[mb_strtolower(trim($row['name']))]
                ?? null;
        }, $rows);
    }

    /**
     * @param  list<array{category: string, code: string, name: string, quantity: int, cost: ?float, vat: ?float, lot_number: string, expiration_date: string}>  $rows
     * @param  array{supplier_id: ?int, markup_percent: float, vat_percent: float, prices_include_vat: bool, update_costs: bool, default_category: string, reason: string}  $options
     * @return array{created: int, restocked: int, units: int, repriced: int, categories: int}
     *
     * @throws StockException naming the row that could not be loaded; nothing is saved.
     */
    public function import(array $rows, array $options, ?int $userId = null): array
    {
        return DB::transaction(function () use ($rows, $options, $userId) {
            $summary = ['created' => 0, 'restocked' => 0, 'units' => 0, 'repriced' => 0, 'categories' => 0];
            $categoryIds = Category::pluck('id', 'name')->mapWithKeys(fn (int $id, string $name) => [mb_strtolower($name) => $id])->all();

            foreach ($rows as $i => $row) {
                $product = $this->matchProducts([$row])[0];

                try {
                    if ($product) {
                        $this->loadExisting($product, $row, $options, $userId, $summary);
                    } else {
                        $this->createProduct($row, $options, $userId, $categoryIds, $summary);
                    }
                } catch (StockException $e) {
                    throw new StockException('Fila '.($i + 1).' ('.$row['name'].'): '.$e->getMessage());
                }
            }

            return $summary;
        });
    }

    /**
     * @param  array{category: string, code: string, name: string, quantity: int, cost: ?float, vat: ?float, lot_number: string, expiration_date: string}  $row
     * @param  array{supplier_id: ?int, markup_percent: float, vat_percent: float, prices_include_vat: bool, update_costs: bool, default_category: string, reason: string}  $options
     * @param  array{created: int, restocked: int, units: int, repriced: int, categories: int}  $summary
     */
    protected function loadExisting(Product $product, array $row, array $options, ?int $userId, array &$summary): void
    {
        $cost = $options['update_costs'] && $row['cost'] !== null
            ? $this->netCost($row['cost'], (float) $product->vat_percent, $options['prices_include_vat'])
            : null;
        $costChanges = $cost !== null && $cost !== round((float) $product->cost_price, 2);

        if ($row['quantity'] > 0) {
            $this->stock->restock(
                $product,
                $row['quantity'],
                strtoupper(trim($row['lot_number'])) ?: null,
                $row['expiration_date'] ?: null,
                $options['supplier_id'] ?? $product->supplier_id,
                $options['reason'],
                $userId,
                $cost,
            );

            $summary['restocked']++;
            $summary['units'] += $row['quantity'];
        } elseif ($costChanges) {
            $this->products->update($product, [
                'cost_price' => $cost,
                'price' => Pricing::finalPrice($cost, (float) $product->markup_percent, (float) $product->vat_percent),
            ], $userId);
        }

        if ($costChanges) {
            $summary['repriced']++;
        }
    }

    /**
     * @param  array{category: string, code: string, name: string, quantity: int, cost: ?float, vat: ?float, lot_number: string, expiration_date: string}  $row
     * @param  array{supplier_id: ?int, markup_percent: float, vat_percent: float, prices_include_vat: bool, update_costs: bool, default_category: string, reason: string}  $options
     * @param  array<string, int>  $categoryIds
     * @param  array{created: int, restocked: int, units: int, repriced: int, categories: int}  $summary
     */
    protected function createProduct(array $row, array $options, ?int $userId, array &$categoryIds, array &$summary): void
    {
        $categoryName = mb_substr(trim($row['category']) ?: trim($options['default_category']), 0, 100);

        if ($categoryName === '') {
            throw new StockException('Falta el rubro.');
        }

        if ($row['cost'] === null || $row['cost'] <= 0) {
            throw new StockException('Falta el precio.');
        }

        if (! isset($categoryIds[mb_strtolower($categoryName)])) {
            $categoryIds[mb_strtolower($categoryName)] = $this->categories->create($categoryName, 'other')->id;
            $summary['categories']++;
        }

        $vat = $row['vat'] ?? $options['vat_percent'];
        $cost = $this->netCost($row['cost'], $vat, $options['prices_include_vat']);
        $code = strtoupper(trim($row['code']));
        $lotNumber = strtoupper(trim($row['lot_number']));
        $tracksExpiry = $lotNumber !== '' && $row['expiration_date'] !== '';

        $this->products->create([
            'sku' => $code !== '' ? $code : $this->generatedSku(),
            'barcode' => preg_match('/^\d{8,14}$/', $code) ? $code : null,
            'name' => trim($row['name']),
            'category_id' => $categoryIds[mb_strtolower($categoryName)],
            'supplier_id' => $options['supplier_id'],
            'cost_price' => $cost,
            'markup_percent' => round($options['markup_percent'], 2),
            'vat_percent' => round($vat, 2),
            'price' => Pricing::finalPrice($cost, $options['markup_percent'], $vat),
            'tracks_expiry' => $tracksExpiry,
            'min_stock' => 0,
            'is_active' => true,
        ], $tracksExpiry && $row['quantity'] > 0
            ? [['lot_number' => $lotNumber, 'quantity' => $row['quantity'], 'expiration_date' => $row['expiration_date']]]
            : [], $tracksExpiry ? 0 : $row['quantity'], $userId);

        $summary['created']++;
        $summary['units'] += $row['quantity'];
    }

    /**
     * Purchase cost without VAT, the way products store it.
     */
    protected function netCost(float $listPrice, float $vatPercent, bool $includesVat): float
    {
        return round($includesVat ? $listPrice / (1 + $vatPercent / 100) : $listPrice, 2);
    }

    /**
     * "IMP-00042" for products that come without a code: the SKU is required.
     */
    protected function generatedSku(): string
    {
        for ($i = Product::count() + 1; ; $i++) {
            $sku = 'IMP-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);

            if (! Product::where('sku', $sku)->exists()) {
                return $sku;
            }
        }
    }
}
