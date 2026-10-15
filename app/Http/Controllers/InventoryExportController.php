<?php

namespace App\Http\Controllers;

use App\Services\InventoryExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    /**
     * Download the inventory with the same filters as the catalog.
     */
    public function __invoke(Request $request, InventoryExportService $export): StreamedResponse
    {
        return response()->streamDownload(function () use ($request, $export) {
            $output = fopen('php://output', 'w');
            $export->writeCsv($output, (string) $request->query('q', ''), $request->integer('category') ?: null, (string) $request->query('sort', 'name'));
            fclose($output);
        }, 'inventario-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
