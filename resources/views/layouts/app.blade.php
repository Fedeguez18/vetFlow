<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · veteFlow' : 'veteFlow' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full bg-[#F4F6F5] font-sans text-slate-800 antialiased">
    @php
        $tabs = [
            ['label' => 'Mostrador POS', 'path' => 'pos'],
            ['label' => 'Inventario & Lotes', 'path' => 'inventario'],
            ['label' => 'Cuentas Corrientes & Remitos', 'path' => 'cuentas-corrientes'],
            ['label' => 'Auditoría & Caja', 'path' => 'auditoria'],
        ];
        $user = auth()->user();
    @endphp

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="flex min-h-16 w-full flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-2 sm:px-6">
            <div class="flex items-center gap-3">
                <a href="{{ url('/pos') }}" class="flex items-center gap-2">
                    <x-logo-mark class="size-8" />
                    <span class="text-lg font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
                </a>

                <span class="hidden items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-600/15 md:inline-flex">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                    </span>
                    Sync en vivo con mostrador POS
                </span>
            </div>

            <nav class="order-last flex w-full gap-1 overflow-x-auto rounded-xl bg-slate-100 p-1 xl:order-none xl:w-auto">
                @foreach ($tabs as $tab)
                    @php($active = request()->is($tab['path'], $tab['path'].'/*'))
                    <a
                        href="{{ url($tab['path']) }}"
                        @class([
                            'shrink-0 rounded-lg px-4 py-2 text-center text-sm font-semibold transition-colors',
                            'bg-[#0F2A26] text-white shadow-sm' => $active,
                            'text-slate-600 hover:bg-white hover:text-slate-900' => ! $active,
                        ])
                        @if ($active) aria-current="page" @endif
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-4">
                <div
                    x-data="{
                        time: '--:--',
                        tick() { this.time = new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); },
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    class="hidden font-mono text-sm font-semibold tabular-nums text-slate-500 lg:block"
                    x-text="time"
                >--:--</div>

                @if ($user)
                    <div class="flex items-center gap-2.5">
                        <div class="hidden text-right leading-tight sm:block">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $user->role }}</div>
                            <div class="text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                        </div>
                        <span class="flex size-9 items-center justify-center rounded-full bg-[#0F2A26]/10 text-sm font-bold text-[#0F2A26] ring-2 ring-white">
                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="Cerrar sesión" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @endif

                @stack('header-actions')
            </div>
        </div>
    </header>

    <main class="w-full px-4 py-6 sm:px-6">
        @isset($slot)
            {{ $slot }}
        @else
            @yield('content')
        @endisset
    </main>

    {{-- Toasts: $this->dispatch('toast', message: '…', type: 'success'|'error') --}}
    <div
        x-data="{
            toasts: [],
            add(detail) {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message: detail.message, type: detail.type ?? 'success' });
                setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 4000);
            },
        }"
        x-on:toast.window="add($event.detail)"
        class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-full max-w-sm flex-col gap-2"
    >
        <template x-for="toast in toasts" x-bind:key="toast.id">
            <div
                x-transition.opacity
                x-bind:class="toast.type === 'error' ? 'bg-rose-600' : 'bg-[#0F2A26]'"
                class="pointer-events-auto flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5 shrink-0 text-emerald-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
