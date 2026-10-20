<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Producer;
use App\Models\Sale;
use App\Services\AccountDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Throwable;

class AccountPdfController extends Controller
{
    /**
     * Account statement (resumen de cuenta corriente) of a producer, optionally between ?desde= and ?hasta= (Y-m-d).
     */
    public function statement(Request $request, Producer $producer, AccountDocumentService $documents): Response
    {
        $data = $documents->statement($producer, $this->date($request->query('desde')), $this->date($request->query('hasta')));

        return Pdf::loadView('pdf.account-statement', $data)
            ->setPaper('a4')
            ->stream('resumen-'.Str::slug($producer->name).'-'.today()->format('Y-m-d').'.pdf');
    }

    /**
     * All the merchandise of the producer's remitos between ?desde= and ?hasta=, in the remito format.
     */
    public function remitosDetail(Request $request, Producer $producer, AccountDocumentService $documents): Response
    {
        $data = $documents->remitosDetail($producer, $this->date($request->query('desde')), $this->date($request->query('hasta')));

        return Pdf::loadView('pdf.remitos-detail', $data)
            ->setPaper('a4')
            ->stream('remitos-'.Str::slug($producer->name).'-'.today()->format('Y-m-d').'.pdf');
    }

    public function receipt(Payment $payment, AccountDocumentService $documents): Response
    {
        return Pdf::loadView('pdf.receipt', $documents->receipt($payment))
            ->setPaper('a4')
            ->stream("recibo-{$payment->voucher_number}.pdf");
    }

    public function remito(Sale $sale, AccountDocumentService $documents): Response
    {
        abort_unless($sale->type === 'remito', 404);

        return Pdf::loadView('pdf.remito', $documents->remito($sale))
            ->setPaper('a4')
            ->stream("remito-{$sale->voucher_number}.pdf");
    }

    protected function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
