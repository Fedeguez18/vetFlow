@use('App\Support\Pricing')

@php
    $input = 'w-full rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10';
    $label = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500';
@endphp

<div>
    @if ($open && ($product = $this->product))
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:py-16" x-on:keydown.escape.window="$wire.close()" x-on:click.self="$wire.close()">
            <form wire:submit="save" class="w-full max-w-lg space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Reponer stock</span>
                        <h2 class="text-lg font-bold text-slate-900">{{ $product->name }}</h2>
                        <p class="text-xs text-slate-500">
                            Stock actual {{ $product->stock }} · mínimo {{ $product->min_stock }}
                            {{ $product->tracks_expiry ? '· maneja vencimiento por lote' : '' }}
                        </p>
                    </div>
                    <button type="button" wire:click="close" class="rounded-lg p-1 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>

                <div>
                    <label for="rs-qty" class="{{ $label }}">Cantidad a ingresar</label>
                    <input id="rs-qty" type="number" min="1" step="1" wire:model="quantity" x-init="$nextTick(() => $el.focus())" class="{{ $input }} text-right text-xl font-bold">
                    @error('quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="rs-lot" class="{{ $label }}">N° de lote {{ $product->tracks_expiry ? '' : '(opcional)' }}</label>
                        <input id="rs-lot" type="text" list="rs-lots" wire:model.live.debounce.400ms="lotNumber" autocomplete="off" class="{{ $input }} font-mono uppercase">
                        <datalist id="rs-lots">
                            @foreach ($this->existingLots as $lot)
                                <option value="{{ $lot->lot_number }}">vence {{ $lot->expiration_date->format('d/m/Y') }} · {{ $lot->quantity }} u.</option>
                            @endforeach
                        </datalist>
                        @error('lotNumber') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        @if ($this->existingLots->contains(fn ($lot) => strcasecmp($lot->lot_number, trim($lotNumber)) === 0))
                            <p class="mt-1 text-xs text-teal-700">Se suma al lote existente.</p>
                        @endif
                    </div>
                    <div>
                        <label for="rs-exp" class="{{ $label }}">Vencimiento {{ $product->tracks_expiry ? '' : '(opcional)' }}</label>
                        <input id="rs-exp" type="date" wire:model="expirationDate" class="{{ $input }}">
                        @error('expirationDate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="rs-cost" class="{{ $label }}">Nuevo precio de compra (opcional)</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">$</span>
                            <input id="rs-cost" type="text" inputmode="decimal" wire:model.live.debounce.400ms="newCost" placeholder="{{ Pricing::money($product->cost_price) }}" class="{{ $input }} pl-7 text-right tabular-nums">
                        </div>
                        @error('newCost') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="rs-supplier" class="{{ $label }}">Proveedor</label>
                        <select id="rs-supplier" wire:model="supplierId" class="{{ $input }}">
                            <option value="">Sin proveedor</option>
                            @foreach ($this->suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->business_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($preview = $this->pricePreview)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm">
                        <span class="text-slate-500">Precio de venta</span>
                        <span class="tabular-nums">
                            <span class="text-slate-400 line-through">{{ Pricing::money($preview['old']) }}</span>
                            <span class="mx-1 text-slate-400">→</span>
                            <span class="font-bold text-slate-900">{{ Pricing::money($preview['new']) }}</span>
                        </span>
                    </div>
                    <p class="-mt-2 text-xs text-slate-400">Con el remarque ({{ Pricing::percent($product->markup_percent) }}%) y el IVA ({{ Pricing::percent($product->vat_percent) }}%) del artículo.</p>
                @endif

                <div>
                    <label for="rs-reason" class="{{ $label }}">Motivo u observación (opcional)</label>
                    <input id="rs-reason" type="text" wire:model="reason" placeholder="Ej. factura A-0003-00012345" class="{{ $input }}">
                </div>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="close" class="rounded-[10px] px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-[10px] bg-[#0F2A26] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                        <span wire:loading.remove wire:target="save">Confirmar ingreso</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
