@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::moneyWithCents($amount);
    $producer = $this->selectedProducer;
@endphp

<div class="grid gap-6 lg:grid-cols-3" x-on:keydown.escape.window="$wire.showPaymentModal && $wire.closePaymentModal()">
    @if ($alert)
        <div class="flex items-start justify-between gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 lg:col-span-3">
            <span>{{ $alert }}</span>
            <button type="button" wire:click="$set('alert', null)" class="opacity-60 hover:opacity-100">&times;</button>
        </div>
    @endif

    {{-- Master: producers --}}
    <section class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:max-h-[calc(100vh-11rem)]">
        <div class="space-y-3 border-b border-slate-200 p-4">
            <div class="flex items-baseline justify-between">
                <h1 class="font-semibold text-slate-900">Estancias / Productores</h1>
                <span class="text-xs text-slate-500">{{ $this->producers->count() }}</span>
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
                        ])
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-slate-800">{{ $item->name }}</span>
                            <span class="block truncate text-xs text-slate-500">
                                {{ $item->business_name ?? ($item->cuit ?? 'Sin datos fiscales') }}
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span @class(['block font-semibold tabular-nums', 'text-rose-600' => $item->balance > 0, 'text-emerald-700' => $item->balance <= 0])>
                                {{ $item->balance > 0 ? $money($item->balance) : 'Al día' }}
                            </span>
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
                    {{ trim($search) !== '' ? 'Ningún productor coincide con la búsqueda.' : 'Todavía no hay productores cargados.' }}
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
                    <h2 class="text-lg font-semibold text-slate-900">{{ $producer->name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ collect([$producer->business_name, $producer->cuit ? 'CUIT '.$producer->cuit : null, $producer->phone ? 'Tel. '.$producer->phone : null])->filter()->join(' · ') ?: 'Sin datos de contacto' }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="block text-xs uppercase tracking-wide text-slate-500">Saldo deudor</span>
                    <span @class(['text-3xl font-bold tabular-nums', 'text-rose-600' => $producer->balance > 0, 'text-emerald-700' => $producer->balance <= 0])>
                        {{ $money($producer->balance) }}
                    </span>
                </div>
                <div class="flex w-full flex-wrap justify-end gap-2">
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
                        wire:click="openPaymentModal"
                        @disabled($producer->balance <= 0)
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
                                    <span class="font-mono font-medium text-slate-800">{{ $sale->voucher_number }}</span>
                                    @if ($sale->status === 'parcial')
                                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Pago parcial</span>
                                    @endif
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
                                        <span class="block text-xs text-slate-500">de {{ $money($sale->total) }}</span>
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

            @if ($this->recentPayments->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <h3 class="border-b border-slate-200 px-4 py-3 font-semibold text-slate-800">Últimos pagos</h3>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($this->recentPayments as $payment)
                            <li wire:key="payment-{{ $payment->id }}" class="flex items-center justify-between gap-4 px-4 py-2.5">
                                <span class="text-slate-600">
                                    {{ $payment->created_at->format('d/m/Y H:i') }} · {{ $methods[$payment->payment_method] ?? $payment->payment_method }}
                                    @if ($payment->notes)
                                        <span class="text-slate-400">· {{ $payment->notes }}</span>
                                    @endif
                                </span>
                                <span class="font-semibold tabular-nums text-emerald-700">{{ $money($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </section>

    {{-- Payment modal --}}
    @if ($showPaymentModal && $producer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" wire:click.self="closePaymentModal">
            <form wire:submit="registerPayment" class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Registrar pago / entrega a cuenta</h2>
                    <p class="text-sm text-slate-500">{{ $producer->name }} · saldo actual {{ $money($producer->balance) }}</p>
                </div>

                <div>
                    <div class="mb-1 flex items-baseline justify-between">
                        <label for="payment-amount" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monto</label>
                        <button type="button" wire:click="fillFullBalance" class="text-xs font-medium text-teal-700 hover:text-teal-900">Saldo total</button>
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
                    <span @class(['text-xl font-bold tabular-nums', 'text-rose-300' => $newBalance !== null && $newBalance < 0])>
                        @if ($newBalance === null)
                            —
                        @elseif ($newBalance < 0)
                            Supera el saldo
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
</div>
