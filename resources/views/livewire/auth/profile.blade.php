<div class="mx-auto max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mi perfil</h1>
        <p class="text-sm text-slate-500">Tus datos de acceso a veteFlow.</p>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <form wire:submit="updateProfile" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Datos personales</h2>

            <div>
                <x-form.label for="name">Nombre</x-form.label>
                <x-form.input id="name" type="text" wire:model="name" autocomplete="name" />
                <x-form.error name="name" />
            </div>

            <div>
                <x-form.label for="email">Email</x-form.label>
                <x-form.input id="email" type="email" wire:model="email" autocomplete="username" />
                <x-form.error name="email" />
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="updateProfile" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                Guardar datos
            </button>
        </form>

        <form wire:submit="updatePassword" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Cambiar contraseña</h2>

            <div>
                <x-form.label for="current_password">Contraseña actual</x-form.label>
                <x-form.input id="current_password" type="password" wire:model="current_password" autocomplete="current-password" />
                <x-form.error name="current_password" />
            </div>

            <div>
                <x-form.label for="password">Contraseña nueva</x-form.label>
                <x-form.input id="password" type="password" wire:model="password" autocomplete="new-password" />
                <x-form.error name="password" />
            </div>

            <div>
                <x-form.label for="password_confirmation">Repetir contraseña nueva</x-form.label>
                <x-form.input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="updatePassword" class="rounded-[10px] bg-[#0F2A26] px-4 py-2.5 font-bold text-white shadow-sm hover:bg-[#1A3D37] disabled:opacity-50">
                Cambiar contraseña
            </button>
        </form>
    </div>
</div>
