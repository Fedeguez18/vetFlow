@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::moneyWithCents($amount);
    $summary = $this->summary;
    $isToday = $this->day->isToday();

    $kinds = [
        'contado' => ['Ticket', 'bg-teal-100 text-teal-800'],
        'remito' => ['Remito', 'bg-amber-100 text-amber-800'],
        'cobro' => ['Cobro Cta. Cte.', 'bg-emerald-100 text-emerald-800'],
    ];

    $kpis = [
        [$isToday ? 'Total cobrado hoy' : 'Total cobrado', $money($summary['collected']), 'Ventas de contado + cobros de cuenta corriente', 'from-emerald-500 to-teal-500 text-white', 'text-emerald-50'],
        ['Dinero en efectivo', $money($summary['cash']), 'Caja física', 'bg-white text-slate-900', 'text-slate-500'],
        ['QR / Transferencia', $money($summary['bank']), 'Ingresos en cuenta bancaria', 'bg-white text-slate-900', 'text-slate-500'],
        ['Comprobantes emitidos', $summary['vouchers'], $summary['tickets'].' '.($summary['tickets'] === 1 ? 'ticket' : 'tickets').' · '.$summary['remitos'].' '.($summary['remitos'] === 1 ? 'remito' : 'remitos'), 'bg-white text-slate-900', 'text-slate-500'],
    ];
@endphp

<div
    x-data="{
        printReport() {
            const frame = document.createElement('iframe');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            document.body.appendChild(frame);
            const doc = frame.contentWindow.document;
            doc.open();
            doc.write(`<!DOCTYPE html><html><head><meta charset='utf-8'><title>Arqueo de caja</title><style>
                @page{size:80mm auto;margin:0}
                body{font-family:ui-monospace,monospace;font-size:12px;width:72mm;margin:0 auto;padding:4mm 0;color:#000}
                h1{font-size:16px;margin:0;text-align:center}h2{font-size:12px;margin:6px 0 2px;text-transform:uppercase}
                .c{text-align:center}.row{display:flex;justify-content:space-between;gap:8px}.muted{color:#444;font-size:11px}
                hr{border:0;border-top:1px dashed #000;margin:6px 0}.total{font-size:15px;font-weight:bold}
                .blank{margin-top:10px}.sign{margin-top:28px;border-top:1px solid #000;text-align:center;font-size:11px;padding-top:2px}
            </style><\/head><body>${this.$refs.report.innerHTML}<\/body><\/html>`);
            {{-- Escaped closing tags on purpose: dev tools like Laravel Boost inject their script before every literal head-closing tag, which would break this attribute. --}}
            doc.close();
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 1000);
        },
    }"
    @if ($isToday) wire:poll.30s @endif
    class="space-y-6"
