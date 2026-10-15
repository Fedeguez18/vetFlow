<div class="w-full max-w-sm">
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
            <x-form.label for="name">Nombre</x-form.label>
            <x-form.input id="name" type="text" wire:model="name" autofocus autocomplete="name" />
            <x-form.error name="name" />
        </div>

        <div>
            <x-form.label for="email">Email</x-form.label>
            <x-form.input id="email" type="email" wire:model="email" autocomplete="username" />
            <x-form.error name="email" />
        </div>

        <div>
            <x-form.label for="password">Contraseña</x-form.label>
            <x-form.input id="password" type="password" wire:model="password" autocomplete="new-password" />
            <x-form.error name="password" />
        </div>

        <div>
            <x-form.label for="password_confirmation">Repetir contraseña</x-form.label>
            <x-form.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
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
