@use('App\Models\Product')
@use('App\Support\Pricing')

@php
    $products = $this->products;
    $lowStock = $this->lowStockProducts;
    $summary = $this->summary;
    $restocks = $this->restocks;

    $stockBadges = [
        'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'low' => 'bg-amber-50 text-amber-700 ring-amber-600/25',
        'critical' => 'bg-rose-50 text-rose-700 ring-rose-600/25',
    ];
    $stockLabels = ['ok' => 'OK', 'low' => 'Stock bajo', 'critical' => 'Crítico'];
    $dateInput = 'rounded-lg border border-slate-300 bg-slate-50 px-2 py-1 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/15';
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('erp.suppliers') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800">‹ Volver a proveedores</a>
            <h1 class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-bold tracking-tight text-slate-900">
                {{ $supplier->business_name }}
                @unless ($supplier->is_active)
                    <span class="rounded-md bg-slate-200 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600">De baja</span>
                @endunless
            </h1>
            @if ($supplier->cuit || $supplier->iva_condition)
                <p class="text-sm text-slate-500">
                    @if ($supplier->cuit) <span class="font-mono">CUIT {{ $supplier->cuit }}</span> @endif
                    @if ($supplier->cuit && $supplier->iva_condition) · @endif
                    {{ $supplier->iva_condition }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('erp.suppliers', ['editar' => $supplier->id]) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Editar datos
            </a>
            @if ($supplier->is_active)
                <button type="button" wire:click="deactivate" wire:confirm="¿Dar de baja a «{{ $supplier->business_name }}»? No va a aparecer más al cargar artículos ni al reponer; sus artículos y el historial se conservan." class="rounded-xl border border-amber-500 px-4 py-2.5 text-sm font-semibold text-amber-700 hover:bg-amber-50">
                    Dar de baja
                </button>
            @else
                <button type="button" wire:click="reactivate" class="rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                    Reactivar
                </button>
            @endif
        </div>
    </div>

    @unless ($supplier->is_active)
        <div class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-600">
            Este proveedor está dado de baja: no aparece al crear o editar artículos ni al reponer stock. Sus artículos y el historial se conservan.
        </div>
    @endunless

    {{-- Contacto + resumen --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Contacto</span>
            <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div class="flex gap-2">
                    <dt class="w-20 shrink-0 text-slate-500">Contacto</dt>
                    <dd class="text-slate-900">{{ $supplier->contact_name ?: 'Sin cargar' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-20 shrink-0 text-slate-500">Teléfono</dt>
                    <dd class="text-slate-900">{{ $supplier->phone ?: 'Sin cargar' }}</dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-20 shrink-0 text-slate-500">Email</dt>
                    <dd class="min-w-0 truncate">
                        @if ($supplier->email)
                            <a href="mailto:{{ $supplier->email }}" class="text-slate-900 hover:underline">{{ $supplier->email }}</a>
                        @else
                            <span class="text-slate-900">Sin cargar</span>
                        @endif
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="w-20 shrink-0 text-slate-500">Dirección</dt>
                    <dd class="text-slate-900">{{ $supplier->fullAddress() ?? 'Sin cargar' }}</dd>
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <dt class="w-20 shrink-0 text-slate-500">Pago</dt>
                    <dd class="text-slate-900">{{ $supplier->payment_terms ?: 'Sin cargar' }}</dd>
                </div>
            </dl>

            @if ($supplier->notes)
                <p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $supplier->notes }}</p>
            @endif

            @if ($this->whatsappUrl)
                <a
                    href="{{ $this->whatsappUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-4 inline-flex items-center gap-2 rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5">
                        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2Zm5.8 14.08c-.24.68-1.42 1.31-1.96 1.36-.5.05-.97.23-3.27-.68-2.77-1.09-4.52-3.92-4.66-4.1-.13-.18-1.11-1.48-1.11-2.82s.7-2 .95-2.27c.25-.27.54-.34.72-.34h.52c.17 0 .39-.06.61.47.24.56.79 1.92.86 2.06.07.14.11.3.02.48-.09.18-.14.3-.27.46-.14.16-.29.36-.41.48-.14.14-.28.29-.12.56.16.27.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.2 1.36.27.14.43.11.59-.07.16-.18.68-.79.86-1.06.18-.27.36-.23.61-.14.25.09 1.58.75 1.85.88.27.14.45.2.52.32.07.11.07.66-.17 1.33Z" />
                    </svg>
                    {{ $lowStock->isNotEmpty() ? 'Pedir reposición por WhatsApp' : 'Escribir por WhatsApp' }}
                </a>
            @else
                <p class="mt-4 text-xs text-slate-500">Cargá un teléfono para contactarlo por WhatsApp.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Última reposición</span>
            <span class="mt-2 block text-2xl font-bold tabular-nums text-slate-900">{{ $summary['last_restock_at']?->format('d/m/Y') ?? '—' }}</span>
            <span class="mt-1 block text-xs text-slate-500">{{ $summary['last_restock_at']?->diffForHumans() ?? 'Todavía no hay reposiciones' }}</span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Reposiciones del mes</span>
            <span class="mt-2 block text-2xl font-bold tabular-nums text-slate-900">{{ $summary['month_restocks'] }}</span>
            <span class="mt-1 block text-xs text-slate-500">{{ $summary['month_units'] }} {{ $summary['month_units'] === 1 ? 'unidad ingresada' : 'unidades ingresadas' }}</span>
        </div>
    </div>

    {{-- Sugerencia de pedido --}}
    @if ($lowStock->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-50/60 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Sugerencia de pedido</h2>
                    <p class="text-xs text-slate-500">Artículos activos con stock bajo o crítico. La cantidad sugerida lleva el stock al doble del mínimo (y a {{ Product::CRITICAL_STOCK + 1 }} unidades como mínimo, para salir de crítico).</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($this->orderText)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <span x-text="copied ? 'Pedido copiado' : 'Copiar pedido'">Copiar pedido</span>
                    </button>
                    @if ($this->whatsappUrl)
                        <a href="{{ $this->whatsappUrl }}" target="_blank" rel="noopener" class="rounded-xl border border-emerald-600 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                            Enviar por WhatsApp
                        </a>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Artículo</th>
                            <th class="px-5 py-3 text-center">Existencias</th>
                            <th class="px-5 py-3 text-center">Mínimo</th>
                            <th class="px-5 py-3 text-right">Cantidad sugerida</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lowStock as $product)
                            <tr wire:key="order-{{ $product->id }}">
                                <td class="px-5 py-3 font-semibold text-slate-900">{{ $product->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-center">
                                    <span class="rounded-md px-2 py-1 text-[11px] font-bold tabular-nums ring-1 ring-inset {{ $stockBadges[$product->stock_status] }}">{{ $product->stock }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-center tabular-nums text-slate-500">{{ $product->min_stock }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-base font-bold tabular-nums text-slate-900">{{ $product->suggestedRestock() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Productos que provee --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Productos que provee</h2>
            <div class="flex items-center gap-2 text-xs font-semibold">
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-600">{{ $products->count() }} {{ $products->count() === 1 ? 'artículo' : 'artículos' }}</span>
                @if ($lowStock->isNotEmpty())
                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-amber-700 ring-1 ring-inset ring-amber-600/25">{{ $lowStock->count() }} con stock bajo</span>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Código / SKU</th>
                        <th class="px-5 py-3">Artículo</th>
                        <th class="px-5 py-3 text-right">Costo</th>
                        <th class="px-5 py-3 text-center">Existencias</th>
                        <th class="px-5 py-3 text-center">Mínimo</th>
                        <th class="px-5 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}" @class(['hover:bg-slate-50/60', 'opacity-50' => ! $product->is_active])>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-500">{{ $product->sku ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="font-bold text-slate-900">{{ $product->name }}</span>
                                @unless ($product->is_active)
                                    <span class="ml-1 inline-block rounded bg-slate-200 px-1.5 text-[10px] font-semibold uppercase text-slate-600">Inactivo</span>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-slate-700">{{ Pricing::money($product->cost_price) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-center">
                                <span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase tabular-nums ring-1 ring-inset {{ $stockBadges[$product->stock_status] }}">{{ $product->stock }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-center tabular-nums text-slate-500">{{ $product->min_stock }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase ring-1 ring-inset {{ $stockBadges[$product->stock_status] }}">{{ $stockLabels[$product->stock_status] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">Este proveedor todavía no tiene artículos asignados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Historial de reposiciones --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Historial de reposiciones</h2>
            <div class="flex flex-wrap items-end gap-2 text-xs">
                <label class="flex flex-col gap-0.5 text-slate-500">
                    Desde
                    <input type="date" wire:model.live="dateFrom" class="{{ $dateInput }}">
                </label>
                <label class="flex flex-col gap-0.5 text-slate-500">
                    Hasta
                    <input type="date" wire:model.live="dateTo" class="{{ $dateInput }}">
                </label>
                @if ($dateFrom !== '' || $dateTo !== '')
                    <button type="button" wire:click="clearDates" class="rounded-lg px-2 py-1.5 font-medium text-teal-700 hover:bg-teal-50">Ver todo</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Fecha</th>
                        <th class="px-5 py-3">Artículo</th>
                        <th class="px-5 py-3">Lote</th>
                        <th class="px-5 py-3 text-right">Cantidad</th>
                        <th class="px-5 py-3">Usuario</th>
                        <th class="px-5 py-3">Comentario / factura</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($restocks as $movement)
                        <tr wire:key="restock-{{ $movement->id }}" class="hover:bg-slate-50/60">
                            <td class="whitespace-nowrap px-5 py-3 tabular-nums text-slate-600">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-3 font-semibold text-slate-900">{{ $movement->product?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">
                                @if ($movement->lot)
                                    {{ $movement->lot->lot_number }}
                                    <span class="block text-slate-400">vence {{ $movement->lot->expiration_date->format('d/m/Y') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-semibold tabular-nums text-emerald-700">+{{ $movement->quantity }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $movement->user?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $movement->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                {{ $dateFrom !== '' || $dateTo !== '' ? 'No hay reposiciones en el período elegido.' : 'Todavía no hay reposiciones de este proveedor.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                @if ($restocks->total() > 0)
                    Viendo {{ $restocks->firstItem() }}–{{ $restocks->lastItem() }} de {{ $restocks->total() }} reposiciones
                @else
                    Sin reposiciones
                @endif
            </span>

            <x-pagination :paginator="$restocks" />
        </div>
    </section>
</div>
