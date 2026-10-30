<?php

namespace App\Http\Controllers;

use App\Services\CashAuditExportService;
use App\Services\CashAuditService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashAuditExportController extends Controller
{
    /**
     * Download the audit of the day chosen in Auditoría: balance and movements.
     */
    public function __invoke(Request $request, CashAuditService $audit, CashAuditExportService $export): StreamedResponse
    {
        $day = $audit->day((string) $request->query('date', ''));

        return response()->streamDownload(function () use ($day, $export) {
            $output = fopen('php://output', 'w');
            $export->writeCsv($output, $day);
            fclose($output);
        }, 'arqueo-'.$day->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
