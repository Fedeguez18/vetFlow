@use('App\Support\Pricing')

{{-- Product grid of the paper remito: COD / FECHA / DETALLE / CANT. / PRECIO / SUBTOTAL, padded with empty rows. --}}
<table class="grid" style="margin-top: 10px;">
    <thead>
        <tr>
            <th style="width: 14%;">Cod.</th>
            <th style="width: 11%;">Fecha</th>
            <th style="width: 35%;">Detalle</th>
            <th style="width: 8%;">Cant.</th>
            <th style="width: 14%;">Precio</th>
            <th style="width: 18%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $line)
            <tr>
                <td class="c" style="font-size: 7.5px; white-space: nowrap;">{{ $line['code'] }}</td>
                <td class="c">
                    {{ $line['date'] }}
                    @if ($showVoucher ?? false)
                        <div class="muted" style="font-size: 7px;">{{ $line['voucher'] }}</div>
                    @endif
                </td>
                <td class="c">{{ $line['detail'] }}</td>
                <td class="num">{{ number_format($line['quantity'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($line['price'], 2, ',', '.') }}</td>
                <td class="num">{{ Pricing::moneyWithCents($line['subtotal']) }}</td>
            </tr>
        @endforeach
        @for ($i = $lines->count(); $i < $minRows; $i++)
            <tr><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
</table>

<table class="totals">
    <tr class="line">
        <td style="width: 60%;">Subtotal exentos</td>
        <td style="width: 12%;"></td>
        <td class="num">{{ $totals['exempt'] > 0 ? Pricing::moneyWithCents($totals['exempt']) : '' }}</td>
    </tr>
    <tr class="line">
        <td>Subtotal sin IVA</td>
        <td></td>
        <td class="num">{{ Pricing::moneyWithCents($totals['net']) }}</td>
    </tr>
    <tr class="grand">
        <td>{{ $totalLabel ?? 'Total con IVA' }}</td>
        <td class="highlight"></td>
        <td class="num highlight">{{ Pricing::moneyWithCents($totals['total']) }}</td>
    </tr>
</table>
