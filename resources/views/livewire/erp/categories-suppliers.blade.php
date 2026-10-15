@use('App\Models\Category')

<div class="space-y-6">
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

            <form wire:submit="saveCategory" class="grid gap-3 border-b border-slate-200 bg-slate-50/60 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-start">
                <div>
                    <x-form.label for="categoryName">{{ $categoryId ? 'Editar rubro' : 'Nuevo rubro' }}</x-form.label>
                    <x-form.input id="categoryName" type="text" wire:model="categoryName" placeholder="Ej.: Antiparasitarios" maxlength="100" class="text-sm" />
                    <x-form.error name="categoryName" />
                </div>

                <div>
                    <x-form.label for="categoryColor">Color</x-form.label>
                    <x-form.select id="categoryColor" wire:model="categoryColor" class="text-sm">
                        @foreach (Category::COLORS as $key => $colorLabel)
                            <option value="{{ $key }}">{{ $colorLabel }}</option>
                        @endforeach
                    </x-form.select>
                </div>

                {{-- Top padding = label height, so the buttons line up with the inputs even when an error grows the first column. --}}
                <div class="flex gap-2 sm:pt-5">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveCategory" class="whitespace-nowrap rounded-[10px] bg-[#0F2A26] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                        {{ $categoryId ? 'Guardar' : 'Agregar' }}
                    </button>
                    @if ($categoryId)
                        <button type="button" wire:click="cancelCategory" class="whitespace-nowrap rounded-[10px] px-3 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-white">Cancelar</button>
                    @endif
                </div>
            </form>

            <ul class="divide-y divide-slate-100">
                @forelse ($this->categories as $category)
                    <li wire:key="category-{{ $category->id }}" @class(['flex items-center justify-between gap-3 px-5 py-3', 'bg-emerald-50/50' => $categoryId === $category->id])>
                        <div class="flex min-w-0 items-center gap-3">
                            <x-category-badge :category="$category" class="shrink-0" />
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
                    <x-form.label for="businessName">{{ $supplierId ? 'Editar proveedor · Razón social' : 'Nuevo proveedor · Razón social' }}</x-form.label>
                    <x-form.input id="businessName" type="text" wire:model="businessName" placeholder="Ej.: BioPampa Laboratorios S.A." class="text-sm" />
                    <x-form.error name="businessName" />
                </div>

                <div>
                    <x-form.label for="cuit">CUIT</x-form.label>
                    <x-form.input id="cuit" type="text" wire:model="cuit" inputmode="numeric" placeholder="30-71234567-1" class="text-sm font-mono" />
                    <x-form.error name="cuit" />
                </div>

                <div>
                    <x-form.label for="phone">Teléfono</x-form.label>
                    <x-form.input id="phone" type="tel" wire:model="phone" placeholder="(0358) 464-2210" class="text-sm" />
                    <x-form.error name="phone" />
                </div>

                <div class="sm:col-span-2">
                    <x-form.label for="supplierEmail">Email</x-form.label>
                    <x-form.input id="supplierEmail" type="email" wire:model="email" placeholder="ventas@proveedor.com" class="text-sm" />
                    <x-form.error name="email" />
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
