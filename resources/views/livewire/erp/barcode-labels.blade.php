<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Etiquetas de código de barras</h1>
            <p class="text-sm text-slate-500">Cada artículo al que le generás un código en el inventario se suma acá. Imprimí la hoja, pegá las etiquetas y vaciala.</p>
        </div>

        @if ($this->labels->isNotEmpty())
            <div class="flex gap-2">
                <a href="{{ route('erp.labels.print') }}" target="_blank" class="whitespace-nowrap rounded-[10px] bg-[#0F2A26] px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-[#1A3D37]">
                    Imprimir hoja
                </a>
                <button type="button" wire:click="clear" wire:confirm="¿Ya imprimiste las etiquetas? La hoja queda vacía." class="whitespace-nowrap rounded-[10px] px-4 py-2.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-300 hover:bg-white">
                    Vaciar hoja
                </button>
            </div>
        @endif
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Pendientes de imprimir</h2>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                {{ $total }} {{ $total === 1 ? 'etiqueta' : 'etiquetas' }} · {{ $sheets }} {{ $sheets === 1 ? 'hoja A4' : 'hojas A4' }}
            </span>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse ($this->labels as $label)
                <li wire:key="label-{{ $label->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-slate-900">{{ $label->product->name }}</div>
                        <div class="font-mono text-xs text-slate-500">{{ $label->product->barcode }} · Stock {{ $label->product->stock }}</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <label for="copies-{{ $label->id }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cantidad</label>
                        <input
                            id="copies-{{ $label->id }}"
                            type="number"
                            min="1"
                            max="{{ \App\Models\BarcodeLabel::MAX_COPIES }}"
                            value="{{ $label->copies }}"
                            wire:change="updateCopies({{ $label->id }}, $event.target.value)"
                            class="w-20 rounded-[10px] border border-slate-300 bg-white px-3 py-1.5 text-right font-mono text-sm text-slate-900 focus:border-[#0F2A26] focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10"
                        >
                        <button type="button" wire:click="remove({{ $label->id }})" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Quitar</button>
                    </div>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-500">No hay etiquetas pendientes. En el inventario, editá un artículo sin código y tocá «Generar».</li>
            @endforelse
        </ul>
    </section>
</div>
