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
    @php($user = auth()->user())

    {{-- Terminal Mostrador (PC 1): sin navegación hacia el ERP. --}}
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white">
        <div class="flex min-h-14 w-full items-center justify-between gap-4 px-4 py-2 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-2">
                    <x-logo-mark class="size-8" />
                    <span class="text-lg font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
                </span>

                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-700 ring-1 ring-emerald-600/15">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span>
                    </span>
                    Terminal Mostrador (PC 1)
                </span>
            </div>

            <div class="flex items-center gap-4">
                <div
                    x-data="{
                        time: '--:--',
                        tick() { this.time = new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); },
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    class="font-mono text-sm font-semibold tabular-nums text-slate-500"
                    x-text="time"
                >--:--</div>

                @if ($user)
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('profile') }}" target="_blank" title="Mi perfil" class="hidden text-right leading-tight hover:opacity-80 sm:block">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $user->role }}</div>
                            <div class="text-sm font-semibold text-slate-900">{{ $user->name }}</div>
                        </a>
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
            </div>
        </div>
    </header>

    <main class="w-full px-4 py-6 sm:px-6">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
