<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Support\Pricing;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads a supplier price list PDF (text, not a scan) into rows: rubro, code, description,
 * quantity and price. Lines without a price that look like a title ("VACUNAS", "Rubro: Farmacia")
 * become the rubro of the products below them.
 */
class PriceListPdfParser
{
    /**
     * "$ 12.345,67", "8.900,00", "45.600", "1500.50" or "1500".
     */
    protected const MONEY = '/^\$?\s?(\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)$/';

    /**
     * Money at the end of a longer text, only when it is unmistakably an amount ("$ 900", "8.900", "12,50").
     */
    protected const TRAILING_MONEY = '/^(.*?\S)\s*(\$\s?\d[\d.]*(?:,\d{1,2})?|\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?|\d+,\d{2})$/u';

    protected const PERCENT = '/^(\d{1,2}(?:[.,]\d{1,2})?)\s?%$/';

    protected const CODE = '/^(?=.*\d)[A-Za-z0-9][A-Za-z0-9.\-\/]{1,39}$/';

    /**
     * Header column names, checked in this order.
     */
    protected const HEADER_COLUMNS = [
        'code' => '/c[oó]d|sku|ean|barra/iu',
        'quantity' => '/cant|stock|unid|exist/iu',
        'vat' => '/\biva\b/iu',
        'price' => '/precio|importe|costo|valor|^\$$/iu',
        'description' => '/desc|producto|art[ií]culo|detalle|nombre/iu',
    ];

    /**
     * Lines that are never a rubro: page headers, footers and notes.
     */
    protected const NOT_A_CATEGORY = '/p[aá]gina|\bhoja\b|lista de precio|\biva\b|vigencia|vigente|fecha|\btel|cuit|www|http|@|total|condici[oó]n|sujeto a|precios/iu';

    /**
     * @return array{rows: list<array{category: string, code: string, name: string, quantity: int, cost: ?float, vat: ?float}>, ignored: list<string>}
     *
     * @throws StockException when the file can't be read as a PDF.
     */
    public function parseFile(string $path): array
    {
        try {
            $document = (new Parser)->parseFile($path);
        } catch (Throwable) {
            throw new StockException('No se pudo leer el PDF. Revisá que no esté dañado ni protegido con contraseña.');
        }

        $lines = [];

        try {
            foreach ($document->getPages() as $page) {
                array_push($lines, ...$this->linesFromChunks($page->getDataTm()));
            }
        } catch (Throwable) {
            $lines = [];
        }

        if ($lines === []) {
            $lines = $this->linesFromText($document->getText());
        }

        return $this->parseLines($lines);
    }

    /**
     * Group the positioned text chunks of a page into lines (same height) of cells sorted left to right.
     *
     * @param  array<int, array{0: array<int, string>, 1: string}>  $chunks
     * @return list<list<array{x: float, text: string}>>
     */
    public function linesFromChunks(array $chunks): array
    {
        $rows = [];

        foreach ($chunks as [$matrix, $text]) {
            $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

            if ($text === '') {
                continue;
            }

            $x = (float) $matrix[4];
            $y = (float) $matrix[5];
            $key = null;

            foreach (array_keys($rows) as $rowKey) {
                if (abs($rows[$rowKey]['y'] - $y) <= 2.5) {
                    $key = $rowKey;
                    break;
                }
            }

            if ($key === null) {
                $rows[] = ['y' => $y, 'cells' => []];
                $key = array_key_last($rows);
            }

            $rows[$key]['cells'][] = ['x' => $x, 'text' => $text];
        }

        usort($rows, fn (array $a, array $b) => $b['y'] <=> $a['y']);

        return array_map(function (array $row) {
            usort($row['cells'], fn (array $a, array $b) => $a['x'] <=> $b['x']);

            return $row['cells'];
        }, $rows);
    }

    /**
     * Fallback for PDFs without positions: one line per text line, cells split on tabs or wide gaps.
     *
     * @return list<list<array{x: float, text: string}>>
     */
    public function linesFromText(string $text): array
    {
        $lines = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            $cells = array_values(array_filter(array_map('trim', preg_split('/\t| {2,}/u', $line)), fn ($cell) => $cell !== ''));

            if ($cells !== []) {
                $lines[] = array_map(fn (string $cell, int $i) => ['x' => (float) $i * 100, 'text' => $cell], $cells, array_keys($cells));
            }
        }

