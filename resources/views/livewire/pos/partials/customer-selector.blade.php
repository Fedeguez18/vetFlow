@use('App\Support\Pricing')
@use('Illuminate\Support\Str')

{{-- POS customer picker: "Consumidor Final" or a producer (required for remitos). --}}
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
                CF
            @endif
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-900">
                {{ $producer ? $producer->name.($producer->business_name ? ' — '.$producer->business_name : '') : 'Consumidor Final' }}
            </span>
            <span class="block truncate text-[11px] text-slate-400">
                @if ($producer)
                    {{ collect([
                        $producer->cuit ? 'CUIT: '.$producer->cuit : null,
                        $producer->hasCreditBalance() ? 'A favor: '.Pricing::money(-$producer->balance) : 'Saldo: '.Pricing::money($producer->balance),
                        $producer->credit_limit !== null ? 'Límite: '.Pricing::money($producer->credit_limit) : null,
                    ])->filter()->join(' · ') }}
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
            @foreach ($producers as $item)
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
                            <span class="shrink-0 text-xs font-semibold tabular-nums text-rose-600">{{ Pricing::money($item->balance) }}</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
</div>
