@extends('pdf.layout', ['client' => $sale->producer])

@section('title', 'Remito '.$sale->voucher_number)
@section('date', $sale->created_at->locale('es')->translatedFormat('j \d\e F \d\e Y'))
@section('subtitle', $sale->status === 'pagada' ? 'Pagado' : ($sale->status === 'parcial' ? 'Pago parcial' : 'Pendiente de pago'))

@section('content')
    @include('pdf.partials.items', ['lines' => $lines, 'totals' => $totals, 'minRows' => 25])

    <table style="margin-top: 40px;">
        <tr>
            <td style="width: 50%;"></td>
            <td class="c" style="border-top: 1px solid #000; padding-top: 3px;">Firma y aclaración</td>
        </tr>
    </table>
@endsection