>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Auditoría & Caja</h1>
            <p class="text-sm text-slate-500">
                {{ ucfirst($this->day->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')) }}
                @if ($isToday) · se actualiza cada 30 s @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <input
                type="date"
                wire:change="$set('date', $event.target.value)"
                value="{{ $this->day->toDateString() }}"
                max="{{ today()->toDateString() }}"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/15"
            >
            <button
                type="button"
                x-on:click="printReport()"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                </svg>
                Imprimir arqueo de caja
            </button>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as [$label, $value, $hint, $cardClass, $hintClass])
            <div @class(['rounded-2xl p-5 shadow-sm', 'bg-gradient-to-br' => $loop->first, 'border border-slate-200' => ! $loop->first, $cardClass])>
                <span @class(['block text-xs font-semibold uppercase tracking-wide', $hintClass])>{{ $label }}</span>
                <span class="mt-2 block text-3xl font-bold tabular-nums">{{ $value }}</span>
                <span @class(['mt-1 block text-xs', $hintClass])>{{ $hint }}</span>
            </div>
        @endforeach
    </div>

    {{-- Chronological audit --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
            <h2 class="font-semibold text-slate-800">Transacciones del día</h2>
            <span class="text-xs text-slate-500">{{ $this->entries->count() }} movimientos</span>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Hora</th>
                    <th class="px-4 py-3">Comprobante</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Método de pago</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->entries as $entry)
                    <tr wire:key="{{ $entry['key'] }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-slate-600">{{ $entry['time']->format('H:i:s') }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            <span class="font-mono font-medium text-slate-800">{{ $entry['voucher'] }}</span>
                            <span class="ml-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $kinds[$entry['kind']][1] }}">{{ $kinds[$entry['kind']][0] }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $entry['client'] }}</td>
                        <td @class(['whitespace-nowrap px-4 py-3', 'text-slate-700' => $entry['method'], 'italic text-slate-400' => ! $entry['method']])>
                            {{ $methods[$entry['method'] ?? 'sin_registrar'] ?? $entry['method'] }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <span @class(['font-semibold tabular-nums', 'text-slate-800' => $entry['collected'] > 0, 'text-slate-400' => $entry['collected'] <= 0])>
                                {{ $money($entry['amount']) }}
                            </span>
                            @if ($entry['kind'] === 'remito')
                                <span class="block text-xs text-amber-700">a cuenta, no ingresa a caja</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-slate-500">No hubo movimientos en el día.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($this->entries->isNotEmpty())
                <tfoot class="border-t border-slate-200 bg-slate-50">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-medium text-slate-600">Total cobrado</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900">{{ $money($summary['collected']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    {{-- 80mm cash count report (printed through a hidden iframe) --}}
    <div x-ref="report" class="hidden">
        <h1>veteFlow</h1>
        <div class="c">ARQUEO DE CAJA DIARIO</div>
        <div class="c muted">{{ $this->day->format('d/m/Y') }} · impreso {{ now()->format('d/m/Y H:i') }}</div>
        <hr>
        <h2>Ingresos por medio de pago</h2>
        @forelse ($summary['by_method'] as $method => $amount)
            <div class="row"><span>{{ $methods[$method] ?? $method }}</span><span>{{ $money($amount) }}</span></div>
        @empty
            <div class="muted">Sin ingresos</div>
        @endforelse
        <hr>
        <div class="row total"><span>TOTAL COBRADO</span><span>{{ $money($summary['collected']) }}</span></div>
        <div class="row"><span>Efectivo en caja</span><span>{{ $money($summary['cash']) }}</span></div>
        <div class="row"><span>QR / Transferencia</span><span>{{ $money($summary['bank']) }}</span></div>
        <hr>
        <h2>Comprobantes</h2>
        <div class="row"><span>Tickets contado ({{ $summary['tickets'] }})</span><span>{{ $money($summary['collected'] - $summary['payments_amount']) }}</span></div>
        <div class="row"><span>Remitos a cuenta ({{ $summary['remitos'] }})</span><span>{{ $money($summary['remitos_amount']) }}</span></div>
        <div class="row"><span>Cobros cta. cte. ({{ $summary['payments'] }})</span><span>{{ $money($summary['payments_amount']) }}</span></div>
        <hr>
        <h2>Movimientos</h2>
        @forelse ($this->entries as $entry)
            <div class="row">
                <span>{{ $entry['time']->format('H:i') }} {{ $entry['voucher'] }}</span>
                <span>{{ $entry['kind'] === 'remito' ? '(cta) ' : '' }}{{ $money($entry['amount']) }}</span>
            </div>
            <div class="muted">&nbsp;&nbsp;{{ \Illuminate\Support\Str::limit($entry['client'], 22) }} · {{ $methods[$entry['method'] ?? 'sin_registrar'] ?? $entry['method'] }}</div>
        @empty
            <div class="muted">Sin movimientos</div>
        @endforelse
        <hr>
        <div class="row blank"><span>Efectivo contado:</span><span>$ ____________</span></div>
        <div class="row blank"><span>Diferencia:</span><span>$ ____________</span></div>
        <div class="sign">Firma responsable de caja</div>
    </div>
</div>
