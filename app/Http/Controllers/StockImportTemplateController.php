<?php

namespace App\Http\Controllers;

use App\Services\StockImportSpreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockImportTemplateController extends Controller
{
    /**
     * Download the empty spreadsheet that "Importar stock" reads back.
     */
    public function __invoke(StockImportSpreadsheet $spreadsheet): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            $output = fopen('php://output', 'w');
            $spreadsheet->writeTemplate($output);
            fclose($output);
        }, 'plantilla-importar-stock.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
