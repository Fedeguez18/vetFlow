<div class="w-full max-w-sm">
    <div class="mb-6 flex items-center justify-center gap-2.5">
        <x-logo-mark class="size-10" />
        <span class="text-2xl font-bold tracking-tight text-slate-900">vete<span class="text-emerald-600">Flow</span></span>
    </div>

    <form wire:submit="resetPassword" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Restablecer contraseña</h1>
            <p class="text-sm text-slate-500">Elegí una contraseña nueva para tu cuenta.</p>
        </div>

        <div>
            <x-form.label for="email">Email</x-form.label>
            <x-form.input id="email" type="email" wire:model="email" autocomplete="username" />
            <x-form.error name="email" />
        </div>

        <div>
            <x-form.label for="password">Contraseña nueva</x-form.label>
            <x-form.input id="password" type="password" wire:model="password" autofocus autocomplete="new-password" />
            <x-form.error name="password" />
        </div>

        <div>
            <x-form.label for="password_confirmation">Repetir contraseña</x-form.label>
            <x-form.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
        </div>

        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-[10px] bg-[#0F2A26] px-4 py-3 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
            <span wire:loading.remove wire:target="resetPassword">Guardar contraseña</span>
            <span wire:loading wire:target="resetPassword">Guardando…</span>
        </button>
    </form>
</div>
