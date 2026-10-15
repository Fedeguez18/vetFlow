@use('App\Livewire\Pos\PosDesk')
@use('Illuminate\Support\Str')

@php
    $money = fn ($amount) => PosDesk::money($amount);

    $icons = [
        'all' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
        'vaccine' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
        'pharmacy' => 'm15 11.25 1.5 1.5.75-.75V8.758l2.276-.61a3 3 0 1 0-3.675-3.675l-.61 2.277H12l-.75.75 1.5 1.5M15 11.25l-8.47 8.47c-.34.34-.8.53-1.28.53s-.94.19-1.28.53l-.97.97-.75-.75.97-.97c.34-.34.53-.8.53-1.28s.19-.94.53-1.28L12.75 9M15 11.25 12.75 9',
        'nutrition' => 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5',
        'identification' => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3ZM6 6h.008v.008H6V6Z',
        'instrument' => 'm7.848 8.25 1.536.887M7.848 8.25a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024M9.384 9.137l2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 0 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.33 4.33 0 0 0 10.607 12m3.736 0 7.794 4.5-.802.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664',
        'accessory' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
        'other' => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
    ];

    // Icon key stored on each category (categories.icon).
    $categoryIcon = fn (?string $icon): string => $icons[$icon] ?? $icons['other'];

    $payButtons = [
        'cuenta_corriente' => ['Cta. Corriente', 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
        'efectivo' => ['Efectivo', 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z'],
        'qr' => ['QR / Transf.', 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5ZM6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z'],
        'tarjeta' => ['Tarjetas', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z'],
    ];

    $onAccount = $paymentMethod === 'cuenta_corriente';
    $producer = $this->selectedProducer;
    $total = $this->total;
    $receivedInvalid = $paymentMethod === 'efectivo' && trim($received) !== ''
        && ($this->receivedAmount === null || $this->receivedAmount < $total);
@endphp

<div
    x-data="{
        q: '',
        scan() {
            const code = this.q.trim();
            this.q = '';
            if (code) this.$wire.addByCode(code);
        },
        refocus() {
            setTimeout(() => {
                if (! ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                    this.$refs.scan.focus();
                }
            }, 120);
        },
        fit() {
            this.$el.style.setProperty('--pos-top', (document.querySelector('body > header')?.offsetHeight ?? 0) + 'px');
        },
        printTicket() {
            const frame = document.createElement('iframe');
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
            document.body.appendChild(frame);
            const doc = frame.contentWindow.document;
            doc.open();
            doc.write(`<!DOCTYPE html><html><head><meta charset='utf-8'><title>Ticket</title><style>
                body{font-family:ui-monospace,monospace;font-size:12px;width:72mm;margin:0 auto;padding:4mm 0;color:#000}
                h1{font-size:16px;margin:0;text-align:center}.c{text-align:center}.r{text-align:right}
                .row{display:flex;justify-content:space-between;gap:8px}.muted{color:#444;font-size:11px}
                hr{border:0;border-top:1px dashed #000;margin:6px 0}.total{font-size:15px;font-weight:bold}
            </style></head><body>${this.$refs.ticket.innerHTML}</body></html>`);
            doc.close();
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => { frame.remove(); this.$refs.scan.focus(); }, 1000);
        },
    }"
    x-init="fit(); $nextTick(() => $refs.scan.focus())"
    x-on:resize.window="fit()"
    x-on:focusout="refocus()"
    x-on:focus-scan.window="$nextTick(() => $refs.scan.focus())"
    x-on:scan-restore.window="if (! q) { q = $event.detail.code; $nextTick(() => $refs.scan.select()) }"
    x-on:print-ticket.window="$nextTick(() => requestAnimationFrame(() => printTicket()))"
    x-on:keydown.f2.window.prevent="$refs.scan.focus(); $refs.scan.select()"
    x-on:keydown.f9.window.prevent="$refs.confirm.disabled || $refs.confirm.click()"
    class="-mx-4 -my-6 flex flex-col bg-[#F4F6F5] sm:-mx-6 lg:h-[calc(100dvh_-_var(--pos-top,6.5rem))] lg:flex-row"
>
    {{-- Catalog --}}
    <section class="min-w-0 flex-1 space-y-4 p-4 sm:p-6 lg:overflow-y-auto">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400">
                    <path stroke-linecap="round" d="M3 5v14M21 5v14" stroke-width="1.5" />
                    <path stroke-linecap="round" d="M6.5 5v14M10 5v14M14.5 5v14M17.5 5v14" stroke-width="1" />
                    <path stroke-linecap="round" d="M8.25 5v14M12.25 5v14" stroke-width="2" />
                </svg>
                <input
                    id="pos-scan"
                    type="text"
                    x-ref="scan"
                    x-model="q"
                    x-on:keydown.enter.prevent="scan()"
                    x-on:keydown.escape="q = ''; $wire.$set('search', '')"
                    x-on:input.debounce.250ms="$wire.$set('search', q)"
                    autofocus
                    autocomplete="off"
                    placeholder="Escaneá o escribí nombre / código…"
                    class="w-full rounded-[10px] border border-slate-200 bg-white py-3 pl-11 pr-28 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"
                >
                <span class="pointer-events-none absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-slate-400">
                    <span wire:loading wire:target="addByCode,addProduct" class="text-emerald-600">●</span>
                    F2 SCAN
                </span>
            </div>
            <button
                type="button"
                x-on:click="$wire.$set('search', q)"
                title="Buscar"
                class="flex size-12 shrink-0 items-center justify-center rounded-[10px] bg-[#0F2A26] text-white shadow-sm hover:bg-[#1A3D37]"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </button>
        </div>

        @if ($alert)
            <div
                wire:key="alert-{{ $alertId }}"
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, {{ $alertType === 'error' ? 6000 : 3500 }})"
                x-show="show"
                x-transition.opacity.duration.300ms
                @class([
                    'flex items-center gap-2.5 rounded-[10px] border px-4 py-2.5 text-sm font-medium',
                    'border-emerald-200 bg-emerald-50 text-emerald-800' => $alertType === 'success',
                    'border-rose-200 bg-rose-50 text-rose-700' => $alertType === 'error',
                ])
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5 shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $alertType === 'success' ? 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' : 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z' }}" />
                </svg>
                <span class="flex-1">{{ $alert }}</span>
                <button type="button" x-on:click="show = false" class="opacity-60 hover:opacity-100">&times;</button>
            </div>
        @endif

        {{-- Category chips --}}
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ([null => ['Todos', 'all']] + $this->categories->mapWithKeys(fn ($category) => [$category->id => [$category->name, $category->icon]])->all() as $id => [$label, $icon])
                <button
                    type="button"
                    wire:key="category-{{ $id ?: 'all' }}"
                    wire:click="$set('categoryId', {{ $id ?: 'null' }})"
                    @class([
                        'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                        'border-[#0F2A26] bg-[#0F2A26] text-white' => $categoryId === ($id ?: null),
                        'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' => $categoryId !== ($id ?: null),
                    ])
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $categoryIcon($icon) }}" />
                    </svg>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Product grid --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->catalog as $product)
                @php
                    $outOfStock = $product->stock <= 0;
                    $lowStock = ! $outOfStock && $product->stock_status !== 'ok';
                    $inCart = $cart[$product->id]['quantity'] ?? 0;
                @endphp
                <button
                    type="button"
                    wire:key="product-{{ $product->id }}"
                    wire:click="addProduct({{ $product->id }})"
                    @disabled($outOfStock)
                    @class([
                        'relative flex flex-col rounded-[10px] border bg-white p-4 text-left shadow-sm transition',
                        'border-slate-200 hover:-translate-y-0.5 hover:border-[#0F2A26]/30 hover:shadow-md' => ! $outOfStock && ! $inCart,
                        'border-emerald-400 ring-2 ring-emerald-400/30 hover:-translate-y-0.5 hover:shadow-md' => ! $outOfStock && $inCart,
                        'cursor-not-allowed border-rose-200 opacity-60' => $outOfStock,
                    ])
                >
                    @if ($inCart)
                        <span class="absolute -right-2 -top-2 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-xs font-bold text-white shadow">{{ $inCart }}</span>
                    @endif
                    <div class="flex items-start justify-between gap-2">
                        <span class="truncate rounded bg-[#0F2A26] px-1.5 py-0.5 text-[10px] font-semibold text-white">{{ $product->category?->name ?? 'General' }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 shrink-0 text-slate-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $categoryIcon($product->category?->icon) }}" />
                        </svg>
                    </div>
                    <span class="mt-2.5 font-bold leading-snug text-slate-900">{{ $product->name }}</span>
                    <span class="mt-0.5 text-xs text-slate-400">
                        {{ $product->description ?: ($product->barcode ? 'Cód. '.$product->barcode : ($product->sku ?? 'Sin código')) }}
                        @if (in_array($product->expiry_status, ['critical', 'expired']))
                            · <span class="font-semibold text-rose-600">{{ $product->expiry_status === 'expired' ? 'Vencido' : 'Vence pronto' }}</span>
                        @endif
                    </span>
                    <div class="mt-auto flex items-end justify-between gap-2 pt-4">
                        <span class="text-xl font-bold tabular-nums text-slate-900">{{ $money($product->price) }}</span>
                        <span @class([
                            'shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold',
                            'bg-emerald-100 text-emerald-700' => ! $outOfStock && ! $lowStock,
                            'bg-amber-100 text-amber-800' => $lowStock,
                            'bg-rose-100 text-rose-700' => $outOfStock,
                        ])>
                            {{ $outOfStock ? 'Sin stock' : 'Stock '.$product->stock }}
                        </span>
                    </div>
                </button>
            @empty
                <div class="col-span-full rounded-[10px] border-2 border-dashed border-slate-200 px-4 py-16 text-center text-slate-400">
                    @if (trim($search) !== '' || $categoryId)
                        No hay productos que coincidan con el filtro.
                    @else
                        Todavía no hay productos cargados.
                    @endif
                </div>
            @endforelse
        </div>

        @if ($this->catalogCount > $this->catalog->count())
            <p class="text-center text-xs text-slate-400">
                Mostrando {{ $this->catalog->count() }} de {{ $this->catalogCount }} productos — refiná la búsqueda o elegí un rubro.
            </p>
        @endif
    </section>

    {{-- Ticket panel --}}
    <aside class="flex w-full shrink-0 flex-col border-t border-slate-200 bg-white lg:w-[30%] lg:min-w-[22rem] lg:border-l lg:border-t-0">
        <div class="space-y-4 p-5 pb-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400">Ticket de venta</span>
                    <span class="block truncate text-2xl font-bold tracking-tight text-slate-900">#{{ $this->nextVoucher }}</span>
                    @if ($this->lastSale)
                        <button type="button" x-on:click="printTicket()" class="mt-0.5 text-xs font-medium text-teal-700 hover:text-teal-900">
                            Reimprimir {{ $this->lastSale->voucher_number }}
                        </button>
                    @endif
                </div>
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Abierto
                </span>
            </div>

            {{-- Customer selector --}}
            <div x-data="{ open: false, term: '' }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" class="relative">
                <button
                    type="button"
                    x-on:click="open = ! open; if (open) $nextTick(() => $refs.customerSearch.focus())"
                    @class([
                        'flex w-full items-center gap-3 rounded-[10px] border p-3 text-left transition-colors hover:bg-slate-50',
                        'border-amber-400 bg-amber-50/50' => $onAccount && ! $producer,
                        'border-slate-200' => ! ($onAccount && ! $producer),
                    ])
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0F2A26]/10 text-sm font-bold text-[#0F2A26]">
                        @if ($producer)
                            {{ Str::upper(Str::substr($producer->name, 0, 1)) }}
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-slate-900">
                            {{ $producer ? $producer->name.($producer->business_name ? ' — '.$producer->business_name : '') : 'Consumidor Final' }}
                        </span>
                        <span class="block truncate text-[11px] text-slate-400">
                            @if ($producer)
                                {{ collect([$producer->cuit ? 'CUIT: '.$producer->cuit : null, 'Saldo: '.$money($producer->balance)])->filter()->join(' · ') }}
                            @elseif ($onAccount)
                                <span class="font-medium text-amber-600">Elegí el productor / estancia para el remito</span>
                            @else
                                Sin datos fiscales
                            @endif
                        </span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4 shrink-0 text-slate-400 transition-transform" x-bind:class="open && 'rotate-180'">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <div x-show="open" x-transition.origin.top style="display: none" class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-lg">
                    <div class="border-b border-slate-100 p-2">
                        <input
                            x-ref="customerSearch"
                            x-model="term"
                            type="text"
                            autocomplete="off"
                            placeholder="Buscar productor, estancia o CUIT…"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0F2A26]/10"
                        >
                    </div>
                    <ul class="max-h-64 divide-y divide-slate-50 overflow-y-auto text-sm">
                        <li>
                            <button type="button" x-on:click="open = false; term = ''; $wire.selectProducer(null)" class="w-full px-3 py-2.5 text-left hover:bg-slate-50">
                                <span class="font-medium text-slate-800">Consumidor Final</span>
                            </button>
                        </li>
                        @foreach ($this->producers as $item)
                            <li
                                wire:key="customer-{{ $item->id }}"
                                x-show="! term || @js(Str::lower($item->name.' '.$item->business_name.' '.$item->cuit)).includes(term.toLowerCase())"
                            >
                                <button
                                    type="button"
                                    x-on:click="open = false; term = ''; $wire.selectProducer({{ $item->id }})"
                                    @class(['flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left hover:bg-slate-50', 'bg-emerald-50' => $producerId === $item->id])
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-slate-800">{{ $item->name }}</span>
                                        <span class="block truncate text-[11px] text-slate-400">{{ collect([$item->business_name, $item->cuit])->filter()->join(' · ') ?: 'Sin datos fiscales' }}</span>
                                    </span>
                                    @if ($item->balance > 0)
                                        <span class="shrink-0 text-xs font-semibold tabular-nums text-rose-600">{{ $money($item->balance) }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- Lines --}}
        <div class="flex items-center justify-between border-y border-slate-100 bg-slate-50/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            <span>Productos</span>
            <span>Total</span>
        </div>
        <div class="max-h-96 min-h-32 flex-1 overflow-y-auto px-5 lg:max-h-none">
            @forelse ($cart as $line)
                <div wire:key="cart-{{ $line['product_id'] }}" class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[#0F2A26]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $categoryIcon($line['icon'] ?? null) }}" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-slate-900" title="{{ $line['name'] }}">{{ $line['name'] }}</div>
                        <div class="text-[11px] tabular-nums text-slate-400">{{ $money($line['price']) }} c/u</div>
                    </div>
                    <div class="flex shrink-0 items-center">
                        @if ($line['quantity'] <= 1)
                            <button type="button" wire:click="removeItem({{ $line['product_id'] }})" title="Quitar" class="flex size-7 items-center justify-center rounded-full border border-slate-200 text-slate-400 hover:border-rose-300 hover:text-rose-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        @else
                            <button type="button" wire:click="setQuantity({{ $line['product_id'] }}, {{ $line['quantity'] - 1 }})" class="flex size-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:border-[#0F2A26]/40 hover:text-[#0F2A26]">&minus;</button>
                        @endif
                        <input
                            type="number"
                            min="1"
                            max="{{ $line['stock'] }}"
                            value="{{ $line['quantity'] }}"
                            wire:change="setQuantity({{ $line['product_id'] }}, $event.target.value)"
                            x-on:keydown.enter.prevent="$el.blur()"
                            class="w-9 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums [appearance:textfield] focus:outline-none focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                        >
                        <button type="button" wire:click="setQuantity({{ $line['product_id'] }}, {{ $line['quantity'] + 1 }})" class="flex size-7 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:border-[#0F2A26]/40 hover:text-[#0F2A26]">+</button>
                    </div>
                    <span class="w-20 shrink-0 text-right text-sm font-bold tabular-nums text-slate-900">{{ $money((float) $line['price'] * $line['quantity']) }}</span>
                </div>
            @empty
                <div class="py-12 text-center text-sm text-slate-400">Escaneá o tocá un producto para empezar la venta.</div>
            @endforelse
        </div>

        {{-- Totals, payment and confirm --}}
        <div class="shrink-0 space-y-3 border-t border-slate-200 p-5">
            @if ($onAccount)
                <div class="flex items-center justify-between gap-2 rounded-[10px] border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-[#0F2A26]">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $payButtons['cuenta_corriente'][1] }}" />
                        </svg>
                        Cargar a Cuenta Corriente
                    </span>
                    <span class="rounded bg-emerald-200/70 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-emerald-800">PENDIENTE</span>
                </div>
            @endif

            <div class="space-y-1 text-sm">
                <div class="flex justify-between text-slate-500">
                    <span>Subtotal</span>
                    <span class="tabular-nums">{{ $money($total) }}</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between border-t border-slate-100 pt-2">
                <span class="font-semibold text-slate-700">{{ $onAccount ? 'Total Remito' : 'Total' }}</span>
                <span class="text-3xl font-bold tabular-nums tracking-tight text-slate-900">{{ $money($total) }}</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                @foreach ($payButtons as $key => [$label, $icon])
                    <button
                        type="button"
                        wire:key="pay-{{ $key }}"
                        wire:click="$set('paymentMethod', '{{ $key }}')"
                        @class([
                            'flex flex-col items-center justify-center gap-1 rounded-[10px] border px-2 py-3 text-xs font-semibold transition-colors',
                            'border-[#0F2A26] bg-[#0F2A26] text-white shadow-sm' => $paymentMethod === $key,
                            'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' => $paymentMethod !== $key,
                        ])
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                        </svg>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($paymentMethod === 'efectivo')
                <div>
                    <div class="relative">
                        <span @class(['pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 font-semibold', 'text-rose-500' => $receivedInvalid, 'text-slate-400' => ! $receivedInvalid])>$</span>
                        <input
                            type="text"
                            inputmode="decimal"
                            wire:model.live.debounce.300ms="received"
                            x-on:keydown.enter.prevent
                            autocomplete="off"
                            placeholder="Monto recibido"
                            @class([
                                'w-full rounded-[10px] border py-3 pl-8 pr-28 text-lg font-semibold tabular-nums focus:outline-none focus:ring-4',
                                'border-rose-400 bg-rose-50 text-rose-700 focus:ring-rose-500/15' => $receivedInvalid,
                                'border-slate-200 bg-white text-slate-900 focus:border-[#0F2A26] focus:ring-[#0F2A26]/10' => ! $receivedInvalid,
                            ])
                        >
                        @if ($cart !== [] && $this->change !== null)
                            <span class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-emerald-100 px-2 py-1 text-right text-[11px] font-semibold leading-tight text-emerald-800">
                                Cambio<br><span class="tabular-nums">{{ $money($this->change) }}</span>
                            </span>
                        @endif
                    </div>
                    @if ($receivedInvalid)
                        <p class="mt-1 text-xs font-medium text-rose-600">
                            {{ $this->receivedAmount === null ? 'Monto inválido.' : 'Faltan '.$money($total - $this->receivedAmount).'.' }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="flex gap-2">
                <button
                    type="button"
                    wire:click="clearCart"
                    wire:confirm="¿Vaciar el ticket?"
                    @disabled($cart === [])
                    title="Vaciar ticket"
                    class="flex size-14 shrink-0 items-center justify-center rounded-[10px] border border-slate-200 text-slate-500 hover:border-rose-300 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                </button>
                <button
                    type="button"
                    x-ref="confirm"
                    wire:click="confirm"
                    wire:loading.attr="disabled"
                    wire:target="confirm"
                    @disabled($cart === [] || $receivedInvalid || ($onAccount && ! $producer))
                    class="flex h-14 flex-1 items-center justify-center gap-2 rounded-[10px] bg-[#0F2A26] px-4 font-bold text-white shadow-md transition hover:bg-[#1A3D37] disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
                    </svg>
                    <span wire:loading.remove wire:target="confirm">Confirmar e Imprimir Ticket</span>
                    <span wire:loading wire:target="confirm">Procesando…</span>
                    <kbd class="rounded bg-white/15 px-1.5 py-0.5 font-sans text-[11px] font-semibold">F9</kbd>
                </button>
            </div>
        </div>
    </aside>

    {{-- Printable ticket (copied into a hidden iframe on print) --}}
    <div x-ref="ticket" class="hidden">
        @if ($sale = $this->lastSale)
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
            <div class="row"><span>Cobro</span><span>{{ $methods[$lastPaymentMethod] ?? '' }}</span></div>
            <hr>
            @foreach ($sale->saleItems as $item)
                <div>{{ $item->product?->name }}</div>
                <div class="row muted"><span>{{ $item->quantity }} x {{ $money($item->unit_price) }}</span><span>{{ $money($item->subtotal) }}</span></div>
            @endforeach
            <hr>
            <div class="row total"><span>TOTAL</span><span>{{ $money($sale->total) }}</span></div>
            @if ($sale->type === 'remito')
                <br><br>
                <div class="c">______________________</div>
                <div class="c muted">Firma y aclaración</div>
            @endif
            <hr>
            <div class="c muted">Comprobante no válido como factura</div>
        @endif
    </div>
</div>
