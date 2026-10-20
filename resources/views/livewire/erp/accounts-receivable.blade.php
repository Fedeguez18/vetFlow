@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::moneyWithCents($amount);
    $producer = $this->selectedProducer;
@endphp

<div class="grid gap-6 lg:grid-cols-3" x-on:keydown.escape.window="($wire.showPaymentModal || $wire.showCreditNoteModal || $wire.showProducerModal || $wire.paymentSuccess) && $wire.closeModals()">
    @if ($alert)
        <div @class([
            'flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm font-medium lg:col-span-3',
            'border-emerald-200 bg-emerald-50 text-emerald-700' => $alertType === 'success',
            'border-rose-200 bg-rose-50 text-rose-700' => $alertType === 'error',
        ])>
            <span>{{ $alert }}</span>
            <button type="button" wire:click="$set('alert', null)" class="opacity-60 hover:opacity-100">&times;</button>
        </div>
    @endif

    {{-- Master: producers --}}
    <section class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:max-h-[calc(100vh-11rem)]">
        <div class="space-y-3 border-b border-slate-200 p-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-baseline gap-2">
                    <h1 class="font-semibold text-slate-900">Estancias / Productores</h1>
                    <span class="text-xs text-slate-500">{{ $this->producers->count() }}</span>
                </div>
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="shrink-0 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:from-emerald-400 hover:to-teal-400"
                >
                    + Nueva cuenta
                </button>
            </div>
            <div class="flex items-baseline justify-between rounded-lg bg-slate-900 px-3 py-2">
                <span class="text-xs uppercase tracking-wide text-slate-400">Total adeudado</span>
                <span class="font-bold tabular-nums text-white">{{ $money($this->totalDebt) }}</span>
            </div>
            <input
                type="search"
                wire:model.live.debounce.250ms="search"
                autocomplete="off"
                placeholder="Buscar por nombre, razón social o CUIT…"
                class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/15"
            >
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input type="checkbox" wire:model.live="showInactive" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/20">
                Mostrar cuentas dadas de baja
            </label>
        </div>

        <ul class="flex-1 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($this->producers as $item)
                <li wire:key="producer-{{ $item->id }}">
                    <button
                        type="button"
                        wire:click="selectProducer({{ $item->id }})"
                        @class([
                            'flex w-full items-center justify-between gap-3 border-l-4 px-4 py-3 text-left transition-colors',
                            'border-emerald-500 bg-emerald-50' => $producerId === $item->id,
                            'border-transparent hover:bg-slate-50' => $producerId !== $item->id,
                            'opacity-50' => ! $item->is_active,
                        ])
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-slate-800">
                                {{ $item->name }}
                                @unless ($item->is_active)
                                    <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Baja</span>
                                @endunless
                            </span>
                            <span class="block truncate text-xs text-slate-500">
                                {{ $item->business_name ?? ($item->cuit ?? 'Sin datos fiscales') }}
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span @class(['block font-semibold tabular-nums', 'text-rose-600' => $item->balance > 0, 'text-emerald-700' => $item->balance <= 0])>
                                @if ($item->balance > 0)
                                    {{ $money($item->balance) }}
                                @elseif ($item->balance < 0)
                                    A favor {{ $money(-$item->balance) }}
                                @else
                                    Al día
                                @endif
                            </span>
                            @if ($item->exceedsCreditLimit(0))
                                <span class="mt-0.5 inline-block rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">Límite excedido</span>
                            @endif
                            @if ($item->unpaid_count > 0)
                                <span class="mt-0.5 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                    {{ $item->unpaid_count }} {{ $item->unpaid_count === 1 ? 'remito pendiente' : 'remitos pendientes' }}
                                </span>
                            @endif
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-sm text-slate-500">
                    {{ trim($search) !== '' ? 'Ningún productor coincide con la búsqueda.' : 'Todavía no hay productores cargados. Creá una cuenta con "+ Nueva cuenta".' }}
                </li>
            @endforelse
        </ul>
    </section>

    {{-- Detail: unpaid remitos --}}
    <section class="space-y-6 lg:col-span-2">
        @if (! $producer)
            <div class="flex h-full min-h-64 items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 p-10 text-center text-slate-500">
                Elegí una estancia o productor de la lista para ver sus remitos adeudados.
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $producer->name }}
                        @unless ($producer->is_active)
                            <span class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 align-middle text-xs font-semibold text-slate-600">Dada de baja</span>
                        @endunless
                    </h2>
                    <p class="text-sm text-slate-500">
                        {{ collect([$producer->business_name, $producer->cuit ? 'CUIT '.$producer->cuit : null, $producer->phone ? 'Tel. '.$producer->phone : null])->filter()->join(' · ') ?: 'Sin datos de contacto' }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="block text-xs uppercase tracking-wide text-slate-500">{{ $producer->hasCreditBalance() ? 'Saldo a favor (acreedor)' : 'Saldo deudor' }}</span>
                    <span @class(['text-3xl font-bold tabular-nums', 'text-rose-600' => $producer->balance > 0, 'text-emerald-700' => $producer->balance <= 0])>
                        {{ $money(abs((float) $producer->balance)) }}
                    </span>
                    <span class="block text-xs text-slate-500">
                        @if ($producer->credit_limit === null)
                            Sin límite de crédito
                        @else
                            Límite {{ $money($producer->credit_limit) }} ·
                            @if ($producer->exceedsCreditLimit(0))
                                <span class="font-semibold text-rose-600">excedido en {{ $money(-$producer->availableCredit()) }}</span>
                            @else
                                disponible {{ $money($producer->availableCredit()) }}
                            @endif
                        @endif
                    </span>
                </div>
                <div class="flex w-full flex-wrap justify-end gap-2">
                    @unless ($producer->isWalkIn())
                        @if ($producer->is_active)
                            <button
                                type="button"
                                wire:click="deactivateProducer"
                                wire:confirm="¿Dar de baja la cuenta de {{ $producer->name }}? No va a aparecer más en el mostrador; el historial se conserva."
                                @disabled($producer->balance != 0)
                                title="{{ $producer->balance != 0 ? 'Para dar de baja la cuenta el saldo tiene que estar en cero.' : '' }}"
                                class="mr-auto rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                Dar de baja
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="reactivateProducer"
                                class="mr-auto rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                            >
                                Reactivar cuenta
                            </button>
                        @endif
                        <button
                            type="button"
                            wire:click="openEditModal"
                            class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Editar datos / límite
                        </button>
                    @endunless
                    <a
                        href="{{ route('erp.accounts.statement-pdf', ['producer' => $producer, 'desde' => $dateFrom ?: null, 'hasta' => $dateTo ?: null]) }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Resumen de cuenta PDF
                    </a>
                    <a
                        href="{{ $this->whatsappUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2Zm5.8 14.08c-.24.68-1.42 1.31-1.96 1.36-.5.05-.97.23-3.27-.68-2.77-1.09-4.52-3.92-4.66-4.1-.13-.18-1.11-1.48-1.11-2.82s.7-2 .95-2.27c.25-.27.54-.34.72-.34h.52c.17 0 .39-.06.61.47.24.56.79 1.92.86 2.06.07.14.11.3.02.48-.09.18-.14.3-.27.46-.14.16-.29.36-.41.48-.14.14-.28.29-.12.56.16.27.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.2 1.36.27.14.43.11.59-.07.16-.18.68-.79.86-1.06.18-.27.36-.23.61-.14.25.09 1.58.75 1.85.88.27.14.45.2.52.32.07.11.07.66-.17 1.33Z" />
                        </svg>
                        Enviar estado por WhatsApp
                    </a>
                    <button
                        type="button"
                        wire:click="openCreditNoteModal"
                        @disabled(! $producer->is_active)
                        class="rounded-xl border border-teal-600 px-4 py-2.5 text-sm font-semibold text-teal-700 hover:bg-teal-50 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Nota de crédito
                    </button>
                    <button
                        type="button"
                        wire:click="openPaymentModal"
                        @disabled(! $producer->is_active)
                        class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:from-emerald-400 hover:to-teal-400 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Registrar pago / entrega a cuenta
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h3 class="font-semibold text-slate-800">Remitos adeudados</h3>
                    <span class="text-xs text-slate-500">Los pagos se imputan del más antiguo al más nuevo</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Comprobante</th>
                            <th class="px-4 py-3">Ítems</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->unpaidSales as $sale)
                            <tr wire:key="sale-{{ $sale->id }}" class="align-top">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                    {{ $sale->created_at->format('d/m/Y') }}
                                    <span class="block text-xs text-slate-400">{{ $sale->created_at->format('H:i') }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <a href="{{ route('erp.remitos.pdf', $sale) }}" target="_blank" rel="noopener" title="Ver remito en PDF" class="font-mono font-medium text-slate-800 underline decoration-slate-300 underline-offset-2 hover:text-teal-700">{{ $sale->voucher_number }}</a>
                                    @if ($sale->status === 'parcial')
                                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Pago parcial</span>
                                    @endif
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        <a href="{{ route('erp.remitos.pdf', $sale) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-md border border-slate-200 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-700">Remito PDF</a>
                                        @foreach ($this->receiptsBySale[$sale->id] ?? [] as $receipt)
                                            <a
                                                wire:key="sale-{{ $sale->id }}-receipt-{{ $receipt['payment']->id }}"
                                                href="{{ route('erp.payments.receipt-pdf', $receipt['payment']) }}"
                                                target="_blank"
                                                rel="noopener"
                                                title="Recibo del {{ $receipt['payment']->created_at->format('d/m/Y') }}: aplicó {{ $money($receipt['applied']) }} a este remito"
                                                class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-400"
                                            >
                                                Recibo {{ $receipt['payment']->voucher_number }}
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <ul class="space-y-0.5">
                                        @foreach ($sale->saleItems as $saleItem)
                                            <li wire:key="sale-item-{{ $saleItem->id }}">
                                                <span class="tabular-nums text-slate-500">{{ $saleItem->quantity }}×</span>
                                                {{ $saleItem->product?->name ?? 'Producto eliminado' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <span class="font-semibold tabular-nums text-slate-800">{{ $money($sale->total - $sale->paid) }}</span>
                                    @if ($sale->paid > 0)
                                        <span class="block text-xs text-slate-500">pagado {{ $money($sale->paid) }} de {{ $money($sale->total) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-slate-500">No tiene remitos adeudados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($this->unpaidSales->isNotEmpty())
                        <tfoot class="border-t border-slate-200 bg-slate-50">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-medium text-slate-600">Total remitos impagos</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums text-slate-900">
                                    {{ $money($this->unpaidSales->sum(fn ($sale) => $sale->total - $sale->paid)) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            {{-- Statement: Debe / Haber / Saldo --}}
            @php
                $statement = $this->statement;
                $kinds = ['remito' => 'Remito', 'pago' => 'Pago', 'nota_credito' => 'Nota de crédito'];
                $signed = fn (float $balance) => $balance < 0 ? $money(-$balance).' a favor' : $money($balance);
            @endphp
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <h3 class="font-semibold text-slate-800">Movimientos de la cuenta</h3>
                    <div class="flex flex-wrap items-end gap-2 text-xs">
                        <label class="flex flex-col gap-0.5 text-slate-500">
                            Desde
                            <input type="date" wire:model.live="dateFrom" class="rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/15">
                        </label>
                        <label class="flex flex-col gap-0.5 text-slate-500">
                            Hasta
                            <input type="date" wire:model.live="dateTo" class="rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/15">
                        </label>
                        <button type="button" wire:click="$set('dateFrom', '')" class="rounded-lg px-2 py-1.5 font-medium text-teal-700 hover:bg-teal-50">Desde el inicio</button>
                        <a
                            href="{{ route('erp.accounts.remitos-pdf', ['producer' => $producer, 'desde' => $dateFrom ?: null, 'hasta' => $dateTo ?: null]) }}"
                            target="_blank"
                            rel="noopener"
                            title="Toda la mercadería de los remitos del período filtrado"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Remitos con detalle PDF
                        </a>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Comprobante</th>
                            <th class="px-4 py-3">Detalle</th>
                            <th class="px-4 py-3 text-right">Debe</th>
                            <th class="px-4 py-3 text-right">Haber</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3 text-right">Comprobante</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr class="bg-slate-50/60 text-slate-500">
                            <td colspan="5" class="px-4 py-2 italic">Saldo anterior</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium tabular-nums">{{ $signed($statement['opening']) }}</td>
                            <td></td>
                        </tr>
                        @forelse ($statement['rows'] as $row)
                            <tr wire:key="movement-{{ $row['key'] }}">
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-700">{{ $row['time']->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @if ($row['kind'] === 'remito')
                                        <a href="{{ route('erp.remitos.pdf', $row['id']) }}" target="_blank" rel="noopener" title="Ver remito en PDF" class="font-mono font-medium text-slate-800 underline decoration-slate-300 underline-offset-2 hover:text-teal-700">{{ $row['voucher'] }}</a>
                                    @elseif ($row['kind'] === 'pago')
                                        <a href="{{ route('erp.payments.receipt-pdf', $row['id']) }}" target="_blank" rel="noopener" title="Ver recibo en PDF" class="font-mono font-medium text-slate-800 underline decoration-slate-300 underline-offset-2 hover:text-teal-700">{{ $row['voucher'] }}</a>
                                    @else
                                        <span class="font-mono font-medium text-slate-800">{{ $row['voucher'] }}</span>
                                    @endif
                                    <span class="block text-xs text-slate-400">{{ $kinds[$row['kind']] }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $row['detail'] ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-rose-600">{{ $row['debit'] > 0 ? $money($row['debit']) : '' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-emerald-700">{{ $row['credit'] > 0 ? $money($row['credit']) : '' }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-slate-800">{{ $signed($row['balance']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                    @if ($row['kind'] !== 'nota_credito')
                                        <a
                                            href="{{ $row['kind'] === 'remito' ? route('erp.remitos.pdf', $row['id']) : route('erp.payments.receipt-pdf', $row['id']) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-700"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            {{ $row['kind'] === 'remito' ? 'Remito' : 'Recibo' }} PDF
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay movimientos en el período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-slate-600">Totales del período</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-rose-600">{{ $money($statement['debit']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-emerald-700">{{ $money($statement['credit']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-900">{{ $signed($statement['closing']) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    {{-- Payment success modal --}}
    @if ($paymentSuccess)
        @php($receiptUrl = route('erp.payments.receipt-pdf', $paymentSuccess['id']))
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="closePaymentSuccess">
            <div role="dialog" aria-modal="true" aria-labelledby="payment-success-title" class="w-full max-w-md space-y-5 rounded-2xl bg-white p-6 text-center shadow-xl">
                <div class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-9 text-emerald-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>

                <div>
                    <h2 id="payment-success-title" class="text-lg font-semibold text-slate-900">Pago registrado</h2>
                    <p class="text-sm text-slate-500">{{ $producer?->name }} · Recibo {{ $paymentSuccess['voucher'] }} · {{ $paymentSuccess['method'] }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-left">
                    <div class="rounded-xl bg-emerald-50 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Monto pagado</dt>
                        <dd class="text-xl font-bold tabular-nums text-emerald-800">{{ $money($paymentSuccess['amount']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-100 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $paymentSuccess['balance'] < 0 ? 'Saldo a favor' : 'Nuevo saldo' }}</dt>
                        <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $money(abs($paymentSuccess['balance'])) }}</dd>
                    </div>
                </dl>

                @if ($paymentSuccess['allocations'] !== [] || $paymentSuccess['unapplied'] > 0)
                    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 text-left text-sm">
                        @foreach ($paymentSuccess['allocations'] as $allocation)
                            <li wire:key="success-allocation-{{ $loop->index }}" class="flex items-center justify-between gap-3 px-3 py-2">
                                <span class="font-mono text-slate-700">{{ $allocation['voucher'] }}</span>
                                <span class="text-right text-xs text-slate-500">
                                    <span class="font-semibold tabular-nums text-slate-800">{{ $money($allocation['applied']) }}</span>
                                    · {{ $allocation['pending'] > 0 ? 'quedan '.$money($allocation['pending']) : 'cancelado' }}
                                </span>
                            </li>
                        @endforeach
                        @if ($paymentSuccess['unapplied'] > 0)
                            <li class="flex items-center justify-between gap-3 px-3 py-2">
                                <span class="text-slate-700">A cuenta (saldo a favor)</span>
                                <span class="text-xs font-semibold tabular-nums text-slate-800">{{ $money($paymentSuccess['unapplied']) }}</span>
                            </li>
                        @endif
                    </ul>
                @endif

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="closePaymentSuccess" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cerrar</button>
                    <a
                        href="{{ $receiptUrl }}"
                        target="_blank"
                        rel="noopener"
                        x-init="$nextTick(() => $el.focus())"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:from-emerald-400 hover:to-teal-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/30"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                        </svg>
                        Imprimir recibo (PDF)
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Payment modal --}}
    @if ($showPaymentModal && $producer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="closePaymentModal">
            <form wire:submit="registerPayment" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Registrar pago / entrega a cuenta</h2>
                    <p class="text-sm text-slate-500">{{ $producer->name }} · {{ $producer->hasCreditBalance() ? 'saldo a favor '.$money(-$producer->balance) : 'saldo actual '.$money($producer->balance) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Si paga más de lo que debe, el excedente queda a favor y se descuenta del próximo remito.</p>
                </div>

                <div>
                    <div class="mb-1 flex items-baseline justify-between">
                        <label for="payment-amount" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</label>
                        @if ($producer->balance > 0)
                            <button type="button" wire:click="fillFullBalance" class="text-xs font-medium text-teal-700 hover:text-teal-900">Saldo total</button>
                        @endif
                    </div>
                    <input
                        id="payment-amount"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="paymentAmount"
                        x-init="$nextTick(() => $el.focus())"
                        autocomplete="off"
                        placeholder="0,00"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-right text-2xl font-semibold tabular-nums text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/15"
                    >
                    @error('paymentAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Medio de pago</span>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($methods as $key => $label)
                            <button
                                type="button"
                                wire:key="method-{{ $key }}"
                                wire:click="$set('paymentMethod', '{{ $key }}')"
                                @class([
                                    'rounded-xl border-2 px-3 py-2 text-sm font-semibold transition-colors',
                                    'border-emerald-500 bg-emerald-50 text-emerald-700' => $paymentMethod === $key,
                                    'border-slate-200 text-slate-600 hover:border-teal-300' => $paymentMethod !== $key,
                                ])
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    @error('paymentMethod') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="payment-notes" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Notas</label>
                    <input
                        id="payment-notes"
                        type="text"
                        wire:model="paymentNotes"
                        placeholder="N° de cheque, transferencia, quién entregó…"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/15"
                    >
                    @error('paymentNotes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                @php($newBalance = $this->balanceAfterPayment)
                <div class="flex items-baseline justify-between rounded-xl bg-slate-900 px-4 py-3 text-white">
                    <span class="text-sm text-slate-400">Saldo luego del pago</span>
                    <span @class(['text-xl font-bold tabular-nums', 'text-emerald-300' => $newBalance !== null && $newBalance < 0])>
                        @if ($newBalance === null)
                            —
                        @elseif ($newBalance < 0)
                            {{ $money(-$newBalance) }} a favor
                        @else
                            {{ $money($newBalance) }}
                        @endif
                    </span>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closePaymentModal" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="registerPayment"
                        class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:from-emerald-400 hover:to-teal-400 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="registerPayment">Confirmar pago</span>
                        <span wire:loading wire:target="registerPayment">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @php($input = 'w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/15')
    @php($label = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500')

    {{-- Credit note modal --}}
    @if ($showCreditNoteModal && $producer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="closeModals">
            <form wire:submit="registerCreditNote" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Emitir nota de crédito</h2>
                    <p class="text-sm text-slate-500">{{ $producer->name }} · {{ $producer->hasCreditBalance() ? 'saldo a favor '.$money(-$producer->balance) : 'saldo actual '.$money($producer->balance) }}</p>
                    <p class="mt-1 text-xs text-slate-400">Baja la deuda como un pago, pero no entra en la caja del día.</p>
                </div>

                <div>
                    <label for="credit-note-amount" class="{{ $label }}">Monto</label>
                    <input
                        id="credit-note-amount"
                        type="text"
                        inputmode="decimal"
                        wire:model.live.debounce.300ms="creditNoteAmount"
                        x-init="$nextTick(() => $el.focus())"
                        autocomplete="off"
                        placeholder="0,00"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-right text-2xl font-semibold tabular-nums text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-emerald-500/15"
                    >
                    @error('creditNoteAmount') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="credit-note-reason" class="{{ $label }}">Motivo</label>
                    <input id="credit-note-reason" type="text" wire:model="creditNoteReason" autocomplete="off" placeholder="Bonificación, error de precio en R-00000012…" class="{{ $input }}">
                    @error('creditNoteReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                @php($balanceAfterNote = $this->balanceAfterCreditNote)
                <div class="flex items-baseline justify-between rounded-xl bg-slate-900 px-4 py-3 text-white">
                    <span class="text-sm text-slate-400">Saldo luego de la nota</span>
                    <span @class(['text-xl font-bold tabular-nums', 'text-emerald-300' => $balanceAfterNote !== null && $balanceAfterNote < 0])>
                        @if ($balanceAfterNote === null)
                            —
                        @elseif ($balanceAfterNote < 0)
                            {{ $money(-$balanceAfterNote) }} a favor
                        @else
                            {{ $money($balanceAfterNote) }}
                        @endif
                    </span>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModals" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="registerCreditNote"
                        class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:from-emerald-400 hover:to-teal-400 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="registerCreditNote">Emitir nota de crédito</span>
                        <span wire:loading wire:target="registerCreditNote">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Create / edit account modal --}}
    @if ($showProducerModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="closeModals">
            <form wire:submit="saveProducer" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div>
                    @if ($form->isEditing())
                        <h2 class="text-lg font-semibold text-slate-900">Editar cuenta</h2>
                        <p class="text-sm text-slate-500">{{ $form->producer->name }}</p>
                    @else
                        <h2 class="text-lg font-semibold text-slate-900">Nueva cuenta corriente</h2>
                        <p class="text-sm text-slate-500">Estancia o productor que va a poder llevar mercadería a remito.</p>
                    @endif
                </div>

                <div>
                    <label for="producer-name" class="{{ $label }}">Nombre / Estancia</label>
                    <input id="producer-name" type="text" wire:model="form.name" x-init="$nextTick(() => $el.focus())" autocomplete="off" placeholder="Estancia La Josefina" class="{{ $input }}">
                    @error('form.name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="producer-business-name" class="{{ $label }}">Razón social / Titular</label>
                    <input id="producer-business-name" type="text" wire:model="form.business_name" autocomplete="off" class="{{ $input }}">
                    @error('form.business_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="producer-cuit" class="{{ $label }}">CUIT</label>
                        <input id="producer-cuit" type="text" inputmode="numeric" wire:model="form.cuit" autocomplete="off" placeholder="20-12345678-9" class="{{ $input }} font-mono">
                        @error('form.cuit') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="producer-phone" class="{{ $label }}">Teléfono (WhatsApp)</label>
                        <input id="producer-phone" type="tel" wire:model="form.phone" autocomplete="off" placeholder="358 412-3344" class="{{ $input }}">
                        @error('form.phone') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="producer-iva" class="{{ $label }}">Condición IVA</label>
                        <select id="producer-iva" wire:model="form.iva_condition" class="{{ $input }}">
                            <option value="">Sin especificar</option>
                            @foreach ($ivaConditions as $condition)
                                <option value="{{ $condition }}">{{ $condition }}</option>
                            @endforeach
                        </select>
                        @error('form.iva_condition') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="producer-discount" class="{{ $label }}">Descuento (%)</label>
                        <input id="producer-discount" type="text" inputmode="decimal" wire:model="form.discount_percent" autocomplete="off" class="{{ $input }} text-right tabular-nums">
                        @error('form.discount_percent') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="producer-credit-limit" class="{{ $label }}">Límite de crédito</label>
                    <input id="producer-credit-limit" type="text" inputmode="decimal" wire:model="form.credit_limit" autocomplete="off" placeholder="Vacío = sin límite" class="{{ $input }} text-right tabular-nums">
                    <p class="mt-1 text-xs text-slate-400">Si un remito lo supera, el mostrador avisa y pide confirmación.</p>
                    @error('form.credit_limit') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeModals" class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="saveProducer"
                        class="rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:from-emerald-400 hover:to-teal-400 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="saveProducer">{{ $form->isEditing() ? 'Guardar cambios' : 'Crear cuenta' }}</span>
                        <span wire:loading wire:target="saveProducer">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
