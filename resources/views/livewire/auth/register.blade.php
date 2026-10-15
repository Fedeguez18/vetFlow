<div class="w-full max-w-sm">
    @php($input = 'w-full rounded-[10px] border border-slate-300 bg-slate-50 px-3 py-2.5 text-slate-900 focus:border-[#0F2A26] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#0F2A26]/10')

    <div class="mb-6 flex items-center justify-center gap-2.5">
        <x-logo-mark class="size-10" />
        <span class="text-2xl font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
    </div>

    <form wire:submit="register" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Crear cuenta</h1>
            <p class="text-sm text-slate-500">Acceso al mostrador y a la gestión de la veterinaria.</p>
        </div>

        <div>
            <label for="name" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</label>
            <input id="name" type="text" wire:model="name" autofocus autocomplete="name" class="{{ $input }}">
            @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" class="{{ $input }}">
            @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Contraseña</label>
            <input id="password" type="password" wire:model="password" autocomplete="new-password" class="{{ $input }}">
            @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Repetir contraseña</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" class="{{ $input }}">
        </div>

        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
            <span wire:loading.remove wire:target="register">Crear cuenta</span>
            <span wire:loading wire:target="register">Creando…</span>
        </button>

        <p class="text-center text-sm text-slate-500">
            ¿Ya tenés cuenta? <a href="{{ route('login') }}" class="font-semibold text-[#0F2A26] hover:underline">Ingresar</a>
        </p>
    </form>
</div>
