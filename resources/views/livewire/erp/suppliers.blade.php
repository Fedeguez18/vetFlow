@use('App\Models\Producer')

@php
    $suppliers = $this->suppliers;
    $editingId = $form->supplier?->id;
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Proveedores</h1>
        <p class="text-sm text-slate-500">Los proveedores activos aparecen al crear o editar artículos y al reponer stock. Hacé clic en uno para ver su ficha.</p>
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
        {{-- Listado --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center gap-3 p-4">
                <div class="w-full sm:w-80">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        autocomplete="off"
                        placeholder="Buscar por razón social o CUIT…"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-3 pr-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"
                    >
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" wire:model.live="showInactive" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/20">
                    Mostrar proveedores dados de baja
                </label>
                <span class="ml-auto text-[11px] font-bold uppercase tracking-wide text-slate-500">Registros: {{ $suppliers->total() }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Proveedor</th>
                            <th class="px-5 py-3">Contacto</th>
                            <th class="px-5 py-3 text-center">Artículos</th>
                            <th class="px-5 py-3 text-center">Stock bajo</th>
                            <th class="px-5 py-3">Última reposición</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($suppliers as $supplier)
                            @php($deletable = $supplier->products_count === 0 && ! $supplier->last_restock_at)
                            <tr wire:key="supplier-{{ $supplier->id }}" @class(['hover:bg-slate-50/60', 'bg-emerald-50/50' => $editingId === $supplier->id, 'text-slate-400' => ! $supplier->is_active])>
                                <td class="px-5 py-3">
                                    <a href="{{ route('erp.suppliers.show', $supplier) }}" @class(['font-bold hover:text-emerald-700 hover:underline', 'text-slate-900' => $supplier->is_active, 'text-slate-500' => ! $supplier->is_active])>{{ $supplier->business_name }}</a>
                                    @unless ($supplier->is_active)
                                        <span class="ml-1 inline-block rounded bg-slate-200 px-1.5 text-[10px] font-semibold uppercase text-slate-600">De baja</span>
                                    @endunless
                                    @if ($supplier->cuit)
                                        <span class="block font-mono text-xs text-slate-500">CUIT {{ $supplier->cuit }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-500">
                                    @if ($supplier->contact_name) <span class="block font-semibold text-slate-700">{{ $supplier->contact_name }}</span> @endif
                                    @if ($supplier->phone) <span class="block">{{ $supplier->phone }}</span> @endif
                                    @if ($supplier->email) <a href="mailto:{{ $supplier->email }}" class="block hover:text-slate-800 hover:underline">{{ $supplier->email }}</a> @endif
                                    @if (! $supplier->contact_name && ! $supplier->phone && ! $supplier->email) <span class="text-slate-400">Sin datos</span> @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-center tabular-nums text-slate-700">{{ $supplier->products_count }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-center">
                                    @if ($supplier->low_stock_count > 0)
                                        <span class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-bold tabular-nums text-amber-700 ring-1 ring-inset ring-amber-600/25">{{ $supplier->low_stock_count }}</span>
                                    @else
                                        <span class="tabular-nums text-slate-400">0</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                                    {{ $supplier->last_restock_at?->format('d/m/Y') ?? 'Sin reposiciones' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <a href="{{ route('erp.suppliers.show', $supplier) }}" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Ver ficha</a>
                                    <button type="button" wire:click="editSupplier({{ $supplier->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Editar</button>
                                    @if ($supplier->is_active)
                                        <button type="button" wire:click="deactivateSupplier({{ $supplier->id }})" wire:confirm="¿Dar de baja a «{{ $supplier->business_name }}»? No va a aparecer más al cargar artículos ni al reponer; sus artículos y el historial se conservan." class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50">Dar de baja</button>
                                    @else
                                        <button type="button" wire:click="reactivateSupplier({{ $supplier->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Reactivar</button>
                                    @endif
                                    @if ($deletable)
                                        <button type="button" wire:click="deleteSupplier({{ $supplier->id }})" wire:confirm="¿Eliminar el proveedor «{{ $supplier->business_name }}»?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Eliminar</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-16 text-center text-slate-500">
                                    {{ trim($search) !== '' ? 'No hay proveedores que coincidan con la búsqueda.' : 'Todavía no hay proveedores. Agregá el primero desde el formulario.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                    @if ($suppliers->total() > 0)
                        Viendo {{ $suppliers->firstItem() }}–{{ $suppliers->lastItem() }} de {{ $suppliers->total() }} proveedores
                    @else
                        Sin proveedores
                    @endif
                </span>

                <x-pagination :paginator="$suppliers" />
            </div>
        </section>

        {{-- Alta / edición --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-24">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">{{ $editingId ? 'Editar proveedor' : 'Nuevo proveedor' }}</h2>
                @if ($editingId)
                    <p class="text-xs text-slate-500">{{ $form->supplier->business_name }}</p>
                @endif
            </div>

            <form wire:submit="saveSupplier" class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-form.label for="businessName">Razón social</x-form.label>
                    <x-form.input id="businessName" type="text" wire:model="form.business_name" placeholder="Ej.: BioPampa Laboratorios S.A." class="text-sm" />
                    <x-form.error name="form.business_name" />
                </div>

                <div>
                    <x-form.label for="cuit">CUIT</x-form.label>
                    <x-form.input id="cuit" type="text" wire:model="form.cuit" inputmode="numeric" placeholder="30-71234567-1" class="text-sm font-mono" />
                    <x-form.error name="form.cuit" />
                </div>

                <div>
                    <x-form.label for="ivaCondition">Condición de IVA</x-form.label>
                    <x-form.select id="ivaCondition" wire:model="form.iva_condition" class="text-sm">
                        <option value="">Sin especificar</option>
                        @foreach (Producer::IVA_CONDITIONS as $condition)
                            <option value="{{ $condition }}">{{ $condition }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.error name="form.iva_condition" />
                </div>

                <div class="sm:col-span-2">
                    <x-form.label for="contactName">Contacto (vendedor o representante)</x-form.label>
                    <x-form.input id="contactName" type="text" wire:model="form.contact_name" placeholder="Ej.: Martín Pérez" class="text-sm" />
                    <x-form.error name="form.contact_name" />
                </div>

                <div>
                    <x-form.label for="phone">Teléfono (WhatsApp)</x-form.label>
                    <x-form.input id="phone" type="tel" wire:model="form.phone" placeholder="(0358) 464-2210" class="text-sm" />
                    <x-form.error name="form.phone" />
                </div>

                <div>
                    <x-form.label for="supplierEmail">Email</x-form.label>
                    <x-form.input id="supplierEmail" type="email" wire:model="form.email" placeholder="ventas@proveedor.com" class="text-sm" />
                    <x-form.error name="form.email" />
                </div>

                <div>
                    <x-form.label for="address">Dirección</x-form.label>
                    <x-form.input id="address" type="text" wire:model="form.address" placeholder="Ej.: Av. Sabattini 1450" class="text-sm" />
                    <x-form.error name="form.address" />
                </div>

                <div>
                    <x-form.label for="city">Localidad</x-form.label>
                    <x-form.input id="city" type="text" wire:model="form.city" placeholder="Ej.: Río Cuarto" class="text-sm" />
                    <x-form.error name="form.city" />
                </div>

                <div class="sm:col-span-2">
                    <x-form.label for="paymentTerms">Condiciones de pago</x-form.label>
                    <x-form.input id="paymentTerms" type="text" wire:model="form.payment_terms" placeholder="Ej.: 30 días fecha factura" class="text-sm" />
                    <x-form.error name="form.payment_terms" />
                </div>

                <div class="sm:col-span-2">
                    <x-form.label for="notes">Notas</x-form.label>
                    <textarea id="notes" wire:model="form.notes" rows="3" placeholder="Días de entrega, mínimo de compra, etc." class="w-full rounded-[10px] border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"></textarea>
                    <x-form.error name="form.notes" />
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveSupplier" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                        {{ $editingId ? 'Guardar' : 'Agregar proveedor' }}
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="cancelSupplier" class="rounded-[10px] px-3 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-white">Cancelar</button>
                    @endif
                </div>
            </form>
        </section>
    </div>
</div>
