<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Support\Pricing;
use Carbon\CarbonImmutable;

/**
 * The "Importar stock" spreadsheet: the inventory export format ("Exportar a Excel") as an empty CSV
 * template, and the reader that turns it into the same rows the PDF price list produces.
 * Columns the import doesn't use (Proveedor, % Remarque, Precio venta, Stock mínimo, Estado, Activo) are skipped.
 */
class StockImportSpreadsheet
{
    /**
     * Header names accepted for each column, compared without accents or case. When several headers
     * match, the one earlier in the list wins: "Artículo" is the name even if "Descripción" is also there.
     */
    protected const COLUMNS = [
        'category' => ['rubro', 'categoria'],
        'sku' => ['sku', 'codigo', 'cod', 'cod.'],
        'barcode' => ['codigo de barras', 'ean'],
        'name' => ['articulo', 'producto', 'nombre', 'descripcion', 'detalle'],
        'quantity' => ['existencias', 'cantidad', 'cant', 'cant.', 'unidades', 'stock'],
        'cost' => ['costo', 'precio de lista', 'precio lista', 'precio', 'importe'],
        'vat' => ['% iva', 'iva %', 'iva', 'iva%'],
        'lot_number' => ['lote', 'nro de lote', 'numero de lote'],
        'expiration_date' => ['proximo vencimiento', 'vencimiento', 'vence', 'fecha de vencimiento'],
    ];

    /**
     * Only the header row of the inventory export plus "Lote", which the export doesn't have and
     * products with expiry need: every line below it is read as a product.
     * UTF-8 BOM and ";" separator, like the export.
     *
     * @param  resource  $output
     */
    public function writeTemplate($output): void
    {
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [...InventoryExportService::HEADERS, 'Lote'], ';', escape: '\\');
    }

    /**
     * @return array{rows: list<array{category: string, code: string, name: string, quantity: int, cost: ?float, vat: ?float, lot_number: string, expiration_date: string}>, ignored: list<string>}
     *
     * @throws StockException when the file has no template header row.
     */
    public function parseFile(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Excel "CSV (delimitado por comas)" saves in Windows-1252, not UTF-8.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\R/u', $content);
        $delimiter = $this->delimiter($lines);
        $columns = null;
        $rows = [];
        $ignored = [];

        foreach ($lines as $line) {
            if (trim($line, " \t;,\"") === '') {
                continue;
            }

            $cells = array_map(fn ($cell) => trim((string) $cell), str_getcsv($line, $delimiter, '"', '\\'));

            if ($columns === null) {
                $columns = $this->headerColumns($cells);

                continue;
            }

            $value = fn (string $column) => isset($columns[$column]) ? ($cells[$columns[$column]] ?? '') : '';
            $name = preg_replace('/\s+/u', ' ', $value('name'));

            if ($name === '') {
                $ignored[] = implode(' ', array_filter($cells, fn ($cell) => $cell !== ''));

                continue;
            }

            $quantity = Pricing::parse($value('quantity'));

            $rows[] = [
                'category' => $value('category'),
                'code' => strtoupper($value('sku') ?: $value('barcode')),
                'name' => mb_substr($name, 0, 255),
                'quantity' => $quantity !== null ? max(0, (int) round($quantity)) : 0,
                'cost' => Pricing::parse($value('cost')),
                'vat' => Pricing::parse($value('vat')),
                'lot_number' => $value('lot_number'),
                'expiration_date' => $this->date($value('expiration_date')),
            ];
        }

        if ($columns === null) {
            throw new StockException('La planilla no tiene la fila de títulos de la plantilla (Artículo y Costo). Descargá la plantilla y completala.');
        }

        return ['rows' => $rows, 'ignored' => $ignored];
    }

    /**
     * Column index of each known field when the cells are the header row, or null.
     *
     * @param  list<string>  $cells
     * @return array<string, int>|null
     */
    protected function headerColumns(array $cells): ?array
    {
        $headers = array_map(
            fn (string $cell) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace(['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'], $cell)))),
            $cells,
        );
        $columns = [];

        foreach (self::COLUMNS as $column => $names) {
            foreach ($names as $name) {
                $index = array_search($name, $headers, true);

                if ($index !== false && ! in_array($index, $columns, true)) {
                    $columns[$column] = $index;
                    break;
                }
            }
        }

        return isset($columns['name'], $columns['cost']) ? $columns : null;
    }

    /**
     * ";" (Excel es-AR), tab or ",", whichever splits the first line into more cells.
     *
     * @param  list<string>  $lines
     */
    protected function delimiter(array $lines): string
    {
        $first = collect($lines)->first(fn ($line) => trim($line) !== '') ?? '';
        $counts = [';' => substr_count($first, ';'), "\t" => substr_count($first, "\t"), ',' => substr_count($first, ',')];
        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * "31/12/2027", "31-12-27" or "2027-12-31" as Y-m-d; anything else is kept for the reviewer to fix.
     */
    protected function date(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $match)) {
            [, $year, $month, $day] = $match;
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{2}|\d{4})$/', $value, $match)) {
            [, $day, $month, $year] = $match;
            $year = strlen($year) === 2 ? '20'.$year : $year;
        } else {
            return $value;
        }

        return checkdate((int) $month, (int) $day, (int) $year)
            ? CarbonImmutable::create((int) $year, (int) $month, (int) $day)->toDateString()
            : $value;
    }
}
