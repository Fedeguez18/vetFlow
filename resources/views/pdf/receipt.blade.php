@use('App\Models\Payment')
@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::moneyWithCents($amount);
    $signed = fn (float $balance) => $balance < 0 ? $money(-$balance).' a favor' : $money($balance);
    $method = Payment::METHODS[$payment->payment_method] ?? $payment->payment_method;
@endphp

@extends('pdf.layout', ['client' => $producer])

@section('title', 'Recibo de Pago '.$payment->voucher_number)
@section('date', $payment->created_at->locale('es')->translatedFormat('j \d\e F \d\e Y'))
@section('subtitle', $payment->created_at->format('H:i').' hs')

@section('content')
    <table style="margin-top: 16px;">
        <tr>
            <td style="border: 1px solid #000; padding: 10px 12px; font-size: 10px; line-height: 1.6;">
                Recibimos de <strong>{{ $producer->business_name ?: $producer->name }}</strong>{{ $producer->cuit ? ' (CUIT '.$producer->cuit.')' : '' }}
                la suma de <strong>PESOS {{ mb_strtoupper($amountInWords) }}</strong>
                en concepto de {{ $unapplied > 0 && $allocations->isEmpty() ? 'pago a cuenta' : 'pago de cuenta corriente' }}.
            </td>
            <td style="width: 28%; border: 1px solid #000; border-left: 0; text-align: center; vertical-align: middle;">
                <div class="muted" style="font-size: 7.5px; text-transform: uppercase;">Importe recibido</div>
                <div style="font-size: 15px; font-weight: bold; margin-top: 2px;">{{ $money($payment->amount) }}</div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <tr>
            <td style="width: 22%;"><span class="label">MEDIO DE PAGO:</span></td>
            <td>{{ $method }}{{ $payment->notes ? ' · '.$payment->notes : '' }}</td>
        </tr>
    </table>

    <div class="section">Imputación del pago</div>
    <table class="grid" style="margin-top: 4px;">
        <thead>
            <tr>
                <th style="width: 14%;">Fecha</th>
                <th>Comprobante</th>
                <th style="width: 18%;">Importe remito</th>
                <th style="width: 18%;">Aplicado</th>
                <th style="width: 18%;">Queda pendiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($allocations as $allocation)
                <tr>
                    <td class="c">{{ $allocation['sale']->created_at->format('d/m/Y') }}</td>
                    <td>Remito {{ $allocation['sale']->voucher_number }}</td>
                    <td class="num">{{ $money($allocation['sale']->total) }}</td>
                    <td class="num">{{ $money($allocation['applied']) }}</td>
                    <td class="num">{{ $allocation['pending'] > 0 ? $money($allocation['pending']) : 'Cancelado' }}</td>
                </tr>
            @endforeach
            @if ($unapplied > 0)
                <tr>
                    <td></td>
                    <td colspan="2"><em>A cuenta: queda como saldo a favor para próximos remitos</em></td>
                    <td class="num">{{ $money($unapplied) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        <tr class="line">
            <td style="width: 60%;">Saldo anterior al pago</td>
            <td style="width: 12%;"></td>
            <td class="num">{{ $signed($balance_before) }}</td>
        </tr>
        <tr class="line">
            <td>(−) Este pago</td>
            <td></td>
            <td class="num">{{ $money($payment->amount) }}</td>
        </tr>
        <tr class="grand">
            <td>{{ $balance_after < 0 ? 'Saldo a favor luego del pago' : 'Saldo luego del pago' }}</td>
            <td class="highlight"></td>
            <td class="num highlight">{{ $money(abs($balance_after)) }}</td>
        </tr>
    </table>

    <table style="margin-top: 50px;">
        <tr>
            <td style="width: 50%;"></td>
            <td class="c" style="border-top: 1px solid #000; padding-top: 3px;">Firma y aclaración por {{ config('business.name') }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top: 14px; font-size: 7.5px;">
        Los pagos se imputan a los remitos del más antiguo al más nuevo. Saldos calculados al momento de este pago.
    </p>
@endsection
