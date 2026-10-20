@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::moneyWithCents($amount);
    $signed = fn (float $balance) => $balance < 0 ? $money(-$balance).' a favor' : $money($balance);
    $vouchers = ['remito' => 'Remito', 'pago' => 'Recibo', 'nota_credito' => 'Nota de crédito'];
    $period = match (true) {
        $from && $to => 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y'),
        (bool) $from => 'Desde el '.$from->format('d/m/Y'),
        (bool) $to => 'Hasta el '.$to->format('d/m/Y'),
        default => 'Todos los movimientos',
    };
@endphp

@extends('pdf.layout', ['client' => $producer])

@section('title', 'Resumen de Cuenta Corriente')
@section('date', ($to ?? today())->locale('es')->translatedFormat('j \d\e F \d\e Y'))
@section('subtitle', $period)

@section('content')
    {{-- Movements by voucher, like a bank statement --}}
    <table class="grid" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 11%;">Fecha</th>
                <th style="width: 19%;">Comprobante</th>
                <th>Detalle</th>
                <th style="width: 14%;">Debe</th>
                <th style="width: 14%;">Haber</th>
                <th style="width: 15%;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td></td>
                <td colspan="4" class="muted"><em>Saldo anterior</em></td>
                <td class="num">{{ $signed($statement['opening']) }}</td>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td class="c">{{ $row['time']->format('d/m/Y') }}</td>
                    <td>{{ $vouchers[$row['kind']] }} {{ $row['voucher'] }}</td>
                    <td>
                        @if ($row['kind'] === 'remito')
                            <em>{{ $row['status'] }}</em>
                        @elseif ($row['kind'] === 'pago')
                            Pago: {{ $row['detail'] }}
                        @else
                            {{ $row['detail'] }}
                        @endif
                    </td>
                    <td class="num">{{ $row['debit'] > 0 ? $money($row['debit']) : '' }}</td>
                    <td class="num">{{ $row['credit'] > 0 ? '- '.$money($row['credit']) : '' }}</td>
                    <td class="num">{{ $signed($row['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c muted">Sin movimientos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr class="line">
            <td style="width: 60%;">Saldo anterior</td>
            <td style="width: 12%;"></td>
            <td class="num">{{ $signed($statement['opening']) }}</td>
        </tr>
        <tr class="line">
            <td>(+) Remitos del período</td>
            <td></td>
            <td class="num">{{ $money($statement['debit']) }}</td>
        </tr>
        <tr class="line">
            <td>(−) Pagos y notas de crédito</td>
            <td></td>
            <td class="num">{{ $money($statement['credit']) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ $statement['closing'] < 0 ? 'Saldo a favor del cliente' : 'Saldo a la fecha' }}</td>
            <td class="highlight"></td>
            <td class="num highlight">{{ $money(abs($statement['closing'])) }}</td>
        </tr>
    </table>

    {{-- What the current debt is made of --}}
    @if ($openRemitos->isNotEmpty())
        <div class="section">Remitos con saldo pendiente al {{ today()->format('d/m/Y') }}</div>
        <table class="grid" style="margin-top: 4px;">
            <thead>
                <tr>
                    <th style="width: 14%;">Fecha</th>
                    <th>Remito</th>
                    <th style="width: 18%;">Importe total</th>
                    <th style="width: 18%;">Pagado</th>
                    <th style="width: 18%;">Saldo pendiente</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openRemitos as $sale)
                    <tr>
                        <td class="c">{{ $sale->created_at->format('d/m/Y') }}</td>
                        <td>{{ $sale->voucher_number }}</td>
                        <td class="num">{{ $money($sale->total) }}</td>
                        <td class="num">{{ $sale->paid > 0 ? $money($sale->paid) : '' }}</td>
                        <td class="num"><strong>{{ $money((float) $sale->total - (float) $sale->paid) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="muted" style="margin-top: 10px; font-size: 7.5px;">
        Los pagos se imputan a los remitos del más antiguo al más nuevo. El estado de cada remito es el del día de emisión de este resumen.
        Para ver el detalle de productos, pida el remito correspondiente.
    </p>
@endsection
