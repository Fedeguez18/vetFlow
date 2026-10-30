@props(['paginator'])

@php
    // Page window like "1 2 3 … 13", same as the inventory table.
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect([1, $last, $current - 1, $current, $current + 1])
        ->merge($current <= 2 ? [2, 3] : [])
        ->merge($current >= $last - 1 ? [$last - 1, $last - 2] : [])
        ->filter(fn ($page) => $page >= 1 && $page <= $last)
        ->unique()
        ->sort()
        ->values();
@endphp

@if ($last > 1)
    <nav class="flex items-center gap-1.5" aria-label="Paginación">
        <button type="button" wire:click="previousPage" @disabled($paginator->onFirstPage()) class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:opacity-40">‹</button>
        @foreach ($pages as $i => $page)
            @if ($i > 0 && $page - $pages[$i - 1] > 1)
                <span class="px-1 text-slate-400">…</span>
            @endif
            <button
                type="button"
                wire:key="page-{{ $page }}"
                wire:click="gotoPage({{ $page }})"
                @class([
                    'flex size-9 items-center justify-center rounded-lg text-sm font-semibold',
                    'bg-[#0F2A26] text-white shadow-sm' => $page === $current,
                    'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' => $page !== $current,
                ])
                @if ($page === $current) aria-current="page" @endif
            >{{ $page }}</button>
        @endforeach
        <button type="button" wire:click="nextPage" @disabled(! $paginator->hasMorePages()) class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 disabled:opacity-40">›</button>
    </nav>
@endif
