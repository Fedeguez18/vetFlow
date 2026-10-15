@use('App\Models\Product')
@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::money($amount);
    $kpis = $this->kpis;
    $products = $this->products;

    $categoryColors = [
        'vaccine' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'pharmacy' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'nutrition' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'identification' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'instrument' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
        'accessory' => 'bg-pink-50 text-pink-700 ring-pink-600/20',
    ];

    $stockBadges = [
        'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'low' => 'bg-amber-50 text-amber-700 ring-amber-600/25',
        'critical' => 'bg-rose-50 text-rose-700 ring-rose-600/25',
    ];

    // Page window like "1 2 3 … 13".
    $current = $products->currentPage();
    $last = $products->lastPage();
    $pages = collect([1, $last, $current - 1, $current, $current + 1])
        ->merge($current <= 2 ? [2, 3] : [])
        ->merge($current >= $last - 1 ? [$last - 1, $last - 2] : [])
        ->filter(fn ($page) => $page >= 1 && $page <= $last)
        ->unique()
        ->sort()
        ->values();
@endphp

@push('header-actions')
    <button
        type="button"
        x-data
        x-on:click="Livewire.dispatch('product-create')"
        class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm hover:bg-emerald-600"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Nuevo artículo
    </button>
@endpush

<div class="space-y-6">
    {{-- KPIs --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-start justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total productos</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tabular-nums text-slate-900">{{ $kpis['total'] }}</span>
                    <span class="text-sm text-slate-500">artículos</span>
                </div>
                <span class="mt-1 block text-xs text-slate-400">En catálogo activo</span>
            </div>
            <span class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>
            </span>
        </div>

        <div class="flex items-start justify-between rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Stock crítico ≤ {{ Product::CRITICAL_STOCK }}</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tabular-nums text-amber-600">{{ $kpis['critical_stock'] }}</span>
                    <span class="text-sm text-amber-700/80">productos</span>
                </div>
                <span class="mt-1.5 inline-block rounded-md bg-amber-400 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950">Reposición urgente</span>
            </div>
            <span class="flex size-11 items-center justify-center rounded-xl bg-white text-amber-500 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
            </span>
        </div>

        <div class="flex items-start justify-between rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Lotes por vencer ≤ 60d</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tabular-nums text-rose-600">{{ $kpis['expiring_lots'] }}</span>
                    <span class="text-sm text-rose-700/80">{{ $kpis['expiring_lots'] === 1 ? 'lote' : 'lotes' }}</span>
                </div>
                <span class="mt-1.5 inline-block rounded-md bg-rose-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Crítico &lt; 30 días: {{ $kpis['critical_lots'] }}</span>
            </div>
            <span class="flex size-11 items-center justify-center rounded-xl bg-white text-rose-500 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
        </div>

        <div class="flex items-start justify-between rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Caja del día</span>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tabular-nums text-emerald-800">{{ $money($kpis['cash_today']) }}</span>
                    <span class="text-xs font-semibold text-emerald-700/70">ARS</span>
                </div>
                <span class="mt-1.5 block text-[11px] font-bold uppercase tracking-wide text-emerald-700">Efectivo + QR + Tarjetas</span>
            </div>
            <span class="flex size-11 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
            </span>
        </div>
    </div>

    {{-- Table card --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-3 p-4">
            <div class="relative w-full sm:w-72">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-400">
                    <path stroke-linecap="round" d="M3 5v14M21 5v14" stroke-width="1.5" />
                    <path stroke-linecap="round" d="M6.5 5v14M10 5v14M14.5 5v14M17.5 5v14" stroke-width="1" />
                    <path stroke-linecap="round" d="M8.25 5v14M12.25 5v14" stroke-width="2" />
                </svg>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    autocomplete="off"
                    placeholder="Buscar por código de barras, SKU o nombre…"
                    class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"
                >
            </div>

            <div class="flex min-w-0 flex-1 gap-1 overflow-x-auto rounded-xl border border-slate-200 p-1">
                @foreach ([null => 'Todos'] + $this->categories->pluck('name', 'id')->all() as $id => $name)
                    <button
                        type="button"
                        wire:key="chip-{{ $id ?: 'all' }}"
                        wire:click="setCategory({{ $id ?: 'null' }})"
                        @class([
                            'shrink-0 rounded-lg px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide transition-colors',
                            'bg-[#0F2A26] text-white shadow-sm' => $categoryId === ($id ?: null),
                            'text-slate-500 hover:bg-slate-100 hover:text-slate-800' => $categoryId !== ($id ?: null),
                        ])
                    >{{ $name }}</button>
                @endforeach
            </div>

            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Registros: {{ $products->total() }}</span>

            <div class="flex items-center gap-2">
                <div x-data="{ open: false }" x-on:click.outside="open = false" class="relative">
                    <button type="button" x-on:click="open = ! open" title="Ordenar: {{ Product::SORTS[$sort] ?? '' }}" class="flex size-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25" /></svg>
                    </button>
                    <div x-show="open" x-transition style="display: none" class="absolute right-0 top-full z-20 mt-1 w-60 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        @foreach (Product::SORTS as $key => $sortLabel)
                            <button type="button" wire:key="sort-{{ $key }}" x-on:click="open = false; $wire.setSort('{{ $key }}')" @class(['block w-full px-3 py-2 text-left text-sm hover:bg-slate-50', 'font-semibold text-[#0F2A26]' => $sort === $key, 'text-slate-600' => $sort !== $key])>
                                {{ $sortLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <a
                    href="{{ route('erp.inventory.export', array_filter(['q' => $search, 'category' => $categoryId, 'sort' => $sort])) }}"
                    title="Exportar CSV (Excel)"
                    class="flex size-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Código / SKU</th>
                        <th class="px-5 py-3">Artículo / Droga</th>
                        <th class="px-5 py-3">Rubro</th>
                        <th class="px-5 py-3 text-right">Precio venta</th>
                        <th class="px-5 py-3 text-center">Existencias</th>
                        <th class="px-5 py-3">Vencimiento lote</th>
                        <th class="px-5 py-3">Estado alerta</th>
                        <th class="px-5 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        @php
                            $days = $product->expiry_days;
                            $status = $product->expiry_status;
                        @endphp
                        <tr wire:key="product-{{ $product->id }}" @class(['hover:bg-slate-50/60', 'opacity-50' => ! $product->is_active])>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500">
                                <span class="block font-semibold text-slate-600">{{ $product->sku ?? '—' }}</span>
                                <span class="block">{{ $product->barcode ?? 'Sin código' }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="block font-bold text-slate-900">{{ $product->name }}</span>
                                @if ($product->description)
                                    <span class="block text-[11px] uppercase tracking-wide text-slate-400">{{ $product->description }}</span>
                                @endif
                                @unless ($product->is_active)
                                    <span class="mt-0.5 inline-block rounded bg-slate-200 px-1.5 text-[10px] font-semibold uppercase text-slate-600">Inactivo</span>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                @if ($product->category)
                                    <span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase tracking-wide ring-1 ring-inset {{ $categoryColors[$product->category->icon] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">{{ $product->category->name }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <span class="group relative inline-block cursor-help font-mono text-base font-bold text-slate-900">
                                    {{ $money($product->price) }}
                                    <span class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 hidden w-52 rounded-lg bg-slate-900 p-3 text-left font-sans text-xs font-normal text-slate-200 shadow-lg group-hover:block">
                                        <span class="flex justify-between"><span>Costo</span><span class="tabular-nums">{{ $money($product->cost_price) }}</span></span>
                                        <span class="flex justify-between"><span>Remarque</span><span class="tabular-nums">{{ Pricing::percent($product->markup_percent) }}%</span></span>
                                        <span class="flex justify-between"><span>IVA</span><span class="tabular-nums">{{ Pricing::percent($product->vat_percent) }}%</span></span>
                                    </span>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-center">
                                <span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase tabular-nums ring-1 ring-inset {{ $stockBadges[$product->stock_status] }}" title="Mínimo {{ $product->min_stock }}">Stock {{ $product->stock }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">
                                {{ $product->tracks_expiry && $product->nextLot ? $product->nextLot->expiration_date->format('d/m/Y') : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                @switch ($status)
                                    @case ('expired')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-white"><span class="size-1.5 rounded-full bg-white"></span>Vencido</span>
                                        @break
                                    @case ('critical')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-rose-600 ring-1 ring-inset ring-rose-200"><span class="size-1.5 rounded-full bg-rose-500"></span>Crítico: {{ $days }} {{ $days === 1 ? 'día' : 'días' }}</span>
                                        @break
                                    @case ('warning')
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-200"><span class="size-1.5 rounded-full bg-amber-500"></span>Atención: {{ $days }} días</span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-200"><span class="size-1.5 rounded-full bg-emerald-500"></span>Al día</span>
                                @endswitch
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        wire:click="$dispatch('product-restock', { productId: {{ $product->id }} })"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold uppercase tracking-wide text-slate-700 hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800"
                                    >
                                        <span class="flex size-4 items-center justify-center rounded-full bg-emerald-500 text-[10px] text-white">+</span>
                                        Reponer
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="$dispatch('product-edit', { productId: {{ $product->id }} })"
                                        title="Editar"
                                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-16 text-center text-slate-500">
                                {{ trim($search) !== '' || $categoryId ? 'No hay artículos que coincidan con el filtro.' : 'Todavía no hay artículos cargados.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                @if ($products->total() > 0)
                    Viendo {{ $products->firstItem() }}–{{ $products->lastItem() }} de {{ $products->total() }} productos registrados
                @else
                    Sin productos
                @endif
            </span>

            @if ($last > 1)
                <nav class="flex items-center gap-1.5" aria-label="Paginación">
                    <button type="button" wire:click="previousPage" @disabled($products->onFirstPage()) class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:opacity-40">‹</button>
                    @foreach ($pages as $i => $page)
                        @if ($i > 0 && $page - $pages[$i - 1] > 1)
                            <span class="px-1 text-slate-400">…</span>
                        @endif
                        <button
                            type="button"
                            wire:key="page-{{ $page }}"
                            wire:click="gotoPage({{ $page }})"
                            @class([
                                'flex size-9 items-center justify-center rounded-lg text-sm font-semibold',
                                'bg-[#0F2A26] text-white shadow-sm' => $page === $current,
                                'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $page !== $current,
                            ])
                            @if ($page === $current) aria-current="page" @endif
                        >{{ $page }}</button>
                    @endforeach
                    <button type="button" wire:click="nextPage" @disabled(! $products->hasMorePages()) class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:opacity-40">›</button>
                </nav>
            @endif
        </div>
    </div>

    <livewire:product-editor />
    <livewire:restock-modal />
</div>
