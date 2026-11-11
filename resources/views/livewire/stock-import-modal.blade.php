@php
    $input = 'w-full rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10';
    $cell = 'w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm text-slate-900 placeholder:text-slate-300 focus:border-[#0F2A26] focus:outline-none focus:ring-2 focus:ring-[#0F2A26]/10';
    $label = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500';
@endphp

<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 sm:py-10" x-on:keydown.escape.window="$wire.close()" x-on:click.self="$wire.close()">
            <div @class(['w-full space-y-5 rounded-2xl bg-white p-6 shadow-xl', 'max-w-lg' => $rows === [], 'max-w-7xl' => $rows !== []])>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Importar stock</span>
                        <h2 class="text-lg font-bold text-slate-900">{{ $rows === [] ? 'Lista de precios en PDF' : $fileName }}</h2>
                        <p class="text-xs text-slate-500">
                            @if ($rows === [])
                                Se leen el código, la descripción, el precio y, si la lista los tiene, la cantidad y el IVA. Los títulos de sección se toman como rubros.
                            @else
                                Revisá y corregí las filas antes de confirmar. Los rubros y artículos que no existen se crean; los que existen suman las unidades como ingreso.
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="close" class="rounded-lg p-1 text-2xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>

                @if ($rows === [])
                    <form wire:submit="readPdf" class="space-y-4">
                        <div>
                            <label for="si-pdf" class="{{ $label }}">Archivo PDF</label>
                            <input id="si-pdf" type="file" accept="application/pdf,.pdf" wire:model="pdf" class="{{ $input }} file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-700">
                            <p wire:loading wire:target="pdf" class="mt-1 text-xs text-slate-500">Subiendo…</p>
                            @error('pdf') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" wire:click="close" class="rounded-[10px] px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="pdf, readPdf" class="rounded-[10px] bg-[#0F2A26] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                                <span wire:loading.remove wire:target="readPdf">Leer PDF</span>
                                <span wire:loading wire:target="readPdf">Leyendo…</span>
                            </button>
                        </div>
                    </form>
                @else
                    @php
                        $summary = $this->summary;
                        $matches = $this->matches;
                        $existingCategories = $this->categoryNames->map(fn ($name) => mb_strtolower($name));
                    @endphp

                    <form wire:submit="import" class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <div>
                                <label for="si-supplier" class="{{ $label }}">Proveedor</label>
                                <select id="si-supplier" wire:model="supplierId" class="{{ $input }}">
                                    <option value="">Sin proveedor</option>
                                    @foreach ($this->suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->business_name }}</option>
                                    @endforeach
                                </select>
                                @error('supplierId') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="si-markup" class="{{ $label }}">% Remarque (nuevos)</label>
                                <input id="si-markup" type="text" inputmode="decimal" wire:model="markupPercent" placeholder="Ej. 40" class="{{ $input }} text-right tabular-nums">
                                @error('markupPercent') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="si-vat" class="{{ $label }}">% IVA (nuevos)</label>
                                <input id="si-vat" type="text" inputmode="decimal" wire:model="vatPercent" class="{{ $input }} text-right tabular-nums">
                                @error('vatPercent') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="si-default-category" class="{{ $label }}">Rubro si la fila no tiene</label>
                                <input id="si-default-category" type="text" list="si-categories" wire:model.live.debounce.400ms="defaultCategory" autocomplete="off" placeholder="Ej. Farmacia general" class="{{ $input }}">
                                @error('defaultCategory') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-2 pt-5">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="pricesIncludeVat" class="rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20">
                                    Los precios incluyen IVA
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="updateCosts" class="rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20">
                                    Actualizar costo de los existentes
                                </label>
                            </div>
                        </div>

                        <datalist id="si-categories">
                            @foreach ($this->categoryNames as $categoryName)
                                <option value="{{ $categoryName }}"></option>
                            @endforeach
                        </datalist>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-md bg-emerald-50 px-2 py-1 font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">{{ $summary['new'] }} nuevos</span>
                            <span class="rounded-md bg-slate-100 px-2 py-1 font-bold text-slate-700 ring-1 ring-inset ring-slate-500/20">{{ $summary['existing'] }} existentes</span>
                            <span class="rounded-md bg-slate-100 px-2 py-1 font-bold text-slate-700 ring-1 ring-inset ring-slate-500/20">{{ $summary['units'] }} unidades</span>
                            @if ($summary['categories'] !== [])
                                <span class="rounded-md bg-teal-50 px-2 py-1 font-bold text-teal-700 ring-1 ring-inset ring-teal-600/20">Rubros nuevos: {{ implode(', ', $summary['categories']) }}</span>
                            @endif
                        </div>

                        @error('rows') <p class="rounded-xl bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror

                        <div class="max-h-[55vh] overflow-auto rounded-xl border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="sticky top-0 z-10 bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="w-8 px-3 py-2"></th>
                                        <th class="px-2 py-2">Estado</th>
                                        <th class="min-w-40 px-2 py-2">Rubro</th>
                                        <th class="min-w-28 px-2 py-2">Código</th>
                                        <th class="min-w-64 px-2 py-2">Descripción</th>
                                        <th class="w-24 px-2 py-2 text-right">Cant.</th>
                                        <th class="w-32 px-2 py-2 text-right">Precio lista</th>
                                        <th class="min-w-28 px-2 py-2">Lote</th>
                                        <th class="min-w-36 px-2 py-2">Vence</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($rows as $i => $row)
                                        @php
                                            $product = $matches[$i] ?? null;
                                            $category = trim($row['category']) ?: trim($defaultCategory);
                                        @endphp
                                        <tr wire:key="import-row-{{ $i }}" @class(['align-top', 'opacity-40' => ! $row['include']])>
                                            <td class="px-3 py-2">
                                                <input type="checkbox" wire:model.live="rows.{{ $i }}.include" class="mt-1.5 rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20" title="Importar esta fila">
                                            </td>
                                            <td class="whitespace-nowrap px-2 py-2">
                                                @if ($product)
                                                    <span class="inline-block rounded-md bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700" title="{{ $product->name }}">Existe · stock {{ $product->stock }}</span>
                                                    @unless ($product->is_active)
                                                        <span class="mt-1 block text-[11px] font-semibold text-amber-700">Dado de baja</span>
                                                    @endunless
                                                    @if ($product->tracks_expiry)
                                                        <span class="mt-1 block text-[11px] text-slate-500">Maneja lotes</span>
                                                    @endif
                                                @else
                                                    <span class="inline-block rounded-md bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700">Nuevo</span>
                                                @endif
                                            </td>
                                            <td class="px-2 py-2">
                                                @if ($product)
                                                    <span class="block pt-1.5 text-xs text-slate-500">Queda en {{ $product->category?->name ?? 'sin rubro' }}</span>
                                                @else
                                                    <input type="text" list="si-categories" wire:model.live.debounce.400ms="rows.{{ $i }}.category" autocomplete="off" placeholder="{{ $defaultCategory ?: 'Rubro' }}" class="{{ $cell }}">
                                                    @if ($category !== '' && ! $existingCategories->contains(mb_strtolower($category)))
                                                        <span class="mt-1 block text-[11px] font-semibold text-teal-700">Rubro nuevo</span>
                                                    @endif
                                                    @error("rows.$i.category") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                                @endif
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="text" wire:model.blur="rows.{{ $i }}.code" autocomplete="off" class="{{ $cell }} font-mono uppercase">
                                                @error("rows.$i.code") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="text" wire:model.blur="rows.{{ $i }}.name" autocomplete="off" class="{{ $cell }}">
                                                @if ($product && mb_strtolower($product->name) !== mb_strtolower(trim($row['name'])))
                                                    <span class="mt-1 block text-[11px] text-slate-500">En inventario: {{ $product->name }}</span>
                                                @endif
                                                @error("rows.$i.name") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="text" inputmode="numeric" wire:model.live.debounce.400ms="rows.{{ $i }}.quantity" class="{{ $cell }} text-right tabular-nums">
                                                @error("rows.$i.quantity") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="text" inputmode="decimal" wire:model="rows.{{ $i }}.cost" class="{{ $cell }} text-right tabular-nums">
                                                @if ($row['vat'] !== null)
                                                    <span class="mt-1 block text-right text-[11px] text-slate-500">IVA {{ \App\Support\Pricing::percent($row['vat']) }}%</span>
                                                @endif
                                                @error("rows.$i.cost") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="text" wire:model="rows.{{ $i }}.lot_number" autocomplete="off" placeholder="{{ $product?->tracks_expiry ? 'Obligatorio' : 'Opcional' }}" class="{{ $cell }} font-mono uppercase">
                                                @error("rows.$i.lot_number") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </td>
                                            <td class="px-2 py-2">
                                                <input type="date" wire:model="rows.{{ $i }}.expiration_date" class="{{ $cell }}">
                                                @error("rows.$i.expiration_date") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="text-xs text-slate-400">
                            El precio de lista se toma como costo de compra. En los nuevos, el precio de venta sale del costo con el remarque y el IVA de arriba; con lote y vencimiento se crean con control de vencimiento.
                        </p>

                        @if ($ignored !== [])
                            <details class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                                <summary class="cursor-pointer font-semibold">{{ count($ignored) }} líneas del PDF sin precio que no se importaron</summary>
                                <ul class="mt-2 max-h-40 list-disc space-y-0.5 overflow-auto pl-5 font-mono">
                                    @foreach ($ignored as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif

                        <div class="flex flex-wrap justify-end gap-2 pt-1">
                            <button type="button" wire:click="restart" class="mr-auto rounded-[10px] px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Elegir otro PDF</button>
                            <button type="button" wire:click="close" class="rounded-[10px] px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100">Cancelar</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="import" class="rounded-[10px] bg-[#0F2A26] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                                <span wire:loading.remove wire:target="import">Importar {{ $summary['new'] + $summary['existing'] }} artículos</span>
                                <span wire:loading wire:target="import">Importando…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
