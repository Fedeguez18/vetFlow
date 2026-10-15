@use('App\Models\Category')

<div class="space-y-6">
    @php
        $input = 'w-full rounded-[10px] border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10';
        $label = 'mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500';

        // Same palette as the inventory catalog badges.
        $categoryColors = [
            'vaccine' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'pharmacy' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
            'nutrition' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
            'identification' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
            'instrument' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
            'accessory' => 'bg-pink-50 text-pink-700 ring-pink-600/20',
        ];
    @endphp

    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Rubros & Proveedores</h1>
        <p class="text-sm text-slate-500">Los rubros y proveedores que cargues acá aparecen al crear o editar artículos y al reponer stock.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Rubros --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Rubros</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ $this->categories->count() }}</span>
            </div>

            <form wire:submit="saveCategory" class="grid gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-4 sm:grid-cols-[1fr_11rem_auto] sm:items-end">
                <div>
                    <label for="categoryName" class="{{ $label }}">{{ $categoryId ? 'Editar rubro' : 'Nuevo rubro' }}</label>
                    <input id="categoryName" type="text" wire:model="categoryName" placeholder="Ej.: Antiparasitarios" maxlength="100" class="{{ $input }}">
                    @error('categoryName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="categoryIcon" class="{{ $label }}">Ícono</label>
                    <select id="categoryIcon" wire:model="categoryIcon" class="{{ $input }}">
                        @foreach (Category::ICONS as $key => $iconLabel)
                            <option value="{{ $key }}">{{ $iconLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveCategory" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                        {{ $categoryId ? 'Guardar' : 'Agregar' }}
                    </button>
                    @if ($categoryId)
                        <button type="button" wire:click="cancelCategory" class="rounded-[10px] px-3 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-white">Cancelar</button>
                    @endif
                </div>
            </form>

            <ul class="divide-y divide-slate-100">
                @forelse ($this->categories as $category)
                    <li wire:key="category-{{ $category->id }}" @class(['flex items-center justify-between gap-3 px-5 py-3', 'bg-emerald-50/50' => $categoryId === $category->id])>
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="shrink-0 rounded-md px-2 py-1 text-[11px] font-bold uppercase tracking-wide ring-1 ring-inset {{ $categoryColors[$category->icon] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">{{ $category->name }}</span>
                            <span class="text-sm text-slate-500">{{ $category->products_count }} {{ $category->products_count === 1 ? 'artículo' : 'artículos' }}</span>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" wire:click="editCategory({{ $category->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Editar</button>
                            <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="¿Eliminar el rubro «{{ $category->name }}»?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Eliminar</button>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">Todavía no hay rubros. Agregá el primero arriba.</li>
                @endforelse
            </ul>
        </section>

        {{-- Proveedores --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Proveedores</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">{{ $this->suppliers->count() }}</span>
            </div>

            <form wire:submit="saveSupplier" class="grid gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="businessName" class="{{ $label }}">{{ $supplierId ? 'Editar proveedor · Razón social' : 'Nuevo proveedor · Razón social' }}</label>
                    <input id="businessName" type="text" wire:model="businessName" placeholder="Ej.: BioPampa Laboratorios S.A." class="{{ $input }}">
                    @error('businessName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cuit" class="{{ $label }}">CUIT</label>
                    <input id="cuit" type="text" wire:model="cuit" inputmode="numeric" placeholder="30-71234567-1" class="{{ $input }} font-mono">
                    @error('cuit') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="{{ $label }}">Teléfono</label>
                    <input id="phone" type="tel" wire:model="phone" placeholder="(0358) 464-2210" class="{{ $input }}">
                    @error('phone') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="supplierEmail" class="{{ $label }}">Email</label>
                    <input id="supplierEmail" type="email" wire:model="email" placeholder="ventas@proveedor.com" class="{{ $input }}">
                    @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveSupplier" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                        {{ $supplierId ? 'Guardar' : 'Agregar proveedor' }}
                    </button>
                    @if ($supplierId)
                        <button type="button" wire:click="cancelSupplier" class="rounded-[10px] px-3 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-white">Cancelar</button>
                    @endif
                </div>
            </form>

            <ul class="divide-y divide-slate-100">
                @forelse ($this->suppliers as $supplier)
                    <li wire:key="supplier-{{ $supplier->id }}" @class(['flex items-start justify-between gap-3 px-5 py-3', 'bg-emerald-50/50' => $supplierId === $supplier->id])>
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-900">{{ $supplier->business_name }}</div>
                            <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-500">
                                @if ($supplier->cuit) <span class="font-mono">CUIT {{ $supplier->cuit }}</span> @endif
                                @if ($supplier->phone) <span>{{ $supplier->phone }}</span> @endif
                                @if ($supplier->email) <a href="mailto:{{ $supplier->email }}" class="hover:text-slate-800 hover:underline">{{ $supplier->email }}</a> @endif
                                <span>{{ $supplier->products_count }} {{ $supplier->products_count === 1 ? 'artículo' : 'artículos' }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" wire:click="editSupplier({{ $supplier->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900">Editar</button>
                            <button type="button" wire:click="deleteSupplier({{ $supplier->id }})" wire:confirm="¿Eliminar el proveedor «{{ $supplier->business_name }}»?" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Eliminar</button>
                        </div>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">Todavía no hay proveedores. Agregá el primero arriba.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
