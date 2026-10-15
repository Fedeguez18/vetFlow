@props(['category'])

@php
    // Badge palette per color key stored in categories.icon (see Category::COLORS).
    $colors = [
        'vaccine' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'pharmacy' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'nutrition' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'identification' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'instrument' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
        'accessory' => 'bg-pink-50 text-pink-700 ring-pink-600/20',
    ];
@endphp

<span {{ $attributes->class(['rounded-md px-2 py-1 text-[11px] font-bold uppercase tracking-wide ring-1 ring-inset', $colors[$category->icon] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20']) }}>{{ $category->name }}</span>
