<div class="w-full max-w-sm">
    <div class="mb-6 flex items-center justify-center gap-2.5">
        <x-logo-mark class="size-10" />
        <span class="text-2xl font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
    </div>

    <form wire:submit="login" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Ingresar</h1>
            <p class="text-sm text-slate-500">Gestión y mostrador de la veterinaria.</p>
        </div>

        @if (session('status'))
            <p class="rounded-[10px] bg-emerald-50 px-3 py-2.5 text-sm text-emerald-800 ring-1 ring-emerald-600/15">{{ session('status') }}</p>
        @endif

        <div>
            <label for="email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
            <input id="email" type="email" wire:model="email" autofocus autocomplete="username" class="w-full rounded-[10px] border border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-900 focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10">
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <div class="mb-1 flex items-center justify-between">
                <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Contraseña</label>
                <a href="{{ route('password.request') }}" class="text-xs font-semibold text-[#0F2A26] hover:underline">¿La olvidaste?</a>
            </div>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" class="w-full rounded-[10px] border border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-900 focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10">
            @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="rounded border-slate-300 text-[#0F2A26] focus:ring-[#0F2A26]/20">
            Recordarme en este equipo
        </label>

        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
            <span wire:loading.remove wire:target="login">Ingresar</span>
            <span wire:loading wire:target="login">Ingresando…</span>
        </button>

        @if (config('auth.registration'))
            <p class="text-center text-sm text-slate-500">
                ¿No tenés cuenta? <a href="{{ route('register') }}" class="font-semibold text-[#0F2A26] hover:underline">Crear cuenta</a>
            </p>
        @endif
    </form>
</div>
