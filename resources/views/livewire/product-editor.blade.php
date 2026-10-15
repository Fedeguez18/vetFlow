@use('App\Support\Pricing')
@use('App\Models\StockMovement')

@php
    $money = fn ($amount) => Pricing::money($amount);
    $editing = $form->isEditing();
    $product = $form->product;
    $input = 'w-full rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10 disabled:bg-slate-100 disabled:text-slate-500';
    $label = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500';
    $statusBadges = [
        'expired' => ['Vencido', 'bg-rose-600 text-white'],
        'critical' => ['Crítico', 'bg-rose-100 text-rose-700'],
        'warning' => ['Atención', 'bg-amber-100 text-amber-800'],
        'ok' => ['Al día', 'bg-emerald-100 text-emerald-700'],
    ];
    $movementBadges = [
        'ingreso' => 'bg-emerald-100 text-emerald-700',
        'egreso' => 'bg-rose-100 text-rose-700',
        'ajuste' => 'bg-amber-100 text-amber-800',
        'venta' => 'bg-sky-100 text-sky-700',
    ];
@endphp

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:py-10" x-on:keydown.escape.window="$wire.close()" x-on:click.self="$wire.close()">
            <div class="flex w-full max-w-4xl flex-col rounded-2xl bg-white shadow-xl">
                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $editing ? 'Editar artículo' : 'Nuevo artículo' }}</span>
                        <h2 class="text-lg font-bold text-slate-900">{{ $editing ? $product->name : 'Alta de artículo' }}</h2>
                        @if ($editing)
                            <p class="text-xs text-slate-500">Stock actual: <span class="font-semibold text-slate-800">{{ $product->stock }}</span> · se modifica sólo con Reponer o Ajuste de stock</p>
                        @endif
                    </div>
                    <button type="button" wire:click="close" class="rounded-lg p-1 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>

                @if ($editing)
                    <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 px-6">
                        @foreach (\App\Livewire\ProductEditor::TABS as $key => $tabLabel)
                            <button
                                type="button"
                                wire:key="tab-{{ $key }}"
                                wire:click="$set('tab', '{{ $key }}')"
                                @class([
                                    'shrink-0 border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors',
                                    'border-[#0F2A26] text-[#0F2A26]' => $tab === $key,
                                    'border-transparent text-slate-500 hover:text-slate-800' => $tab !== $key,
                                ])
                            >{{ $tabLabel }}</button>
                        @endforeach
                    </nav>
                @endif

                <div class="px-6 py-5">
                    {{-- DATOS --}}
                    @if ($tab === 'datos')
                        <form wire:submit="save" id="product-form" class="space-y-6">
                            <section>
                                <h3 class="mb-3 text-sm font-bold text-slate-900">Datos generales</h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="pf-barcode" class="{{ $label }}">Código de barras</label>
                                        <input id="pf-barcode" type="text" wire:model="form.barcode" x-init="$nextTick(() => $el.focus())" x-on:keydown.enter.prevent="document.getElementById('pf-sku').focus()" autocomplete="off" placeholder="Escaneá o tipeá el código" class="{{ $input }} font-mono">
                                        @error('form.barcode') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="pf-sku" class="{{ $label }}">SKU</label>
                                        <input id="pf-sku" type="text" wire:model="form.sku" autocomplete="off" placeholder="Ej. IVM-315-500" class="{{ $input }} font-mono uppercase">
                                        @error('form.sku') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="pf-name" class="{{ $label }}">Nombre</label>
                                        <input id="pf-name" type="text" wire:model="form.name" class="{{ $input }}">
                                        @error('form.name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="pf-description" class="{{ $label }}">Descripción</label>
                                        <textarea id="pf-description" wire:model="form.description" rows="2" class="{{ $input }}"></textarea>
                                        @error('form.description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label for="pf-category" class="{{ $label }}">Rubro</label>
                                        <select id="pf-category" wire:model="form.category_id" class="{{ $input }}">
                                            <option value="">Sin rubro</option>
                                            @foreach ($this->categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="pf-supplier" class="{{ $label }}">Proveedor</label>
                                        <select id="pf-supplier" wire:model="form.supplier_id" class="{{ $input }}">
                                            <option value="">Sin proveedor</option>
                                            @foreach ($this->suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->business_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 class="mb-3 text-sm font-bold text-slate-900">Precios</h3>
                                <div class="grid gap-4 sm:grid-cols-4">
                                    @foreach ([
                                        'cost_price' => ['Precio inicial (costo s/IVA)', '$', null],
                                        'markup_percent' => ['% Remarque', null, '%'],
                                        'vat_percent' => ['% IVA', null, '%'],
                                        'price' => ['Precio final (c/IVA)', '$', null],
                                    ] as $field => [$fieldLabel, $prefix, $suffix])
                                        <div wire:key="price-{{ $field }}">
                                            <label for="pf-{{ $field }}" class="{{ $label }}">{{ $fieldLabel }}</label>
                                            <div class="relative">
                                                @if ($prefix)
                                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">{{ $prefix }}</span>
                                                @endif
                                                <input
                                                    id="pf-{{ $field }}"
                                                    type="text"
                                                    inputmode="decimal"
                                                    wire:model.live.debounce.400ms="form.{{ $field }}"
                                                    autocomplete="off"
                                                    @class([$input, 'text-right tabular-nums', 'pl-7' => $prefix, 'pr-7' => $suffix, 'font-bold' => $field === 'price'])
                                                >
                                                @if ($suffix)
                                                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">{{ $suffix }}</span>
                                                @endif
                                            </div>
                                            @error('form.'.$field) <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                    <label class="flex items-center gap-2 text-slate-600">
                                        <input type="checkbox" wire:model.live="form.round" class="rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20">
                                        Redondear precio final
                                    </label>
                                    @if ($form->round)
                                        <select wire:model.live="form.round_to" class="rounded-lg border border-slate-300 py-1 pl-2 pr-8 text-sm">
                                            <option value="10">a la decena</option>
                                            <option value="100">a la centena</option>
                                        </select>
                                    @endif
                                    <span class="text-xs text-slate-400">Si editás el precio final se recalcula el % de remarque.</span>
                                </div>

                                @if ($breakdown = $this->breakdown)
                                    <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-4">
                                        @foreach (['Costo' => $breakdown['cost'], 'Ganancia' => $breakdown['profit'], 'IVA' => $breakdown['vat'], 'Final' => $breakdown['final']] as $partLabel => $amount)
                                            <div>
                                                <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $partLabel }}</span>
                                                <span @class(['font-semibold tabular-nums', 'text-rose-600' => $amount < 0, 'text-slate-900 text-base font-bold' => $partLabel === 'Final', 'text-slate-700' => $partLabel !== 'Final' && $amount >= 0])>{{ $money($amount) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($this->belowCost)
                                    <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                                        Atención: el precio final queda por debajo del costo. Podés guardarlo igual.
                                    </p>
                                @endif
                            </section>

                            <section>
                                <h3 class="mb-3 text-sm font-bold text-slate-900">Existencias y vencimientos</h3>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-3">
                                        <input type="checkbox" wire:model.live="form.tracks_expiry" @disabled($editing && $product->stock > 0) class="rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20 disabled:opacity-50">
                                        Maneja vencimiento / lotes
                                        @if ($editing && $product->stock > 0)
                                            <span class="text-xs text-slate-400">(no se puede cambiar con stock cargado)</span>
                                        @endif
                                    </label>

                                    @if (! $editing)
                                        @if ($form->tracks_expiry)
                                            <div class="sm:col-span-3">
                                                <div class="overflow-hidden rounded-xl border border-slate-200">
                                                    <table class="w-full text-sm">
                                                        <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                                            <tr>
                                                                <th class="px-3 py-2">N° de lote</th>
                                                                <th class="px-3 py-2">Cantidad</th>
                                                                <th class="px-3 py-2">Vencimiento</th>
                                                                <th class="w-10"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-slate-100">
                                                            @foreach ($form->lots as $i => $lot)
                                                                <tr wire:key="new-lot-{{ $i }}" class="align-top">
                                                                    <td class="px-3 py-2">
                                                                        <input type="text" wire:model="form.lots.{{ $i }}.lot_number" class="{{ $input }} uppercase">
                                                                        @error('form.lots.'.$i.'.lot_number') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        <input type="number" min="1" wire:model.live.debounce.400ms="form.lots.{{ $i }}.quantity" class="{{ $input }} text-right">
                                                                        @error('form.lots.'.$i.'.quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                                    </td>
                                                                    <td class="px-3 py-2">
                                                                        <input type="date" wire:model="form.lots.{{ $i }}.expiration_date" class="{{ $input }}">
                                                                        @error('form.lots.'.$i.'.expiration_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                                    </td>
                                                                    <td class="px-2 py-2 text-center">
                                                                        <button type="button" wire:click="removeLot({{ $i }})" class="mt-1.5 text-lg text-slate-400 hover:text-rose-600" title="Quitar lote">&times;</button>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="mt-2 flex items-center justify-between text-sm">
                                                    <button type="button" wire:click="addLot" class="font-semibold text-teal-700 hover:text-teal-900">+ Agregar lote</button>
                                                    <span class="text-slate-500">Stock total: <span class="font-bold text-slate-900">{{ $form->lotsTotal() }}</span> unidades</span>
                                                </div>
                                            </div>
                                        @else
                                            <div>
                                                <label for="pf-initial" class="{{ $label }}">Existencias iniciales</label>
                                                <input id="pf-initial" type="number" min="0" wire:model="form.initial_stock" class="{{ $input }} text-right">
                                                @error('form.initial_stock') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </div>
                                        @endif
                                    @endif

                                    <div>
                                        <label for="pf-min" class="{{ $label }}">Stock mínimo</label>
                                        <input id="pf-min" type="number" min="0" wire:model="form.min_stock" class="{{ $input }} text-right">
                                        @error('form.min_stock') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    @if ($editing)
                                        <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-700">
                                            <input type="checkbox" wire:model="form.is_active" class="rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20">
                                            Artículo activo
                                        </label>
                                    @endif
                                </div>
                            </section>
                        </form>
                    @endif

                    {{-- LOTES --}}
                    @if ($tab === 'lotes')
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-3 py-2">Lote</th><th class="px-3 py-2 text-right">Cantidad</th><th class="px-3 py-2">Vencimiento</th><th class="px-3 py-2">Estado</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($this->lots as $lot)
                                        @php([$statusLabel, $statusClass] = $statusBadges[$lot->expiry_status])
                                        <tr wire:key="lot-{{ $lot->id }}" @class(['opacity-50' => $lot->quantity === 0])>
                                            <td class="px-3 py-2 font-mono">{{ $lot->lot_number }}</td>
                                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $lot->quantity }}</td>
                                            <td class="px-3 py-2">{{ $lot->expiration_date->format('d/m/Y') }}</td>
                                            <td class="px-3 py-2">
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusClass }}">
                                                    {{ $statusLabel }}{{ $lot->days_left >= 0 && $lot->expiry_status !== 'ok' ? ': '.$lot->days_left.' días' : '' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-400">{{ $product->tracks_expiry ? 'Sin lotes cargados.' : 'Este artículo no maneja vencimiento.' }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- MOVIMIENTOS --}}
                    @if ($tab === 'movimientos')
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-3 py-2">Fecha</th><th class="px-3 py-2">Tipo</th><th class="px-3 py-2 text-right">Cantidad</th><th class="px-3 py-2">Lote</th><th class="px-3 py-2">Motivo</th><th class="px-3 py-2">Usuario</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($this->movements as $movement)
                                        <tr wire:key="movement-{{ $movement->id }}">
                                            <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $movementBadges[$movement->type] }}">{{ StockMovement::TYPES[$movement->type] }}</span></td>
                                            <td @class(['px-3 py-2 text-right font-semibold tabular-nums', 'text-emerald-700' => $movement->quantity > 0, 'text-rose-600' => $movement->quantity < 0])>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                                            <td class="px-3 py-2 font-mono text-xs">{{ $movement->lot?->lot_number ?? '—' }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ $movement->reason ?? '—' }}{{ $movement->supplier ? ' · '.$movement->supplier->business_name : '' }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ $movement->user?->name ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Sin movimientos.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- HISTORIAL DE PRECIOS --}}
                    @if ($tab === 'precios')
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-3 py-2">Fecha</th><th class="px-3 py-2 text-right">Costo</th><th class="px-3 py-2 text-right">% Remarque</th><th class="px-3 py-2 text-right">% IVA</th><th class="px-3 py-2 text-right">Precio final</th><th class="px-3 py-2">Usuario</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($this->priceHistory as $history)
                                        <tr wire:key="price-{{ $history->id }}">
                                            <td class="whitespace-nowrap px-3 py-2 text-slate-600">{{ $history->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $money($history->cost_price) }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ Pricing::percent($history->markup_percent) }}%</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ Pricing::percent($history->vat_percent) }}%</td>
                                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $money($history->price) }}</td>
                                            <td class="px-3 py-2 text-slate-600">{{ $history->user?->name ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Sin cambios de precio registrados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- AJUSTE DE STOCK --}}
                    @if ($tab === 'ajuste')
                        <form wire:submit="adjust" class="grid gap-4 sm:grid-cols-2">
                            @if ($product->tracks_expiry)
                                <div class="sm:col-span-2">
                                    <label for="adj-lot" class="{{ $label }}">Lote</label>
                                    <select id="adj-lot" wire:model="adjustLotId" class="{{ $input }}">
                                        <option value="">Elegí el lote…</option>
                                        @foreach ($this->lots as $lot)
                                            <option value="{{ $lot->id }}">{{ $lot->lot_number }} — {{ $lot->quantity }} u. — vence {{ $lot->expiration_date->format('d/m/Y') }}</option>
                                        @endforeach
                                    </select>
                                    @error('adjustLotId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            <div class="sm:col-span-2">
                                <span class="{{ $label }}">Tipo de ajuste</span>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach (['subtract' => 'Restar unidades', 'set' => 'Corregir a cantidad contada'] as $mode => $modeLabel)
                                        <button type="button" wire:key="mode-{{ $mode }}" wire:click="$set('adjustMode', '{{ $mode }}')" @class([
                                            'rounded-[10px] border px-3 py-2 text-sm font-semibold',
                                            'border-[#0F2A26] bg-[#0F2A26] text-white' => $adjustMode === $mode,
                                            'border-slate-200 text-slate-600 hover:border-slate-300' => $adjustMode !== $mode,
                                        ])>{{ $modeLabel }}</button>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label for="adj-qty" class="{{ $label }}">{{ $adjustMode === 'subtract' ? 'Cantidad a restar' : 'Cantidad contada' }}</label>
                                <input id="adj-qty" type="number" min="0" wire:model="adjustQuantity" class="{{ $input }} text-right">
                                @error('adjustQuantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="adj-reason" class="{{ $label }}">Motivo</label>
                                <select id="adj-reason" wire:model.live="adjustReason" class="{{ $input }}">
                                    <option value="">Elegí el motivo…</option>
                                    @foreach ($reasons as $key => $reasonLabel)
                                        <option value="{{ $key }}">{{ $reasonLabel }}</option>
                                    @endforeach
                                </select>
                                @error('adjustReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="adj-note" class="{{ $label }}">Detalle {{ $adjustReason === 'otro' ? '(obligatorio)' : '(opcional)' }}</label>
                                <input id="adj-note" type="text" wire:model="adjustNote" class="{{ $input }}">
                                @error('adjustNote') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex justify-end sm:col-span-2">
                                <button type="submit" wire:loading.attr="disabled" wire:target="adjust" class="rounded-[10px] bg-[#0F2A26] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#1A3D37] disabled:opacity-50">
                                    Registrar ajuste
                                </button>
                            </div>
                        </form>
                    @endif
                </div>

                @if ($tab === 'datos')
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-6 py-4">
                        <button type="button" wire:click="close" class="rounded-[10px] px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                        <button type="submit" form="product-form" wire:loading.attr="disabled" wire:target="save" class="rounded-[10px] bg-[#0F2A26] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                            <span wire:loading.remove wire:target="save">{{ $editing ? 'Guardar cambios' : 'Crear artículo' }}</span>
                            <span wire:loading wire:target="save">Guardando…</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
