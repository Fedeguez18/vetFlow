@use('App\Support\Pricing')

@php
    $money = fn ($amount) => Pricing::money($amount);

    $payButtons = [
        'cuenta_corriente' => 'Cta. Corriente',
        'efectivo' => 'Efectivo',
        'qr' => 'QR / Transf.',
        'tarjeta' => 'Tarjetas',
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
            </style><\/head><body>${this.$refs.ticket.innerHTML}<\/body><\/html>`);
            {{-- Escaped closing tags on purpose: dev tools like Laravel Boost inject their script before every literal head-closing tag, which would break this attribute. --}}
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
                    class="w-full rounded-[10px] border border-slate-200 bg-white py-3 pl-4 pr-28 text-base text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"
                >
                <span class="pointer-events-none absolute right-3 top-1/2 inline-flex -translate-y-1/2 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-slate-400">
                    <span wire:loading wire:target="addByCode,addProduct" class="text-emerald-600">●</span>
                    F2 SCAN
                </span>
            </div>
            <button
                type="button"
                x-on:click="$wire.$set('search', q)"
                class="flex h-12 shrink-0 items-center justify-center px-4 text-sm font-semibold rounded-[10px] bg-[#0F2A26] text-white shadow-sm hover:bg-[#1A3D37]"
            >
                Buscar
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
                <span class="flex-1">{{ $alert }}</span>
                <button type="button" x-on:click="show = false" class="opacity-60 hover:opacity-100">&times;</button>
            </div>
        @endif

        {{-- Category chips --}}
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach ([null => 'Todos'] + $this->categories->pluck('name', 'id')->all() as $id => $label)
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
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Product grid --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($this->catalog as $product)
                @php
                    // Expired lots cannot be sold, so the card shows only the sellable units.
                    $sellable = $product->sellableStock();
                    $outOfStock = $sellable <= 0;
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
                    </div>
                    <span class="mt-2.5 font-bold leading-snug text-slate-900">{{ $product->name }}</span>
                    <span class="mt-0.5 text-xs text-slate-400">
                        {{ $product->description ?: ($product->barcode ? 'Cód. '.$product->barcode : ($product->sku ?? 'Sin código')) }}
                        @if (in_array($product->expiry_status, ['critical', 'expired']))
                            · <span class="font-semibold text-rose-600">{{ $product->expiry_status === 'expired' ? 'Lote vencido' : 'Vence pronto' }}</span>
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
                            {{ $outOfStock ? ($product->stock > 0 ? 'Solo vencidos' : 'Sin stock') : 'Stock '.$sellable }}
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
                        @if ($this->lastSale->type === 'remito')
                            <a href="{{ route('erp.remitos.pdf', $this->lastSale) }}" target="_blank" rel="noopener" class="ml-2 mt-0.5 text-xs font-medium text-teal-700 hover:text-teal-900">Remito A4 (PDF)</a>
                        @endif
                    @endif
                </div>
                <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    Abierto
                </span>
            </div>

            {{-- Customer selector --}}
            @include('livewire.pos.partials.customer-selector', [
                'producers' => $this->producers,
                'producer' => $producer,
                'producerId' => $producerId,
                'onAccount' => $onAccount,
            ])
        </div>

        {{-- Lines --}}
        <div class="flex items-center justify-between border-y border-slate-100 bg-slate-50/60 px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
            <span>Productos</span>
            <span>Total</span>
        </div>
        <div class="max-h-96 min-h-32 flex-1 overflow-y-auto px-5 lg:max-h-none">
            @forelse ($cart as $line)
                <div wire:key="cart-{{ $line['product_id'] }}" class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-slate-900" title="{{ $line['name'] }}">{{ $line['name'] }}</div>
                        <div class="text-[11px] tabular-nums text-slate-400">{{ $money($line['price']) }} c/u</div>
                    </div>
                    <div class="flex shrink-0 items-center">
                        @if ($line['quantity'] <= 1)
                            <button type="button" wire:click="removeItem({{ $line['product_id'] }})" title="Quitar" class="flex size-7 items-center justify-center rounded-full border border-slate-200 text-slate-400 hover:border-rose-300 hover:text-rose-600">
                                &minus;
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
                    <span>
                        Cargar a Cuenta Corriente
                    </span>
                    <span class="rounded bg-emerald-200/70 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-emerald-800">PENDIENTE</span>
                </div>

                @if ($producer?->hasCreditBalance())
                    <div class="rounded-[10px] border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-medium text-teal-800">
                        Tiene {{ $money(-$producer->balance) }} a favor: se descuenta de este remito.
                    </div>
                @endif

                @if ($creditLimitWarning)
                    <div class="space-y-2 rounded-[10px] border border-amber-300 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                        <p class="font-semibold">{{ $creditLimitWarning }}</p>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('creditLimitWarning', null)" class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold hover:bg-amber-100">Cancelar</button>
                            <button
                                type="button"
                                wire:click="confirm(true)"
                                wire:loading.attr="disabled"
                                wire:target="confirm"
                                class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-500 disabled:opacity-50"
                            >
                                Emitir el remito igual
                            </button>
                        </div>
                    </div>
                @elseif ($this->exceedsCreditLimit)
                    <div class="rounded-[10px] border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900">
                        Este remito supera el límite de crédito ({{ $money($producer->credit_limit) }}, disponible {{ $money(max(0, $producer->availableCredit())) }}). Se va a pedir confirmación.
                    </div>
                @endif
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
                @foreach ($payButtons as $key => $label)
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
                    class="flex h-14 shrink-0 items-center justify-center px-4 text-sm font-semibold rounded-[10px] border border-slate-200 text-slate-500 hover:border-rose-300 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Vaciar
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
        @include('livewire.pos.partials.ticket', ['sale' => $this->lastSale, 'paymentLabel' => $methods[$lastPaymentMethod] ?? ''])
    </div>
</div>
