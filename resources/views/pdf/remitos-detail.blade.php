@php
    $period = match (true) {
        $from && $to => 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y'),
        (bool) $from => 'Desde el '.$from->format('d/m/Y'),
        (bool) $to => 'Hasta el '.$to->format('d/m/Y'),
        default => 'Todos los remitos',
    };
@endphp

@extends('pdf.layout', ['client' => $producer])

@section('title', 'Detalle de Remitos')
@section('date', ($to ?? today())->locale('es')->translatedFormat('j \d\e F \d\e Y'))
@section('subtitle', $period)

@section('content')
    @include('pdf.partials.items', ['lines' => $lines, 'totals' => $totals, 'minRows' => 20, 'showVoucher' => true, 'totalLabel' => 'Total remitos con IVA'])

    <p class="muted" style="margin-top: 10px; font-size: 7.5px;">
        @if ($remitos->isEmpty())
            No hay remitos en el período.
        @else
            {{ $remitos->count() }} {{ $remitos->count() === 1 ? 'remito' : 'remitos' }}: {{ $remitos->pluck('voucher_number')->join(', ') }}.
            Detalle de la mercadería retirada; los pagos y el saldo de la cuenta figuran en el resumen de cuenta corriente.
        @endif
    </p>
@endsection