        return $lines;
    }

    /**
     * @param  list<list<array{x: float, text: string}>>  $lines
     * @return array{rows: list<array{category: string, code: string, name: string, quantity: int, cost: ?float, vat: ?float}>, ignored: list<string>}
     */
    public function parseLines(array $lines): array
    {
        $firstHeader = null;
        $header = null;

        foreach ($lines as $i => $cells) {
            if ($columns = $this->headerColumns($cells)) {
                $firstHeader = $i;
                $header = $columns;
                break;
            }
        }

        $rows = [];
        $ignored = [];
        $category = '';

        foreach ($lines as $i => $cells) {
            $text = implode(' ', array_column($cells, 'text'));

            if ($this->headerColumns($cells)) {
                continue;
            }

            if ($row = $this->productRow($cells, $header)) {
                $rows[] = ['category' => $category] + $row;

                continue;
            }

            // Titles above the table header (company name, "Lista de precios…") are not rubros.
            if (($firstHeader === null || $i > $firstHeader) && ($name = $this->categoryName($text)) !== null) {
                $category = $name;

                continue;
            }

            $ignored[] = $text;
        }

        return ['rows' => $rows, 'ignored' => $ignored];
    }

    /**
     * Column positions when the line is the table header ("Código | Descripción | Precio").
     *
     * @param  list<array{x: float, text: string}>  $cells
     * @return array<string, float>|null
     */
    protected function headerColumns(array $cells): ?array
    {
        $columns = [];

        foreach ($cells as $cell) {
            if (mb_strlen($cell['text']) > 30) {
                return null;
            }

            foreach (self::HEADER_COLUMNS as $column => $pattern) {
                if (! isset($columns[$column]) && preg_match($pattern, $cell['text'])) {
                    $columns[$column] = $cell['x'];
                    break;
                }
            }
        }

        return isset($columns['price']) && (isset($columns['description']) || isset($columns['code'])) ? $columns : null;
    }

    /**
     * @param  list<array{x: float, text: string}>  $cells
     * @param  array<string, float>|null  $header
     * @return array{code: string, name: string, quantity: int, cost: ?float, vat: ?float}|null
     */
    protected function productRow(array $cells, ?array $header): ?array
    {
        $quantity = 0;
        $vat = null;

        // A quantity column only exists when the header names it: take the integer under it.
        if (isset($header['quantity'])) {
            foreach ($cells as $i => $cell) {
                if (preg_match('/^\d{1,6}$/', $cell['text']) && $this->nearestColumn($cell['x'], $header) === 'quantity') {
                    $quantity = (int) $cell['text'];
                    unset($cells[$i]);
                    break;
                }
            }

            $cells = array_values($cells);
        }

        $priceIndex = null;

        foreach ($cells as $i => $cell) {
            if (preg_match(self::MONEY, $cell['text']) && ! ($header && $this->nearestColumn($cell['x'], $header) === 'description')) {
                $priceIndex = $i;
            }
        }

        // The whole line came as one text: split the amount off its end.
        if ($priceIndex === null && $cells !== [] && preg_match(self::TRAILING_MONEY, end($cells)['text'], $match)) {
            $last = array_pop($cells);
            $cells[] = ['x' => $last['x'], 'text' => $match[1]];
            $cells[] = ['x' => $last['x'] + 1, 'text' => $match[2]];
            $priceIndex = count($cells) - 1;
        }

        if ($priceIndex === null) {
            return null;
        }

        foreach (array_slice($cells, $priceIndex + 1) as $cell) {
            if (preg_match(self::PERCENT, $cell['text'], $match)) {
                $vat = Pricing::parse($match[1]);
            }
        }

        $cost = Pricing::parse($cells[$priceIndex]['text']);
        $texts = array_column(array_slice($cells, 0, $priceIndex), 'text');
        $code = '';

        if ($header === null || isset($header['code'])) {
            if (count($texts) > 1 && preg_match(self::CODE, $texts[0])) {
                $code = array_shift($texts);
            } elseif (count($texts) === 1 && preg_match('/^(\S+)\s+(.+)$/u', $texts[0], $match) && preg_match(self::CODE, $match[1])) {
                $code = $match[1];
                $texts = [$match[2]];
            }
        }

        $name = trim(preg_replace('/\s+/u', ' ', implode(' ', $texts)));

        if ($name === '' || ! preg_match('/\p{L}/u', $name)) {
            return null;
        }

        return [
            'code' => strtoupper($code),
            'name' => mb_substr($name, 0, 255),
            'quantity' => $quantity,
            'cost' => $cost,
            'vat' => $vat,
        ];
    }

    /**
     * "VACUNAS" → "Vacunas"; "Rubro: Farmacia general" → "Farmacia general"; anything else → null.
     */
    protected function categoryName(string $text): ?string
    {
        $text = trim(preg_replace('/^(rubro|categor[ií]a|l[ií]nea|secci[oó]n)\s*:?\s*/iu', '', trim($text)), " \t:-–—");
        $words = preg_split('/\s+/u', $text);

        // A wrapped description ("dosis", "con aplicador") starts in lowercase.
        if (mb_strlen($text) < 3 || mb_strlen($text) > 60 || count($words) > 6 || ! preg_match('/^\p{Lu}/u', $text)
            || preg_match('/\d/', $text) || ! preg_match('/\p{L}/u', $text) || preg_match(self::NOT_A_CATEGORY, $text)) {
            return null;
        }

        return mb_strtoupper($text) === $text ? mb_convert_case(mb_strtolower($text), MB_CASE_TITLE) : $text;
    }

    /**
     * @param  array<string, float>  $header
     */
    protected function nearestColumn(float $x, array $header): string
    {
        $distances = array_map(fn (float $columnX) => abs($columnX - $x), $header);
        asort($distances);

        return array_key_first($distances);
    }
}
