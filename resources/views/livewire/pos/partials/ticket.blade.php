@use('App\Support\Pricing')

{{-- 80 mm ticket / remito content. The POS copies it into a hidden iframe to print it. --}}
@if ($sale)
    <h1>veteFlow</h1>
    <div class="c muted">Veterinaria rural</div>
    <hr>
    <div class="row"><span>{{ $sale->type === 'remito' ? 'REMITO' : 'TICKET' }}</span><span>{{ $sale->voucher_number }}</span></div>
    <div class="row"><span>Fecha</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
    @if ($sale->producer)
        <div class="row"><span>Productor</span><span>{{ $sale->producer->name }}</span></div>
        @if ($sale->producer->cuit)
            <div class="row"><span>CUIT</span><span>{{ $sale->producer->cuit }}</span></div>
        @endif
    @endif
    <div class="row"><span>Cobro</span><span>{{ $paymentLabel }}</span></div>
    <hr>
    @foreach ($sale->saleItems as $item)
        <div>{{ $item->product?->name }}</div>
        <div class="row muted"><span>{{ $item->quantity }} x {{ Pricing::money($item->unit_price) }}</span><span>{{ Pricing::money($item->subtotal) }}</span></div>
    @endforeach
    <hr>
    <div class="row total"><span>TOTAL</span><span>{{ Pricing::money($sale->total) }}</span></div>
    @if ($sale->type === 'remito')
        <br><br>
        <div class="c">______________________</div>
        <div class="c muted">Firma y aclaración</div>
    @endif
    <hr>
    <div class="c muted">Comprobante no válido como factura</div>
@endif
