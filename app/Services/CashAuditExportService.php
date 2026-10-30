<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class CashAuditExportService
{
    public const HEADERS = ['Hora', 'Comprobante', 'Tipo', 'Cliente', 'Método de pago', 'Usuario', 'Rol', 'Monto', 'Cobrado'];

    protected const KINDS = ['contado' => 'Ticket', 'remito' => 'Remito', 'cobro' => 'Cobro Cta. Cte.'];

    public function __construct(protected CashAuditService $audit) {}

    /**
     * Write the audit of the day as a CSV that Excel (es-AR) opens directly: the balance on top and
     * every movement below, with the same numbers as the Auditoría screen.
     * UTF-8 BOM, ";" separator and comma decimals.
     *
     * @param  resource  $output
     */
    public function writeCsv($output, CarbonImmutable $day): void
    {
        $entries = $this->audit->entries($day);
        $summary = $this->audit->summary($entries);
        $methods = CashAuditService::METHODS + ['sin_registrar' => 'Sin registrar'];
        $number = fn ($value) => number_format((float) $value, 2, ',', '');
        $line = fn (array $fields) => fputcsv($output, $fields, ';', escape: '\\');

        fwrite($output, "\xEF\xBB\xBF");

        $line(['Arqueo de caja', $day->format('d/m/Y')]);
        $line(['']);
        $line(['Balance', 'Monto']);
        $line(['Total cobrado', $number($summary['collected'])]);
        $line(['Dinero en efectivo', $number($summary['cash'])]);
        $line(['QR / Transferencia', $number($summary['bank'])]);
        $line(['']);
        $line(['Ingresos por medio de pago', 'Monto']);
        foreach ($summary['by_method'] as $method => $amount) {
            $line([$methods[$method] ?? $method, $number($amount)]);
        }
        $line(['']);
        $line(['Comprobantes', 'Monto', 'Cantidad']);
        $line(['Tickets contado', $number($summary['collected'] - $summary['payments_amount']), $summary['tickets']]);
        $line(['Remitos a cuenta (no ingresan a caja)', $number($summary['remitos_amount']), $summary['remitos']]);
        $line(['Cobros cta. cte.', $number($summary['payments_amount']), $summary['payments']]);
        $line(['']);

        $line(self::HEADERS);
        foreach ($entries as $entry) {
            $line([
                $entry['time']->format('H:i:s'),
                $entry['voucher'],
                self::KINDS[$entry['kind']] ?? $entry['kind'],
                $entry['client'],
                $methods[$entry['method'] ?? 'sin_registrar'] ?? $entry['method'],
                $entry['user'] ?? 'Sin registrar',
                $entry['user_role'],
                $number($entry['amount']),
                $number($entry['collected']),
            ]);
        }
        $line(['', '', '', '', '', '', 'Total cobrado', '', $number($summary['collected'])]);
    }
}
